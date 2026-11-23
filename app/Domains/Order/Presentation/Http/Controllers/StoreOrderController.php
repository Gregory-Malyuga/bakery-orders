<?php

namespace Domains\Order\Presentation\Http\Controllers;

use Domains\Order\Application\Data\StoreOrderHomeData;
use Domains\Order\Application\Data\StoreOrderShowData;
use Domains\Order\Application\OrderingService;
use Domains\Order\Application\Queries\Home\StoreOrderHomeHandler;
use Domains\Order\Application\Queries\Home\StoreOrderHomeQuery;
use Domains\Order\Application\Queries\Repeat\StoreOrderRepeatHandler;
use Domains\Order\Application\Queries\Repeat\StoreOrderRepeatQuery;
use Domains\Order\Application\Queries\Show\StoreOrderShowHandler;
use Domains\Order\Application\Queries\Show\StoreOrderShowQuery;
use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Domains\User\Domain\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreOrderController
{
    public function __construct(private readonly OrderingService $service, private readonly OrderRepositoryInterface $repository,
        private readonly StoreOrderHomeHandler $home,
        private readonly StoreOrderRepeatHandler $repeat,
        private readonly StoreOrderShowHandler $show) {}

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
        $accessible = $this->user($request)->accessibleStoreIds();
        if ($accessible === []) {
            abort_if($request->has('store'), 403);

            return view('bakery.no-stores');
        }

        return $this->renderHome($this->home->handle(new StoreOrderHomeQuery($this->storeId($request), $accessible)));
    }

    public function repeat(Request $request, int $order): View
    {
        return $this->renderHome($this->repeat->handle(new StoreOrderRepeatQuery($order, $this->user($request)->accessibleStoreIds())));
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

    public function editPreview(Request $request): View
    {
        abort_unless($this->service->isOpen(), 422, 'Приём заказов закрыт.');
        /** @var array{quantities:array<int|string,int|string>,request_key:string,comment?:string} $input */
        $input = $request->validate(['store' => ['required', 'integer'], 'quantities' => ['required', 'array'], 'quantities.*' => ['required', 'integer', 'min:0', 'max:100000'], 'request_key' => ['required', 'uuid'], 'comment' => ['nullable', 'string', 'max:1000']]);
        $storeId = $this->storeId($request);
        abort_if($this->repository->activeOrder($storeId, $this->service->deliveryDate()) !== null, 409);

        return $this->index($request)->with([
            'repeatQuantities' => $input['quantities'],
            'commentDraft' => $input['comment'] ?? '',
            'requestKey' => $input['request_key'],
        ]);
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
        return $this->renderShow($this->show->handle(new StoreOrderShowQuery($order, $this->user($request)->accessibleStoreIds())));
    }

    private function renderHome(StoreOrderHomeData $data): View
    {
        return view('bakery.store', $data->viewData());
    }

    private function renderShow(StoreOrderShowData $data): View
    {
        return view('bakery.order', $data->viewData());
    }

    public function cancel(Request $request, int $order): RedirectResponse
    {
        $record = $this->repository->order($order);
        abort_unless($record !== null && in_array($record->bakery_id, $this->user($request)->accessibleStoreIds(), true), 404);
        $this->service->cancel($record->bakery_id, $order);

        return redirect()->route('bakery.order', $order)->with('success', 'Заказ отменён.');
    }
}
