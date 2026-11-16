<?php

namespace Domains\Order\Presentation\Http\Controllers;

use Domains\Bakery\Application\Exports\InvoiceWorkbookInterface;
use Domains\Bakery\Application\Exports\PeriodReportWorkbookInterface;
use Domains\Bakery\Application\GroupInvoiceService;
use Domains\Bakery\Application\LabelPlanService;
use Domains\Order\Application\OrderCutoff;
use Domains\Order\Application\Queries\Detail\AdminOrderDetailQuery;
use Domains\Order\Application\Queries\Detail\AdminOrderDetailHandler;
use Domains\Order\Application\Queries\Edit\AdminOrderEditQuery;
use Domains\Order\Application\Queries\Edit\AdminOrderEditHandler;
use Domains\OrderAdjustment\Application\Commands\Adjust\AdjustOrderCommand;
use Domains\OrderAdjustment\Application\Commands\Adjust\AdjustOrderHandler;
use Domains\OrderReturn\Application\Commands\Record\RecordOrderReturnCommand;
use Domains\OrderReturn\Application\Commands\Record\RecordOrderReturnHandler;
use Domains\OrderReturn\Application\Commands\Cancel\CancelOrderReturnCommand;
use Domains\OrderReturn\Application\Commands\Cancel\CancelOrderReturnHandler;
use Domains\Bakery\Application\OrderReturnSummary;
use Domains\Bakery\Application\PeriodReportService;
use Domains\Bakery\Application\ProductionCalculator;
use Domains\StoreAccount\Application\Queries\ListAll\ListStoreAccountsQuery;
use Domains\StoreAccount\Application\Queries\ListAll\ListStoreAccountsHandler;
use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Domains\StockPrice\Application\StockPrices;
use Domains\User\Domain\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        return view('bakery.admin-order', $this->detail->handle(new AdminOrderDetailQuery($order))->view);
    }

    public function editOrder(int $order): View
    {
        return view('bakery.edit-order', $this->edit->handle(new AdminOrderEditQuery($order))->view);
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
