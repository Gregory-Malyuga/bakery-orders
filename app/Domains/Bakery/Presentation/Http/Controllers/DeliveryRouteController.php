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
        /** @var array{date?:string} $input */
        $input = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $input['date'] ?? now('Europe/Moscow')->addDay()->toDateString();

        return view('bakery.delivery-routes', [
            'date' => $date,
            'stores' => $this->orders->stores()->where('is_active', true),
            'routes' => DB::table('bakery_delivery_routes')->orderBy('name')->get(),
            'sheets' => DB::table('bakery_delivery_sheets')->orderByDesc('id')->limit(100)->get(),
        ]);
    }

    public function saveRoute(Request $request): RedirectResponse
    {
        /** @var array{id?:int|string,name:string,driver:string,stores:list<int|string>} $data */
        $data = $request->validate([
            'id' => ['nullable', 'integer', Rule::exists('bakery_delivery_routes', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'string', 'max:255'],
            'stores' => ['required', 'array', 'min:1'],
            'stores.*' => ['required', 'integer', 'distinct', Rule::exists('bakeries', 'id')->where('is_active', true)],
        ]);
        $values = ['name' => trim($data['name']), 'driver' => trim($data['driver']), 'store_ids' => json_encode(array_map('intval', $data['stores']), JSON_THROW_ON_ERROR), 'updated_at' => now()];
        if (isset($data['id'])) {
            DB::table('bakery_delivery_routes')->where('id', $data['id'])->update($values);
        } else {
            DB::table('bakery_delivery_routes')->insert($values + ['created_at' => now()]);
        }

        return redirect()->route('bakery.delivery-routes')->with('success', 'Маршрут сохранён.');
    }

    /**
     * @param  array{date:string,route_id?:int|string,driver?:string,stores?:list<int|string>,save?:string,confirm_overlap?:string}  $data
     * @return array{date:string,driver:string,route:string,closed:bool,rows:list<array{store_id:int,name:string,address:string,order:int,returns:int,due:int,has_order:bool}>,order:int,returns:int,due:int}
     */
    private function build(array $data): array
    {
        $date = $data['date'];
        /** @var object{store_ids:string,driver:string,name:string}|null $route */
        $route = isset($data['route_id']) ? DB::table('bakery_delivery_routes')->find((int) $data['route_id']) : null;
        /** @var list<int> $ids */
        $ids = $route ? json_decode($route->store_ids, true, 512, JSON_THROW_ON_ERROR) : array_map(static fn (int|string $id): int => (int) $id, $data['stores'] ?? []);
        $driver = $route ? (string) $route->driver : trim((string) ($data['driver'] ?? ''));
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
        $other = DB::table('bakery_delivery_sheets')->where('delivery_date', $date)->get();
        foreach ($other as $sheet) {
            /** @var object{snapshot:string} $sheet */
            /** @var array{rows:list<array{store_id:int}>} $snapshot */
            $snapshot = json_decode((string) $sheet->snapshot, true, 512, JSON_THROW_ON_ERROR);
            foreach ($snapshot['rows'] as $row) {
                if (in_array($row['store_id'], $ids, true) && ! isset($data['confirm_overlap'])) {
                    throw ValidationException::withMessages(['stores' => 'Точка уже есть в сохранённом листе на эту дату. Проверьте маршруты водителей.']);
                }
            }
        }

        return ['date' => $date, 'driver' => $driver, 'route' => $route ? (string) $route->name : 'Разовый маршрут', 'closed' => DB::table('bakery_closed_delivery_dates')->where('delivery_date', $date)->exists(), 'rows' => $rows, 'order' => array_sum(array_column($rows, 'order')), 'returns' => array_sum(array_column($rows, 'returns')), 'due' => array_sum(array_column($rows, 'due'))];
    }

    public function createSheet(Request $request): View|RedirectResponse
    {
        /** @var array{date:string,route_id?:int|string,driver?:string,stores?:list<int|string>,save?:string,confirm_overlap?:string} $data */
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'route_id' => ['nullable', 'integer', Rule::exists('bakery_delivery_routes', 'id')],
            'driver' => ['required_without:route_id', 'nullable', 'string', 'max:255'],
            'stores' => ['required_without:route_id', 'array'],
            'stores.*' => ['integer', 'distinct', Rule::exists('bakeries', 'id')],
            'save' => ['nullable', 'in:1'],
            'confirm_overlap' => ['nullable', 'in:1'],
        ]);
        $cutoffValue = DB::table('bakery_settings')->where('key', 'order_cutoff')->value('value');
        $cutoff = is_string($cutoffValue) ? $cutoffValue : '18:30';
        $deadline = Carbon::parse($data['date'], 'Europe/Moscow')->subDay()->setTimeFromTimeString($cutoff ?: '18:30');
        if (now('Europe/Moscow')->lt($deadline)) {
            throw ValidationException::withMessages(['date' => 'Печать доступна после закрытия приёма заказов.']);
        }
        $sheet = $this->build($data);
        if (isset($data['save'])) {
            $id = DB::table('bakery_delivery_sheets')->insertGetId(['delivery_date' => $data['date'], 'snapshot' => json_encode($sheet, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);

            return redirect()->route('bakery.delivery-sheet', $id);
        }

        return view('bakery.delivery-sheet', ['sheet' => $sheet]);
    }

    public function sheet(int $sheet): View
    {
        /** @var object{snapshot:string}|null $row */
        $row = DB::table('bakery_delivery_sheets')->find($sheet);
        abort_if($row === null, 404);

        return view('bakery.delivery-sheet', ['sheet' => json_decode((string) $row->snapshot, true, 512, JSON_THROW_ON_ERROR)]);
    }
}
