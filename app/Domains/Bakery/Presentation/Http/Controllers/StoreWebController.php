<?php

namespace Domains\Bakery\Presentation\Http\Controllers;

use Domains\Bakery\Application\OrderingService;
use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Domains\User\Domain\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StoreWebController
{
    public function __construct(private readonly OrderingService $service, private readonly OrderingRepositoryInterface $repository) {}

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function storeId(Request $request): int
    {
        /** @var array{store?:int|string|null} $input */
        $input = $request->validate(['store' => ['nullable', 'integer']]);
        $accessibleStoreIds = $this->user($request)->accessibleStoreIds();
        $id = (int) ($input['store'] ?? ($accessibleStoreIds[0] ?? 0));
        abort_unless(in_array($id, $accessibleStoreIds, true), 403);

        return $id;
    }

    public function index(Request $request): View
    {
        if ($this->user($request)->accessibleStoreIds() === []) {
            abort_if($request->has('store'), 403);
            return view('bakery.no-stores');
        }
        $id = $this->storeId($request);
        $store = $this->repository->store($id);
        abort_unless($store !== null && $store->is_active, 403);

        return view('bakery.store', [
            'store' => $store,
            'accessibleStores' => $this->repository->stores()->whereIn('id', $this->user($request)->accessibleStoreIds())->where('is_active', true),
            'date' => $this->service->deliveryDate(),
            'open' => $this->service->isOpen(),
            'products' => $this->repository->products()->where('is_active', true),
            'prices' => $this->repository->prices($id),
            'minimums' => $this->repository->minimums($id),
            'activeOrder' => $this->repository->activeOrder($id, $this->service->deliveryDate()),
            'history' => $this->repository->history($id),
            'requestKey' => (string) Str::uuid(),
            'repeatQuantities' => [],
        ]);
    }

    public function repeat(Request $request, int $order): View
    {
        $source = $this->repository->order($order);
        abort_unless($source !== null && in_array((int) $source->bakery_id, $this->user($request)->accessibleStoreIds(), true), 404);
        abort_unless($this->service->isOpen(), 422, 'Приём заказов закрыт.');
        abort_if($this->repository->activeOrder((int) $source->bakery_id, $this->service->deliveryDate()) !== null, 409, 'Заказ на завтра уже оформлен.');
        $store = $this->repository->store((int) $source->bakery_id);
        abort_unless($store !== null && $store->is_active, 403);
        $prices = $this->repository->prices((int) $store->id);
        $products = $this->repository->products()->where('is_active', true);
        $repeatQuantities = [];
        foreach ($source->items as $item) {
            if ($products->contains('id', $item->product_id) && isset($prices[$item->product_id])) {
                $repeatQuantities[$item->product_id] = $item->quantity;
            }
        }

        return view('bakery.store', [
            'store' => $store,
            'accessibleStores' => $this->repository->stores()->whereIn('id', $this->user($request)->accessibleStoreIds())->where('is_active', true),
            'date' => $this->service->deliveryDate(),
            'open' => true,
            'products' => $products,
            'prices' => $prices,
            'minimums' => $this->repository->minimums((int) $store->id),
            'activeOrder' => null,
            'history' => $this->repository->history((int) $store->id),
            'requestKey' => (string) Str::uuid(),
            'repeatQuantities' => $repeatQuantities,
        ]);
    }

    public function preview(Request $request): View
    {
        abort_unless($this->service->isOpen(), 422, 'Приём заказов закрыт.');
        /** @var array{quantities:array<int|string,int|string>,request_key:string,comment?:string} $input */
        $input = $request->validate(['quantities' => ['required', 'array'], 'quantities.*' => ['required', 'integer', 'min:0', 'max:100000'], 'request_key' => ['required', 'uuid'], 'comment' => ['nullable', 'string', 'max:1000']]);
        $id = $this->storeId($request);
        abort_if($this->repository->activeOrder($id, $this->service->deliveryDate()) !== null, 409);
        $store = $this->repository->store($id);
        abort_unless($store !== null && $store->is_active, 403);

        return view('bakery.confirm', ['store' => $store, 'quote' => $this->service->quote($id, $input['quantities']), 'quantities' => $input['quantities'], 'requestKey' => $input['request_key'], 'comment' => trim($input['comment'] ?? ''), 'changed' => false]);
    }

    public function place(Request $request): RedirectResponse|View
    {
        /** @var array{quantities:array<int|string,int|string>,request_key:string,quote_hash:string,comment?:string} $input */
        $input = $request->validate(['quantities' => ['required', 'array'], 'quantities.*' => ['required', 'integer', 'min:0', 'max:100000'], 'request_key' => ['required', 'uuid'], 'quote_hash' => ['required', 'string', 'size:64'], 'comment' => ['nullable', 'string', 'max:1000']]);
        $id = $this->storeId($request);
        $result = $this->service->place($id, $input['request_key'], $input['quantities'], $input['quote_hash'], trim($input['comment'] ?? '') ?: null);
        if ($result['order'] !== null) {
            return redirect()->route('bakery.order', $result['order']->id)->with('success', 'Заказ принят.');
        }

        return view('bakery.confirm', ['store' => $this->repository->store($id), 'quote' => $result['quote'], 'quantities' => $input['quantities'], 'requestKey' => $input['request_key'], 'comment' => trim($input['comment'] ?? ''), 'changed' => true]);
    }

    public function show(Request $request, int $order): View
    {
        $record = $this->repository->order($order);
        abort_unless($record !== null && in_array($record->bakery_id, $this->user($request)->accessibleStoreIds(), true), 404);

        return view('bakery.order', [
            'order' => $record,
            'canCancel' => $record->status === 'accepted' && $this->service->isOpen() && $record->created_at?->timezone('Europe/Moscow')->toDateString() === now('Europe/Moscow')->toDateString(),
            'canRepeat' => $this->service->isOpen() && $this->repository->activeOrder((int) $record->bakery_id, $this->service->deliveryDate()) === null,
        ]);
    }

    public function cancel(Request $request, int $order): RedirectResponse
    {
        $record = $this->repository->order($order);
        abort_unless($record !== null && in_array($record->bakery_id, $this->user($request)->accessibleStoreIds(), true), 404);
        $this->service->cancel($record->bakery_id, $order);

        return redirect()->route('bakery.order', $order)->with('success', 'Заказ отменён.');
    }
}
