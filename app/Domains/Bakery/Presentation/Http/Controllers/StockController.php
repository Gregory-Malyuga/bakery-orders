<?php

namespace Domains\Bakery\Presentation\Http\Controllers;

use Domains\Bakery\Application\StockLedger;
use Domains\User\Domain\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockController
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function index(Request $request): View
    {
        $this->ledger->syncCatalog();
        /** @var array{date?:string} $filters */
        $filters = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $filters['date'] ?? now('Europe/Moscow')->toDateString();
        $items = DB::table('bakery_stock_items')->orderBy('kind')->orderBy('name')->get();
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

        return view('bakery.stock', compact('items', 'balances', 'movements', 'discrepancies', 'date'));
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
