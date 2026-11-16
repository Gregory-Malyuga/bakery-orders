<?php

namespace Domains\Bakery\Presentation\Http\Controllers;

use Domains\Bakery\Application\BulkProductPrices;
use Domains\Bakery\Application\Exports\InvoiceWorkbookInterface;
use Domains\Bakery\Application\Exports\PeriodReportWorkbookInterface;
use Domains\Bakery\Application\GroupInvoiceService;
use Domains\Bakery\Application\LabelPlanService;
use Domains\Bakery\Application\OrderCutoff;
use Domains\Bakery\Application\OrderManagementService;
use Domains\Bakery\Application\OrderReturnSummary;
use Domains\Bakery\Application\PeriodReportService;
use Domains\Bakery\Application\ProductionCalculator;
use Domains\Bakery\Application\Repositories\AdminRepositoryInterface;
use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Domains\Bakery\Application\StockPrices;
use Domains\User\Domain\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminWebController
{
    public function __construct(private readonly OrderingRepositoryInterface $orders, private readonly AdminRepositoryInterface $admin, private readonly InvoiceWorkbookInterface $workbook, private readonly LabelPlanService $labelPlans, private readonly ProductionCalculator $production, private readonly OrderManagementService $management, private readonly OrderReturnSummary $returnSummary, private readonly GroupInvoiceService $groupInvoices, private readonly PeriodReportService $periods, private readonly PeriodReportWorkbookInterface $periodWorkbook, private readonly OrderCutoff $cutoff, private readonly StockPrices $stockPrices, private readonly BulkProductPrices $bulkPrices) {}

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
        $productsById = $products->keyBy('id');
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $quantities[$item->product_id][$order->bakery_id] = ($quantities[$item->product_id][$order->bakery_id] ?? 0) + $item->quantity;
                $totals[$item->product_id] = ($totals[$item->product_id] ?? 0) + $item->quantity;
            }
        }
        $plan = $this->labelPlans->forQuantities($quantities, $products, $stores);
        $packaging = $plan['packaging'];
        $labelPackaging = $plan['labelPackaging'];
        $labels = $plan['labels'];
        $labelTotal = $plan['labelTotal'];
        $packageTotal = $plan['packageTotal'];
        $excludedStores = $plan['excludedStores'];
        $productionQuantities = [];
        $productionProductIds = [];
        foreach ($totals as $productId => $quantity) {
            $product = $productsById->get($productId);
            if ($product === null) {
                continue;
            }
            $name = $product->name;
            $productionQuantities[$name] = ($productionQuantities[$name] ?? 0) + $quantity;
            $productionProductIds[$name] = (int) $productId;
        }
        $production = $this->production->calculate($productionQuantities, $productionProductIds);
        $productionTotal = array_sum($totals);
        $orderedStoreIds = $orders->pluck('bakery_id')->all();
        $activeStores = $stores->where('is_active', true);
        $matrixStores = $stores->filter(fn ($store): bool => $store->is_active || in_array($store->id, $orderedStoreIds, true));
        $pageCount = max(1, (int) ceil($orders->count() / 20));
        $page = min((int) ($input['page'] ?? 1), $pageCount);
        $visibleOrders = $orders->forPage($page, 20);
        $orderReturns = $this->returnSummary->forOrders($visibleOrders);
        $groups = $tab === 'orders' ? $this->groupInvoices->forOrders($orders) : [];

        return view('bakery.admin', compact('date', 'tab', 'orders', 'visibleOrders', 'orderReturns', 'groups', 'page', 'pageCount', 'activeStores', 'matrixStores', 'products', 'quantities', 'totals', 'packaging', 'labelPackaging', 'labels', 'labelTotal', 'packageTotal', 'excludedStores', 'productionTotal', 'production', 'orderedStoreIds'));
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
            /** @var list<int> $matchingStoreIds */
            $matchingStoreIds = $stores->filter(fn ($store): bool => mb_stripos($store->name, $search) !== false)->pluck('id')->all();
            $accounts = array_values(array_filter($accounts, fn (array $account): bool => count(array_intersect(array_map('intval', $account['store_ids']), $matchingStoreIds)) > 0));
        }
        $storeOwners = $tab === 'accounts' ? DB::table('bakery_account_stores')->pluck('user_id', 'bakery_id')->all() : [];
        $perPage = $tab === 'accounts' ? 8 : 20;
        $count = $tab === 'accounts' ? count($accounts) : $stores->count();
        $pageCount = max(1, (int) ceil($count / $perPage));
        $page = min((int) ($input['page'] ?? 1), $pageCount);
        $visibleStores = $stores->forPage($page, $perPage);
        $visibleAccounts = array_slice($accounts, ($page - 1) * $perPage, $perPage);
        $unavailableProducts = $selectedStore && $tab === 'prices'
            ? DB::table('bakery_store_product_exclusions')->where('bakery_id', $selectedStore)->pluck('product_id')->mapWithKeys(fn ($id): array => is_numeric($id) ? [(int) $id => true] : [])->all()
            : [];

        return view('bakery.catalog', compact('stores', 'visibleStores', 'products', 'selectedStore', 'accounts', 'visibleAccounts', 'storeOwners', 'unavailableProducts', 'tab', 'page', 'pageCount', 'search') + ['prices' => $selectedStore ? $this->orders->prices($selectedStore, true, false) : [], 'minimums' => $selectedStore ? $this->orders->minimums($selectedStore) : [], 'orderCutoff' => $this->cutoff->time()]);
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
        /** @var array{id?:int|string,name:string,address:string,is_active:bool|int|string,is_cashless?:bool|int|string,discount_percent?:int|string|null} $data */
        $data = $request->validate(['id' => ['nullable', 'integer', 'exists:bakeries,id'], 'name' => ['required', 'string', 'max:255'], 'address' => ['required', 'string', 'max:255'], 'is_active' => ['required', 'boolean'], 'is_cashless' => ['sometimes', 'required', 'boolean'], 'discount_percent' => ['nullable', 'integer', 'min:0', 'max:100']]);
        $changes = ['name' => $data['name'], 'address' => $data['address'], 'is_active' => (bool) $data['is_active'], 'discount_enabled' => (int) ($data['discount_percent'] ?? 0) > 0, 'discount_percent' => (int) ($data['discount_percent'] ?? 0)];
        if (isset($data['is_cashless'])) {
            $changes['is_cashless'] = (bool) $data['is_cashless'];
        }
        $this->admin->saveStore(isset($data['id']) ? (int) $data['id'] : null, $changes);

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
        $this->bulkPrices->single((int) $data['product'], (int) $data['store'], $amount, $this->adminId($request));

        return redirect()->route('bakery.catalog', ['tab' => 'prices', 'store' => $data['store']])->with('success', 'Цена сохранена.');
    }

    public function product(Request $request, int $product): View
    {
        $item = $this->orders->products()->firstWhere('id', $product);
        abort_if($item === null, 404);
        $stores = $this->orders->stores();
        $prices = [];
        $unavailable = DB::table('bakery_store_product_exclusions')->where('product_id', $product)->pluck('bakery_id')->mapWithKeys(fn ($id): array => is_numeric($id) ? [(int) $id => true] : [])->all();
        foreach ($stores as $store) {
            $prices[$store->id] = $this->orders->prices((int) $store->id, true)[$product] ?? null;
        }
        $history = DB::table('bakery_price_operations as o')->join('users as u', 'u.id', '=', 'o.user_id')->where('o.product_id', $product)->orderByDesc('o.id')->select('o.*', 'u.name as author')->limit(100)->get();
        $historyItems = DB::table('bakery_price_operation_items as i')->join('bakeries as b', 'b.id', '=', 'i.bakery_id')->whereIn('i.operation_id', $history->pluck('id'))->select('i.*', 'b.name as store_name')->orderBy('i.bakery_id')->get()->groupBy('operation_id');
        $reversed = DB::table('bakery_price_operations')->whereIn('reverses_id', $history->pluck('id'))->pluck('reverses_id')->all();

        return view('bakery.product', compact('item', 'stores', 'prices', 'unavailable', 'history', 'historyItems', 'reversed'));
    }

    public function bulkPrice(Request $request, int $product): RedirectResponse
    {
        /** @var array{stores:list<int|string>,direction:string,rubles:string,request_key:string} $data */
        $data = $request->validate(['stores' => ['required', 'array', 'min:1'], 'stores.*' => ['required', 'integer', 'distinct', 'exists:bakeries,id'], 'direction' => ['required', 'in:increase,decrease'], 'rubles' => ['required', 'regex:/^\d{1,8}(?:[.,]\d{1,2})?$/'], 'request_key' => ['required', 'uuid']]);
        [$rubles, $kopecks] = array_pad(explode('.', str_replace(',', '.', $data['rubles'])), 2, '0');
        $delta = ((int) $rubles * 100) + (int) str_pad($kopecks, 2, '0');
        if ($delta <= 0) {
            throw ValidationException::withMessages(['rubles' => 'Изменение должно быть больше нуля.']);
        }
        $this->bulkPrices->apply($product, array_map('intval', $data['stores']), $delta, $data['direction'], $data['request_key'], $this->adminId($request));

        return redirect()->route('bakery.product', $product)->with('success', 'Цены изменены.');
    }

    public function reverseBulkPrice(Request $request, int $product, int $operation): RedirectResponse
    {
        /** @var array{reason:string} $data */
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        abort_unless(DB::table('bakery_price_operations')->where('id', $operation)->where('product_id', $product)->exists(), 404);
        $this->bulkPrices->reverse($operation, $data['reason'], $this->adminId($request));

        return redirect()->route('bakery.product', $product)->with('success', 'Изменение отменено.');
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

        $returns = DB::table('bakery_returns')->leftJoin('users as cancelling_users', 'bakery_returns.cancelled_by_user_id', '=', 'cancelling_users.id')
            ->where('bakery_returns.order_id', $order)->orderByDesc('bakery_returns.id')
            ->select('bakery_returns.*', 'cancelling_users.name as cancelled_by_name')->get();
        $adjustments = DB::table('bakery_order_adjustments')->where('order_id', $order)->orderByDesc('id')->get();
        $prices = $this->orders->prices((int) $record->bakery_id);
        $returnProducts = $this->orders->products()->filter(fn ($product): bool => $record->items->contains('product_id', $product->id) || ($product->is_active && isset($prices[$product->id])));

        $returnAmount = $this->returnSummary->forOrders([$record])[$order]['amount'] ?? 0;

        return view('bakery.admin-order', ['order' => $record, 'returns' => $returns, 'adjustments' => $adjustments, 'returnProducts' => $returnProducts, 'returnAmount' => $returnAmount]);
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

    public function cancelReturn(Request $request, int $order, int $return): RedirectResponse
    {
        /** @var array{reason:string} $data */
        $data = $request->validate(['reason' => ['required', 'string', 'max:500', 'not_regex:/^\s*$/u']]);
        $this->management->cancelReturn($order, $return, $this->adminId($request), trim($data['reason']));

        return redirect()->route('bakery.admin-order', $order)->with('success', 'Возврат отменён.');
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

        return view('bakery.period-report', ['from' => $period['from'], 'to' => $period['to'], 'report' => $report, 'stockRows' => $this->stockPrices->dailyRows($period['from'], $period['to'])]);
    }

    public function downloadReport(Request $request): BinaryFileResponse
    {
        $period = $this->period($request);
        $report = $this->periods->build($period['from'], $period['to']);

        return response()->download($this->periodWorkbook->create($report, $this->stockPrices->dailyRows($period['from'], $period['to'])), 'otchet-'.$period['from'].'-'.$period['to'].'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function printOne(int $order): View
    {
        $record = $this->orders->order($order);
        abort_unless($record !== null && $record->status === 'accepted', 404);

        return view('bakery.print', ['orders' => collect([$record]), 'returns' => $this->returnSummary->forOrders([$record]), 'downloadUrl' => route('bakery.xlsx-one', $record->id)]);
    }

    public function printBatch(Request $request): View
    {
        $date = $this->date($request);

        $orders = $this->orders->orders($date);

        return view('bakery.print', ['orders' => $orders, 'returns' => $this->returnSummary->forOrders($orders), 'groups' => $this->groupInvoices->forOrders($orders), 'date' => $date, 'generatedAt' => now('Europe/Moscow'), 'downloadUrl' => route('bakery.xlsx-batch', ['date' => $date])]);
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

        $orders = $this->orders->orders($date);

        return response()->download($this->workbook->create($orders, $this->orders->products(), $this->groupInvoices->forOrders($orders), $date, now('Europe/Moscow')->format('d.m.Y H:i')), "nakladnye-{$date}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
