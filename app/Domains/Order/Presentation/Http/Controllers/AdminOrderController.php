<?php

namespace Domains\Order\Presentation\Http\Controllers;

use Domains\Order\Application\Data\AdminOrderDetailData;
use Domains\Order\Application\Data\AdminOrderEditData;
use Domains\Order\Application\Queries\Detail\AdminOrderDetailHandler;
use Domains\Order\Application\Queries\Detail\AdminOrderDetailQuery;
use Domains\Order\Application\Queries\Edit\AdminOrderEditHandler;
use Domains\Order\Application\Queries\Edit\AdminOrderEditQuery;
use Domains\OrderAdjustment\Application\Commands\Adjust\AdjustOrderCommand;
use Domains\OrderAdjustment\Application\Commands\Adjust\AdjustOrderHandler;
use Domains\OrderReturn\Application\Commands\Cancel\CancelOrderReturnCommand;
use Domains\OrderReturn\Application\Commands\Cancel\CancelOrderReturnHandler;
use Domains\OrderReturn\Application\Commands\Record\RecordOrderReturnCommand;
use Domains\OrderReturn\Application\Commands\Record\RecordOrderReturnHandler;
use Domains\User\Domain\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminOrderController
{
    public function __construct(private readonly AdminOrderDetailHandler $detail,
        private readonly AdminOrderEditHandler $edit,
        private readonly AdjustOrderHandler $adjust,
        private readonly RecordOrderReturnHandler $recordHandler,
        private readonly CancelOrderReturnHandler $cancelHandler) {}

    private function adminId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return (int) $user->id;
    }

    public function order(int $order): View
    {
        return $this->renderDetail($this->detail->handle(new AdminOrderDetailQuery($order)));
    }

    public function editOrder(int $order): View
    {
        return $this->renderEdit($this->edit->handle(new AdminOrderEditQuery($order)));
    }

    private function renderDetail(AdminOrderDetailData $data): View
    {
        return view('bakery.admin-order', $data->viewData());
    }

    private function renderEdit(AdminOrderEditData $data): View
    {
        return view('bakery.edit-order', $data->viewData());
    }

    public function updateOrder(Request $request, int $order): RedirectResponse
    {
        /** @var array{quantities:array<int|string,int|string>,reason:string,admin_note?:string} $data */
        $data = $request->validate(['quantities' => ['required', 'array'], 'quantities.*' => ['required', 'integer', 'min:0', 'max:100000'], 'reason' => ['required', 'string', 'max:500'], 'admin_note' => ['nullable', 'string', 'max:1000']]);
        $this->adjust->handle(new AdjustOrderCommand($order, $this->adminId($request), $data['quantities'], trim($data['reason']), isset($data['admin_note']) ? trim($data['admin_note']) : null));

        return redirect()->route('bakery.admin-order', $order)->with('success', 'Заявка скорректирована.');
    }

    public function recordReturn(Request $request, int $order): RedirectResponse
    {
        /** @var array{product:int|string,quantity:int|string,reason?:string} $data */
        $data = $request->validate(['product' => ['required', 'integer', 'exists:bakery_products,id'], 'quantity' => ['required', 'integer', 'min:1', 'max:100000'], 'reason' => ['nullable', 'string', 'max:500']]);
        $this->recordHandler->handle(new RecordOrderReturnCommand($order, $this->adminId($request), (int) $data['product'], (int) $data['quantity'], isset($data['reason']) ? trim($data['reason']) : null));

        return back()->with('success', 'Возврат зафиксирован.');
    }

    public function cancelReturn(Request $request, int $order, int $return): RedirectResponse
    {
        /** @var array{reason:string} $data */
        $data = $request->validate(['reason' => ['required', 'string', 'max:500', 'not_regex:/^\s*$/u']]);
        $this->cancelHandler->handle(new CancelOrderReturnCommand($order, $return, $this->adminId($request), trim($data['reason'])));

        return redirect()->route('bakery.admin-order', $order)->with('success', 'Возврат отменён.');
    }
}
