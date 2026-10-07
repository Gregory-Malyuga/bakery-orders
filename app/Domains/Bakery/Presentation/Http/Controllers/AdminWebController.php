<?php

namespace Domains\Bakery\Presentation\Http\Controllers;

use Domains\Bakery\Application\Exports\InvoiceWorkbookInterface;
use Domains\Bakery\Application\Repositories\AdminRepositoryInterface;
use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminWebController
{
    public function __construct(private readonly OrderingRepositoryInterface $orders, private readonly AdminRepositoryInterface $admin, private readonly InvoiceWorkbookInterface $workbook) {}

    private function date(Request $request): string
    {
        /** @var array{date?:string} $input */
        $input = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return $input['date'] ?? now('Europe/Moscow')->addDay()->toDateString();
    }

    public function index(Request $request): View
    {
        $date = $this->date($request);
        $orders = $this->orders->orders($date);
        $stores = $this->orders->stores();
        $products = $this->orders->products();
        $quantities = [];
        $totals = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $quantities[$item->product_id][$order->bakery_id] = ($quantities[$item->product_id][$order->bakery_id] ?? 0) + $item->quantity;
                $totals[$item->product_id] = ($totals[$item->product_id] ?? 0) + $item->quantity;
            }
        }
        $orderedStoreIds = $orders->pluck('bakery_id')->all();
        $activeStores = $stores->where('is_active', true);
        $matrixStores = $stores->filter(fn ($store): bool => $store->is_active || in_array($store->id, $orderedStoreIds, true));

        return view('bakery.admin', compact('date', 'orders', 'activeStores', 'matrixStores', 'products', 'quantities', 'totals', 'orderedStoreIds'));
    }

    public function catalog(Request $request): View
    {
        $stores = $this->orders->stores();
        $products = $this->orders->products();
        /** @var array{store?:int|string} $input */
        $input = $request->validate(['store' => ['nullable', 'integer']]);
        $selectedStore = (int) ($input['store'] ?? $stores->first()?->id);
        abort_if($selectedStore && $this->orders->store($selectedStore) === null, 404);

        return view('bakery.catalog', ['stores' => $stores, 'products' => $products, 'selectedStore' => $selectedStore, 'prices' => $selectedStore ? $this->orders->prices($selectedStore) : [], 'accounts' => $this->admin->accounts()]);
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
        /** @var array{id?:int|string,name:string,sort_order:int|string,minimum_quantity:int|string,is_active:bool|int|string} $data */
        $data = $request->validate(['id' => ['nullable', 'integer', 'exists:bakery_products,id'], 'name' => ['required', 'string', 'max:255'], 'sort_order' => ['required', 'integer', 'min:0'], 'minimum_quantity' => ['required', 'integer', 'min:1', 'max:100000'], 'is_active' => ['required', 'boolean']]);
        $this->admin->saveProduct(isset($data['id']) ? (int) $data['id'] : null, ['name' => $data['name'], 'sort_order' => (int) $data['sort_order'], 'minimum_quantity' => (int) $data['minimum_quantity'], 'is_active' => (bool) $data['is_active']]);

        return back()->with('success', 'Товар сохранён.');
    }

    public function savePrice(Request $request): RedirectResponse
    {
        /** @var array{store:int|string,product:int|string,rubles:string} $data */
        $data = $request->validate(['store' => ['required', 'integer', 'exists:bakeries,id'], 'product' => ['required', 'integer', 'exists:bakery_products,id'], 'rubles' => ['required', 'regex:/^\d{1,8}(?:[.,]\d{1,2})?$/']]);
        [$rubles, $kopecks] = array_pad(explode('.', str_replace(',', '.', $data['rubles'])), 2, '0');
        $amount = ((int) $rubles * 100) + (int) str_pad($kopecks, 2, '0');
        $this->admin->savePrice((int) $data['store'], (int) $data['product'], $amount);

        return redirect()->route('bakery.catalog', ['store' => $data['store']])->with('success', 'Цена сохранена.');
    }

    public function saveAccount(Request $request): RedirectResponse
    {
        /** @var array{id?:int|string,store:int|string,name:string,email:string,password?:string,is_active:bool|int|string} $data */
        $data = $request->validate(['id' => ['nullable', 'integer', 'exists:users,id'], 'store' => ['required', 'integer', 'exists:bakeries,id'], 'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email,'.(is_scalar($request->input('id')) ? $request->input('id') : 'NULL')], 'password' => ['nullable', 'string', 'min:10'], 'is_active' => ['required', 'boolean']]);
        $this->admin->saveAccount(isset($data['id']) ? (int) $data['id'] : null, (int) $data['store'], $data['name'], $data['email'], $data['password'] ?? null, (bool) $data['is_active']);

        return back()->with('success', 'Аккаунт сохранён.');
    }

    public function order(int $order): View
    {
        $record = $this->orders->order($order);
        abort_if($record === null, 404);

        return view('bakery.admin-order', ['order' => $record]);
    }

    public function printOne(int $order): View
    {
        $record = $this->orders->order($order);
        abort_unless($record !== null && $record->status === 'accepted', 404);

        return view('bakery.print', ['orders' => collect([$record])]);
    }

    public function printBatch(Request $request): View
    {
        $date = $this->date($request);

        return view('bakery.print', ['orders' => $this->orders->orders($date)]);
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
