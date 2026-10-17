<?php

namespace Domains\Bakery\Presentation\Http\Controllers;

use Domains\Bakery\Application\Exports\InvoiceWorkbookInterface;
use Domains\Bakery\Application\OrderCutoff;
use Domains\Bakery\Application\OrderManagementService;
use Domains\Bakery\Application\PackagingPlanner;
use Domains\Bakery\Application\PeriodReportService;
use Domains\Bakery\Application\ProductionCalculator;
use Domains\Bakery\Application\Repositories\AdminRepositoryInterface;
use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Domains\Bakery\Infrastructure\Exports\PeriodReportWorkbook;
use Domains\User\Domain\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminWebController
{
    public function __construct(private readonly OrderingRepositoryInterface $orders, private readonly AdminRepositoryInterface $admin, private readonly InvoiceWorkbookInterface $workbook, private readonly PackagingPlanner $packaging, private readonly ProductionCalculator $production, private readonly OrderManagementService $management, private readonly PeriodReportService $periods, private readonly PeriodReportWorkbook $periodWorkbook, private readonly OrderCutoff $cutoff) {}

    private function adminId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return (int) $user->id;
    }

    private function date(Request $request): string
    {
        /** @var array{date?:string} $input */
        $input = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return $input['date'] ?? now('Europe/Moscow')->addDay()->toDateString();
    }

    public function index(Request $request): View
    {
        $date = $this->date($request);
        /** @var array{tab?:string,page?:int|string} $input */
        $input = $request->validate(['tab' => ['nullable', 'in:orders,production,packaging,labels,prep'], 'page' => ['nullable', 'integer', 'min:1']]);
        $tab = $input['tab'] ?? 'orders';
        $orders = $this->orders->orders($date);
        $stores = $this->orders->stores();
        $products = $this->orders->products();
        $quantities = [];
        $totals = [];
        $packaging = [];
        $productsById = $products->keyBy('id');
        $storesById = $stores->keyBy('id');
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $quantities[$item->product_id][$order->bakery_id] = ($quantities[$item->product_id][$order->bakery_id] ?? 0) + $item->quantity;
                $totals[$item->product_id] = ($totals[$item->product_id] ?? 0) + $item->quantity;
            }
        }
        foreach ($quantities as $productId => $byStore) {
            $product = $productsById->get($productId);
            if ($product === null) {
                continue;
            }
            $packaging[$productId] = ['one' => 0, 'two' => 0, 'three' => 0, 'five' => 0, 'loose' => 0];
            foreach ($byStore as $storeId => $quantity) {
                $store = $storesById->get($storeId);
                if ($store === null) {
                    continue;
                }
                $plan = $this->packaging->plan($quantity, $product->packaging_mode);
                foreach ($plan as $size => $count) {
                    $packaging[$productId][$size] += $count;
                }
            }
        }
        $productionQuantities = [];
        foreach ($totals as $productId => $quantity) {
            $product = $productsById->get($productId);
            if ($product === null) {
                continue;
            }
            $name = $product->name;
            $productionQuantities[$name] = ($productionQuantities[$name] ?? 0) + $quantity;
        }
        $production = $this->production->calculate($productionQuantities);
        $labels = [];
        foreach ($packaging as $productId => $plan) {
            $labels[$productId] = $plan['one'] + $plan['two'] + $plan['three'] + $plan['five'];
        }
        $labelTotal = array_sum($labels);
        $productionTotal = array_sum($totals);
        $orderedStoreIds = $orders->pluck('bakery_id')->all();
        $activeStores = $stores->where('is_active', true);
        $matrixStores = $stores->filter(fn ($store): bool => $store->is_active || in_array($store->id, $orderedStoreIds, true));
        $pageCount = max(1, (int) ceil($orders->count() / 20));
        $page = min((int) ($input['page'] ?? 1), $pageCount);
        $visibleOrders = $orders->forPage($page, 20);

        return view('bakery.admin', compact('date', 'tab', 'orders', 'visibleOrders', 'page', 'pageCount', 'activeStores', 'matrixStores', 'products', 'quantities', 'totals', 'packaging', 'labels', 'labelTotal', 'productionTotal', 'production', 'orderedStoreIds'));
    }

    public function catalog(Request $request): View
    {
        $stores = $this->orders->stores();
        $products = $this->orders->products();
        /** @var array{store?:int|string,tab?:string,page?:int|string,search?:string} $input */
        $input = $request->validate(['store' => ['nullable', 'integer'], 'tab' => ['nullable', 'in:stores,products,prices,accounts,settings'], 'page' => ['nullable', 'integer', 'min:1'], 'search' => ['nullable', 'string', 'max:255']]);
        $tab = $input['tab'] ?? 'stores';
        $selectedStore = (int) ($input['store'] ?? $stores->first()?->id);
        abort_if($selectedStore && $this->orders->store($selectedStore) === null, 404);
        $accounts = $this->admin->accounts();
        $search = trim($input['search'] ?? '');
        if ($tab === 'accounts' && $search !== '') {
            $matchingStoreIds = $stores->filter(fn ($store): bool => mb_stripos($store->name, $search) !== false)->pluck('id')->all();
            $accounts = array_values(array_filter($accounts, fn (array $account): bool => count(array_intersect($account['store_ids'], $matchingStoreIds)) > 0));
        }
        $storeOwners = $tab === 'accounts' ? DB::table('bakery_account_stores')->pluck('user_id', 'bakery_id')->all() : [];
        $perPage = $tab === 'accounts' ? 8 : 20;
        $count = $tab === 'accounts' ? count($accounts) : $stores->count();
        $pageCount = max(1, (int) ceil($count / $perPage));
        $page = min((int) ($input['page'] ?? 1), $pageCount);
        $visibleStores = $stores->forPage($page, $perPage);
        $visibleAccounts = array_slice($accounts, ($page - 1) * $perPage, $perPage);
        $unavailableProducts = $selectedStore && $tab === 'prices'
            ? DB::table('bakery_store_product_exclusions')->where('bakery_id', $selectedStore)->pluck('product_id')->mapWithKeys(fn ($id): array => [(int) $id => true])->all()
            : [];

        return view('bakery.catalog', compact('stores', 'visibleStores', 'products', 'selectedStore', 'accounts', 'visibleAccounts', 'storeOwners', 'unavailableProducts', 'tab', 'page', 'pageCount', 'search') + ['prices' => $selectedStore ? $this->orders->prices($selectedStore, true) : [], 'minimums' => $selectedStore ? $this->orders->minimums($selectedStore) : [], 'orderCutoff' => $this->cutoff->time()]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        /** @var array{order_cutoff:string} $data */
        $data = $request->validate(['order_cutoff' => ['required', 'date_format:H:i']]);
        DB::table('bakery_settings')->updateOrInsert(['key' => 'order_cutoff'], ['value' => $data['order_cutoff']]);

        return redirect()->route('bakery.catalog', ['tab' => 'settings'])->with('success', 'Время последнего заказа сохранено.');
    }

    public function saveStore(Request $request): RedirectResponse
    {
        /** @var array{id?:int|string,name:string,address:string,is_active:bool|int|string} $data */
        $data = $request->validate(['id' => ['nullable', 'integer', 'exists:bakeries,id'], 'name' => ['required', 'string', 'max:255'], 'address' => ['required', 'string', 'max:255'], 'is_active' => ['required', 'boolean']]);
        $this->admin->saveStore(isset($data['id']) ? (int) $data['id'] : null, ['name' => $data['name'], 'address' => $data['address'], 'is_active' => (bool) $data['is_active']]);

        return back()->with('success', 'Магазин сохранён.');
    }

    public function saveProduct(Request $request): RedirectResponse
    {
        /** @var array{id?:int|string,name:string,sort_order:int|string,minimum_quantity:int|string,packaging_mode?:string,is_active:bool|int|string} $data */
        $data = $request->validate(['id' => ['nullable', 'integer', 'exists:bakery_products,id'], 'name' => ['required', 'string', 'max:255'], 'sort_order' => ['required', 'integer', 'min:0'], 'minimum_quantity' => ['required', 'integer', 'min:1', 'max:100000'], 'packaging_mode' => ['nullable', 'in:single,pair,mixed'], 'is_active' => ['required', 'boolean']]);
        $mode = $data['packaging_mode'] ?? (isset($data['id']) ? (string) $this->orders->products()->firstWhere('id', (int) $data['id'])?->packaging_mode : 'mixed');
        $packageMinimum = $mode === 'mixed' ? 3 : ($mode === 'pair' ? 2 : 1);
        $changes = ['name' => $data['name'], 'sort_order' => (int) $data['sort_order'], 'minimum_quantity' => max((int) $data['minimum_quantity'], $packageMinimum), 'is_active' => (bool) $data['is_active']];
        if (isset($data['packaging_mode'])) {
            $changes['packaging_mode'] = $data['packaging_mode'];
        }
        DB::transaction(function () use ($data, $changes, $packageMinimum): void {
            $this->admin->saveProduct(isset($data['id']) ? (int) $data['id'] : null, $changes);
            if (isset($data['id'])) {
                DB::table('bakery_store_product_minimums')->where('product_id', (int) $data['id'])->where('minimum_quantity', '<', $packageMinimum)->update(['minimum_quantity' => $packageMinimum]);
            }
        });

        return back()->with('success', 'Товар сохранён.');
    }

    public function savePrice(Request $request): RedirectResponse
    {
        /** @var array{store:int|string,product:int|string,rubles:string} $data */
        $data = $request->validate(['store' => ['required', 'integer', 'exists:bakeries,id'], 'product' => ['required', 'integer', 'exists:bakery_products,id'], 'rubles' => ['required', 'regex:/^\d{1,8}(?:[.,]\d{1,2})?$/']]);
        [$rubles, $kopecks] = array_pad(explode('.', str_replace(',', '.', $data['rubles'])), 2, '0');
        $amount = ((int) $rubles * 100) + (int) str_pad($kopecks, 2, '0');
        $this->admin->savePrice((int) $data['store'], (int) $data['product'], $amount);

        return redirect()->route('bakery.catalog', ['tab' => 'prices', 'store' => $data['store']])->with('success', 'Цена сохранена.');
    }

    public function saveMinimum(Request $request): RedirectResponse
    {
        /** @var array{store:int|string,product:int|string,minimum_quantity?:int|string|null} $data */
        $data = $request->validate(['store' => ['required', 'integer', 'exists:bakeries,id'], 'product' => ['required', 'integer', 'exists:bakery_products,id'], 'minimum_quantity' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $key = ['bakery_id' => (int) $data['store'], 'product_id' => (int) $data['product']];
        if (! isset($data['minimum_quantity'])) {
            DB::table('bakery_store_product_minimums')->where($key)->delete();
        } else {
            $mode = (string) $this->orders->products()->firstWhere('id', (int) $data['product'])?->packaging_mode;
            $packageMinimum = $mode === 'mixed' ? 3 : ($mode === 'pair' ? 2 : 1);
            DB::table('bakery_store_product_minimums')->updateOrInsert($key, ['minimum_quantity' => max((int) $data['minimum_quantity'], $packageMinimum)]);
        }

        return redirect()->route('bakery.catalog', ['tab' => 'prices', 'store' => $data['store']])->with('success', 'Минимум для магазина сохранён.');
    }

    public function saveAvailability(Request $request): RedirectResponse
    {
        /** @var array{store:int|string,product:int|string,is_available:bool|int|string} $data */
        $data = $request->validate(['store' => ['required', 'integer', 'exists:bakeries,id'], 'product' => ['required', 'integer', 'exists:bakery_products,id'], 'is_available' => ['required', 'boolean']]);
        $storeId = (int) $data['store'];
        $productId = (int) $data['product'];
        $key = ['bakery_id' => $storeId, 'product_id' => $productId];
        if ((bool) $data['is_available']) {
            abort_unless(isset($this->orders->prices($storeId, true)[$productId]), 422, 'Сначала назначьте цену товара.');
            DB::table('bakery_store_product_exclusions')->where($key)->delete();
        } else {
            DB::table('bakery_store_product_exclusions')->updateOrInsert($key);
        }

        return redirect()->route('bakery.catalog', ['tab' => 'prices', 'store' => $storeId])->with('success', 'Ассортимент магазина сохранён.');
    }

    public function saveAccount(Request $request): RedirectResponse
    {
        /** @var array{id?:int|string,stores?:list<int|string>,name:string,email:string,password?:string,is_active:bool|int|string} $data */
        $data = $request->validate(['id' => ['nullable', 'integer', 'exists:users,id'], 'stores' => ['sometimes', 'array'], 'stores.*' => ['required', 'integer', 'distinct', 'exists:bakeries,id'], 'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email,'.(is_scalar($request->input('id')) ? $request->input('id') : 'NULL')], 'password' => ['nullable', 'string', 'min:10'], 'is_active' => ['required', 'boolean']]);
        $this->admin->saveAccount(isset($data['id']) ? (int) $data['id'] : null, array_map('intval', $data['stores'] ?? []), $data['name'], $data['email'], $data['password'] ?? null, (bool) $data['is_active']);

        return back()->with('success', 'Аккаунт сохранён.');
    }

    public function order(int $order): View
    {
        $record = $this->orders->order($order);
        abort_if($record === null, 404);

        $returns = DB::table('bakery_returns')->where('order_id', $order)->orderByDesc('id')->get();
        $adjustments = DB::table('bakery_order_adjustments')->where('order_id', $order)->orderByDesc('id')->get();

        return view('bakery.admin-order', ['order' => $record, 'returns' => $returns, 'adjustments' => $adjustments]);
    }

    public function editOrder(int $order): View
    {
        $record = $this->orders->order($order);
        abort_unless($record !== null && $record->status === 'accepted', 404);
        $prices = $this->orders->prices((int) $record->bakery_id);
        $products = $this->orders->products()->filter(fn ($product): bool => $record->items->contains('product_id', $product->id) || ($product->is_active && isset($prices[$product->id])));

        return view('bakery.edit-order', ['order' => $record, 'products' => $products, 'prices' => $prices]);
    }

    public function updateOrder(Request $request, int $order): RedirectResponse
    {
        /** @var array{quantities:array<int|string,int|string>,reason:string,admin_note?:string} $data */
        $data = $request->validate(['quantities' => ['required', 'array'], 'quantities.*' => ['required', 'integer', 'min:0', 'max:100000'], 'reason' => ['required', 'string', 'max:500'], 'admin_note' => ['nullable', 'string', 'max:1000']]);
        $this->management->adjust($order, $this->adminId($request), $data['quantities'], trim($data['reason']), isset($data['admin_note']) ? trim($data['admin_note']) : null);

        return redirect()->route('bakery.admin-order', $order)->with('success', 'Заявка скорректирована.');
    }

    public function recordReturn(Request $request, int $order): RedirectResponse
    {
        /** @var array{product:int|string,quantity:int|string,reason?:string} $data */
        $data = $request->validate(['product' => ['required', 'integer', 'exists:bakery_products,id'], 'quantity' => ['required', 'integer', 'min:1', 'max:100000'], 'reason' => ['nullable', 'string', 'max:500']]);
        $this->management->recordReturn($order, $this->adminId($request), (int) $data['product'], (int) $data['quantity'], isset($data['reason']) ? trim($data['reason']) : null);

        return back()->with('success', 'Возврат зафиксирован.');
    }

    /** @return array{from:string,to:string} */
    private function period(Request $request): array
    {
        /** @var array{from?:string,to?:string} $input */
        $input = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $today = now('Europe/Moscow');
        $from = $input['from'] ?? $today->copy()->startOfMonth()->toDateString();
        $to = $input['to'] ?? $today->toDateString();
        abort_if($from > $to, 422, 'Начало периода позже конца.');

        return ['from' => $from, 'to' => $to];
    }

    public function report(Request $request): View
    {
        $period = $this->period($request);
        $report = $this->periods->build($period['from'], $period['to']);

        return view('bakery.period-report', ['from' => $period['from'], 'to' => $period['to'], 'report' => $report]);
    }

    public function downloadReport(Request $request): BinaryFileResponse
    {
        $period = $this->period($request);
        $report = $this->periods->build($period['from'], $period['to']);

        return response()->download($this->periodWorkbook->create($report), 'otchet-'.$period['from'].'-'.$period['to'].'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function printOne(int $order): View
    {
        $record = $this->orders->order($order);
        abort_unless($record !== null && $record->status === 'accepted', 404);

        return view('bakery.print', ['orders' => collect([$record]), 'returns' => $this->returnsForOrders([$record->id]), 'downloadUrl' => route('bakery.xlsx-one', $record->id)]);
    }

    public function printBatch(Request $request): View
    {
        $date = $this->date($request);

        $orders = $this->orders->orders($date);

        return view('bakery.print', ['orders' => $orders, 'returns' => $this->returnsForOrders($orders->pluck('id')->all()), 'downloadUrl' => route('bakery.xlsx-batch', ['date' => $date])]);
    }

    /** @param list<int> $ids
     * @return array<int, array<int, int>>
     */
    private function returnsForOrders(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $result = [];
        foreach (DB::table('bakery_returns')->select('order_id', 'product_id')->selectRaw('SUM(quantity) AS quantity')->whereIn('order_id', $ids)->groupBy('order_id', 'product_id')->get() as $row) {
            $result[$row->order_id][$row->product_id] = (int) $row->quantity;
        }

        return $result;
    }

    public function downloadOne(int $order): BinaryFileResponse
    {
        $record = $this->orders->order($order);
        abort_unless($record !== null && $record->status === 'accepted', 404);

        return response()->download($this->workbook->create([$record], $this->orders->products()), "nakladnaya-{$record->id}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function downloadBatch(Request $request): BinaryFileResponse
    {
        $date = $this->date($request);

        return response()->download($this->workbook->create($this->orders->orders($date), $this->orders->products()), "nakladnye-{$date}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
