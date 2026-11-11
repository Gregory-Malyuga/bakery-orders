<?php

namespace Domains\Bakery\Presentation\Http\Controllers;

use Domains\Bakery\Application\StockLedger;
use Domains\Bakery\Application\StockPrices;
use Domains\User\Domain\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockController
{
    public function __construct(private readonly StockLedger $ledger, private readonly StockPrices $stockPrices) {}

    public function index(Request $request): View
    {
        $this->ledger->syncCatalog();
        /** @var array{date?:string} $filters */
        $filters = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $filters['date'] ?? now('Europe/Moscow')->toDateString();
        $items = DB::table('bakery_stock_items')->orderBy('kind')->orderBy('name')->get();
        $today = now('Europe/Moscow')->toDateString();
        $tomorrow = now('Europe/Moscow')->addDay()->toDateString();
        $prices = $this->stockPrices->forDate($date);
        $tomorrowPrices = DB::table('bakery_stock_price_changes')->where('effective_date', '<=', $tomorrow)
            ->orderBy('stock_item_id')->orderByDesc('effective_date')->orderByDesc('id')->get()->unique('stock_item_id')->keyBy('stock_item_id');
        $priceHistory = DB::table('bakery_stock_price_changes as price')
            ->join('bakery_stock_items as item', 'item.id', '=', 'price.stock_item_id')
            ->join('users as actor', 'actor.id', '=', 'price.actor_id')
            ->select('price.*', 'item.name as item_name', 'actor.name as actor_name')
            ->orderByDesc('price.id')->limit(50)->get();
        $balances = DB::table('bakery_stock_movements')->where('movement_date', '<=', $date)
            ->select('stock_item_id')->selectRaw('SUM(quantity_milli) AS balance')->groupBy('stock_item_id')
            ->pluck('balance', 'stock_item_id');
        $movements = DB::table('bakery_stock_movements as movement')
            ->join('bakery_stock_items as item', 'item.id', '=', 'movement.stock_item_id')
            ->join('users as actor', 'actor.id', '=', 'movement.actor_id')
            ->where('movement.movement_date', '<=', $date)
            ->orderByDesc('movement.movement_date')->orderByDesc('movement.id')
            ->select('movement.*', 'item.name as item_name', 'item.unit', 'actor.name as actor_name')->limit(100)->get();
        $discrepancies = DB::table('bakery_stock_movements as movement')
            ->join('bakery_stock_items as item', 'item.id', '=', 'movement.stock_item_id')
            ->where('movement.kind', 'inventory')->where('movement.movement_date', '<=', $date)
            ->whereNotIn('movement.id', DB::table('bakery_stock_movements')->whereNotNull('reverses_id')->select('reverses_id'))
            ->orderByDesc('movement.id')->select('movement.*', 'item.name as item_name', 'item.unit')->limit(50)->get();
        /** @var Collection<int, object{stock_item_id:int,price_date:string}> $discrepancyPriceRecords */
        $discrepancyPriceRecords = DB::table('bakery_stock_daily_prices')
            ->whereIn('price_date', $discrepancies->pluck('movement_date')->unique())->get();
        $discrepancyPrices = $discrepancyPriceRecords->keyBy(fn (object $price): string => $price->stock_item_id.'|'.$price->price_date);

        return view('bakery.stock', compact('items', 'balances', 'movements', 'discrepancies', 'discrepancyPrices', 'date', 'today', 'tomorrow', 'prices', 'tomorrowPrices', 'priceHistory'));
    }

    public function savePrice(Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        /** @var array{item:int,price:string,measure:string,custom_quantity?:string,custom_unit?:string} $data */
        $data = $request->validate([
            'item' => ['required', 'integer', 'exists:bakery_stock_items,id'],
            'price' => ['required', 'string', 'max:20'],
            'measure' => ['required', 'in:1g,70g,100g,1kg,1ml,100ml,1l,1unit,custom'],
            'custom_quantity' => ['required_if:measure,custom', 'nullable', 'string', 'max:20'],
            'custom_unit' => ['required_if:measure,custom', 'nullable', 'in:g,kg,ml,l,pcs,tbsp,m,roll'],
        ]);
        /** @var object{unit:string}|null $item */
        $item = DB::table('bakery_stock_items')->find($data['item']);
        if ($item === null) {
            throw ValidationException::withMessages(['item' => 'Позиция склада не найдена.']);
        }
        $price = str_replace(',', '.', trim($data['price']));
        if (! preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $price)) {
            throw ValidationException::withMessages(['price' => 'Укажите цену в рублях с точностью до копейки.']);
        }
        [$rubles, $kopecks] = array_pad(explode('.', $price, 2), 2, '');
        $priceKopecks = (int) $rubles * 100 + (int) str_pad($kopecks, 2, '0');
        if ($priceKopecks <= 0) {
            throw ValidationException::withMessages(['price' => 'Цена должна быть больше нуля.']);
        }
        [$quantity, $unit] = match ($data['measure']) {
            '1g' => ['1', 'g'], '70g' => ['70', 'g'], '100g' => ['100', 'g'], '1kg' => ['1', 'kg'],
            '1ml' => ['1', 'ml'], '100ml' => ['100', 'ml'], '1l' => ['1', 'l'],
            '1unit' => ['1', (string) $item->unit],
            default => [$data['custom_quantity'] ?? '', $data['custom_unit'] ?? ''],
        };
        $baseUnit = match ($unit) {
            'kg' => 'g', 'l' => 'ml', default => $unit
        };
        $stockUnit = match ((string) $item->unit) {
            'kg' => 'g', 'l' => 'ml', default => (string) $item->unit
        };
        if ($baseUnit !== $stockUnit) {
            throw ValidationException::withMessages(['measure' => 'Мера цены должна соответствовать единице складской позиции.']);
        }
        $quantity = str_replace(',', '.', trim($quantity));
        if (! preg_match('/^\d{1,9}(?:\.\d{1,3})?$/', $quantity)) {
            throw ValidationException::withMessages(['custom_quantity' => 'Укажите положительную меру до трёх знаков после запятой.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');
        $measureMilli = ((int) $whole * 1000 + (int) str_pad($fraction, 3, '0')) * (in_array($unit, ['kg', 'l'], true) ? 1000 : 1);
        if ($measureMilli <= 0) {
            throw ValidationException::withMessages(['custom_quantity' => 'Мера должна быть больше нуля.']);
        }
        $effectiveDate = now('Europe/Moscow')->addDay()->toDateString();
        $previous = DB::table('bakery_stock_price_changes')->where('stock_item_id', $data['item'])
            ->where('effective_date', '<=', $effectiveDate)->orderByDesc('effective_date')->orderByDesc('id')->first();
        DB::table('bakery_stock_price_changes')->insert([
            'stock_item_id' => $data['item'], 'effective_date' => $effectiveDate,
            'price_kopecks' => $priceKopecks, 'measure_milli' => $measureMilli,
            'measure_unit' => $unit, 'actor_id' => $actor->id,
            'previous_price_kopecks' => $previous?->price_kopecks,
            'previous_measure_milli' => $previous?->measure_milli,
            'previous_measure_unit' => $previous?->measure_unit,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('bakery.stock')->with('success', 'Цена на завтра сохранена.');
    }

    public function createPackaging(Request $request): RedirectResponse
    {
        /** @var array{name:string,unit:string} $data */
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'unit' => ['required', 'in:pcs,g,m,roll']]);
        $name = trim($data['name']);
        if ($name === '' || DB::table('bakery_stock_items')->where('kind', 'packaging')->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
            throw ValidationException::withMessages(['name' => 'Упаковка с таким названием уже есть или название пустое.']);
        }
        DB::table('bakery_stock_items')->insert([
            'kind' => 'packaging', 'name' => $name, 'unit' => $data['unit'], 'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('bakery.stock')->with('success', 'Упаковка добавлена.');
    }

    public function record(Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        /** @var array{item:int,kind:string,quantity:string,reason:string} $data */
        $data = $request->validate([
            'item' => ['required', 'integer', 'exists:bakery_stock_items,id'],
            'kind' => ['required', 'in:opening,receipt,consumption,writeoff,return,inventory'],
            'quantity' => ['required', 'string', 'max:20'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $quantity = $this->ledger->quantity($data['quantity']);
        $reason = trim($data['reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Укажите основание движения.']);
        }
        if ($data['kind'] === 'inventory') {
            $this->ledger->inventory($data['item'], $quantity, $reason, (int) $actor->id);
        } else {
            $this->ledger->record($data['item'], $data['kind'], $quantity, $reason, (int) $actor->id);
        }

        return redirect()->route('bakery.stock')->with('success', 'Движение записано.');
    }

    public function reverse(Request $request, int $movement): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        /** @var array{reason:string} $data */
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $reason = trim($data['reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Укажите причину исправления.']);
        }
        $this->ledger->reverse($movement, $reason, (int) $actor->id);

        return redirect()->route('bakery.stock')->with('success', 'Исправление записано отдельным движением.');
    }
}
