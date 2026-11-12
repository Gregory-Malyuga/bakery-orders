<?php

namespace Domains\Bakery\Presentation\Http\Controllers;

use Domains\Bakery\Application\OrderReturnSummary;
use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DeliveryRouteController
{
    public function __construct(private readonly OrderingRepositoryInterface $orders, private readonly OrderReturnSummary $returns) {}

    public function index(Request $request): View
    {
        /** @var array{date?:string,copy?:int|string} $input */
        $input = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'copy' => ['nullable', 'integer', Rule::exists('bakery_delivery_sheets', 'id')],
        ]);
        $now = now('Europe/Moscow');
        $date = $input['date'] ?? ($now->lt($now->copy()->setTimeFromTimeString($this->cutoffTime())) ? $now->toDateString() : $now->copy()->addDay()->toDateString());
        $copyId = isset($input['copy']) ? (int) $input['copy'] : null;
        /** @var object{snapshot:string}|null $copySheet */
        $copySheet = $copyId !== null ? DB::table('bakery_delivery_sheets')->find($copyId) : null;
        /** @var array{route:string,driver:string,salary_kopecks:int|null,rows:list<array{store_id:int}>}|null $copySnapshot */
        $copySnapshot = $copySheet !== null ? json_decode((string) $copySheet->snapshot, true, 512, JSON_THROW_ON_ERROR) : null;

        return view('bakery.delivery-routes', [
            'date' => $date,
            'stores' => $this->orders->stores()->where('is_active', true),
            'sheets' => DB::table('bakery_delivery_sheets')->orderByDesc('id')->limit(100)->get(),
            'prints' => DB::table('bakery_delivery_sheet_prints')->orderByDesc('id')->limit(100)->get(),
            'copyId' => $copyId,
            'copySnapshot' => $copySnapshot,
        ]);
    }

    /**
     * @param  array{date:string,name:string,driver:string,salary?:string,stores:list<int|string>}  $data
     * @return array{date:string,driver:string,route:string,salary_kopecks:int|null,closed:bool,rows:list<array{store_id:int,name:string,address:string,order:int,returns:int,due:int,has_order:bool}>,order:int,returns:int,due:int}
     */
    private function build(array $data): array
    {
        $date = $data['date'];
        /** @var list<int> $ids */
        $ids = array_map(static fn (int|string $id): int => (int) $id, $data['stores']);
        $driver = trim($data['driver']);
        if ($driver === '' || $ids === []) {
            throw ValidationException::withMessages(['driver' => 'Укажите водителя и выберите точки.']);
        }
        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['stores' => 'Точка повторяется в маршруте.']);
        }
        $stores = $this->orders->stores()->keyBy('id');
        $orders = $this->orders->orders($date);
        $byStore = $orders->keyBy('bakery_id');
        $returns = $this->returns->forOrders($orders);
        $rows = [];
        foreach ($ids as $id) {
            $store = $stores->get((int) $id);
            if ($store === null) {
                throw ValidationException::withMessages(['stores' => 'Одна из точек удалена.']);
            }
            $order = $byStore->get((int) $id);
            $amount = $order ? (int) $order->total_kopecks : 0;
            $returned = $order ? ($returns[$order->id]['amount'] ?? 0) : 0;
            $rows[] = ['store_id' => (int) $id, 'name' => (string) $store->name, 'address' => (string) $store->address, 'order' => $amount, 'returns' => $returned, 'due' => $amount - $returned, 'has_order' => $order !== null];
        }
        $salary = isset($data['salary']) && $data['salary'] !== '' ? (int) round((float) str_replace(',', '.', $data['salary']) * 100) : null;

        return ['date' => $date, 'driver' => $driver, 'route' => trim($data['name']), 'salary_kopecks' => $salary, 'closed' => DB::table('bakery_closed_delivery_dates')->where('delivery_date', $date)->exists(), 'rows' => $rows, 'order' => array_sum(array_column($rows, 'order')), 'returns' => array_sum(array_column($rows, 'returns')), 'due' => array_sum(array_column($rows, 'due'))];
    }

    public function createSheet(Request $request): RedirectResponse
    {
        /** @var array{date:string,name:string,driver:string,salary?:string,stores:list<int|string>} $data */
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'string', 'max:255'],
            'salary' => ['nullable', 'regex:/^\d{1,7}([.,]\d{1,2})?$/'],
            'stores' => ['required', 'array', 'min:1'],
            'stores.*' => ['required', 'integer', 'distinct', Rule::exists('bakeries', 'id')],
        ]);
        $this->assertAfterCutoff($data['date']);
        $sheet = $this->build($data);
        $id = DB::table('bakery_delivery_sheets')->insertGetId(['delivery_date' => $data['date'], 'snapshot' => json_encode($sheet, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);

        return redirect()->route('bakery.delivery-sheet', $id);
    }

    private function cutoffTime(): string
    {
        $cutoffValue = DB::table('bakery_settings')->where('key', 'order_cutoff')->value('value');

        return is_string($cutoffValue) && $cutoffValue !== '' ? $cutoffValue : '18:30';
    }

    private function assertAfterCutoff(string $date): void
    {
        $now = now('Europe/Moscow');
        $cutoff = $this->cutoffTime();
        if ($now->lt($now->copy()->setTimeFromTimeString($cutoff)) && $date !== $now->toDateString()) {
            throw ValidationException::withMessages(['date' => 'До закрытия приёма заказов можно составить лист только на сегодня.']);
        }
        $deadline = Carbon::parse($date, 'Europe/Moscow')->subDay()->setTimeFromTimeString($cutoff);
        if ($now->lt($deadline)) {
            throw ValidationException::withMessages(['date' => 'Печать доступна после закрытия приёма заказов.']);
        }
    }

    public function printSheet(Request $request): View
    {
        /** @var array{sheet_id:int|string} $data */
        $data = $request->validate(['sheet_id' => ['required', 'integer', Rule::exists('bakery_delivery_sheets', 'id')]]);
        $sourceId = (int) $data['sheet_id'];
        /** @var object{snapshot:string}|null $source */
        $source = DB::table('bakery_delivery_sheets')->find($sourceId);
        abort_if($source === null, 404);
        /** @var array{date:string,rows:list<array{store_id:int}>} $sheet */
        $sheet = json_decode((string) $source->snapshot, true, 512, JSON_THROW_ON_ERROR);
        $printId = DB::table('bakery_delivery_sheet_prints')->insertGetId([
            'delivery_date' => $sheet['date'], 'source_sheet_id' => $sourceId,
            'snapshot' => json_encode($sheet, JSON_THROW_ON_ERROR), 'printed_at' => now(),
        ]);

        return view('bakery.delivery-sheet', ['sheet' => $sheet, 'autoPrint' => true, 'printId' => $printId]);
    }

    public function printedSheet(int $print): View
    {
        /** @var object{snapshot:string,printed_at:string}|null $row */
        $row = DB::table('bakery_delivery_sheet_prints')->find($print);
        abort_if($row === null, 404);

        return view('bakery.delivery-sheet', [
            'sheet' => json_decode((string) $row->snapshot, true, 512, JSON_THROW_ON_ERROR),
            'archiveRecord' => true, 'printedAt' => $row->printed_at,
        ]);
    }

    public function editSheet(int $sheet): View
    {
        /** @var object{snapshot:string}|null $row */
        $row = DB::table('bakery_delivery_sheets')->find($sheet);
        abort_if($row === null, 404);
        /** @var array{date:string,rows:list<array{store_id:int}>} $snapshot */
        $snapshot = json_decode((string) $row->snapshot, true, 512, JSON_THROW_ON_ERROR);

        return view('bakery.delivery-sheet-edit', [
            'sheetId' => $sheet,
            'sheet' => $snapshot,
            'stores' => $this->orders->stores(),
        ]);
    }

    public function updateSheet(Request $request, int $sheet): RedirectResponse
    {
        abort_unless(DB::table('bakery_delivery_sheets')->where('id', $sheet)->exists(), 404);
        /** @var array{date:string,name:string,driver:string,salary?:string,stores:list<int|string>} $data */
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'string', 'max:255'],
            'salary' => ['nullable', 'regex:/^\d{1,7}([.,]\d{1,2})?$/'],
            'stores' => ['required', 'array', 'min:1'],
            'stores.*' => ['required', 'integer', 'distinct', Rule::exists('bakeries', 'id')],
        ]);
        $this->assertAfterCutoff($data['date']);
        $snapshot = $this->build($data);
        DB::table('bakery_delivery_sheets')->where('id', $sheet)->update([
            'delivery_date' => $data['date'],
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        return redirect()->route('bakery.delivery-sheet', $sheet)->with('success', 'Лист обновлён.');
    }

    public function sheet(int $sheet): View
    {
        /** @var object{snapshot:string}|null $row */
        $row = DB::table('bakery_delivery_sheets')->find($sheet);
        abort_if($row === null, 404);

        /** @var array{date:string,rows:list<array{store_id:int}>} $snapshot */
        $snapshot = json_decode((string) $row->snapshot, true, 512, JSON_THROW_ON_ERROR);

        return view('bakery.delivery-sheet', ['sheet' => $snapshot, 'sheetId' => $sheet]);
    }

    public function deleteSheet(int $sheet): RedirectResponse
    {
        $deleted = DB::table('bakery_delivery_sheets')->where('id', $sheet)->delete();
        abort_if($deleted === 0, 404);

        return redirect()->route('bakery.delivery-routes')->with('success', 'Маршрутный лист удалён.');
    }
}
