<?php

namespace Domains\Invoice\Presentation\Http\Controllers;

use Domains\Bakery\Application\GroupInvoiceService;
use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Domains\Invoice\Application\Exports\InvoiceWorkbookInterface;
use Domains\Invoice\Application\Queries\Batch\BatchInvoiceHandler;
use Domains\Invoice\Application\Queries\Batch\BatchInvoiceQuery;
use Domains\Invoice\Application\Queries\Single\SingleInvoiceHandler;
use Domains\Invoice\Application\Queries\Single\SingleInvoiceQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InvoiceController
{
    public function __construct(private readonly OrderingRepositoryInterface $orders, private readonly InvoiceWorkbookInterface $workbook, private readonly GroupInvoiceService $groupInvoices,
        private readonly SingleInvoiceHandler $single,
        private readonly BatchInvoiceHandler $batch) {}

    private function date(Request $request): string
    {
        /** @var array{date?:string} $input */
        $input = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return $input['date'] ?? now('Europe/Moscow')->addDay()->toDateString();
    }

    public function printOne(int $order): View
    {
        $data = $this->single->handle(new SingleInvoiceQuery($order));

        return view('bakery.print', $data->view + ['downloadUrl' => route('bakery.xlsx-one', $order)]);
    }

    public function printBatch(Request $request): View
    {
        $date = $this->date($request);
        $data = $this->batch->handle(new BatchInvoiceQuery($date));

        return view('bakery.print', $data->view + ['downloadUrl' => route('bakery.xlsx-batch', ['date' => $date])]);
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
