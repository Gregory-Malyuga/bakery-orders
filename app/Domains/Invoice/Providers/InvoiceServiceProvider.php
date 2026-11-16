<?php

namespace Domains\Invoice\Providers;

use Domains\Invoice\Application\Exports\InvoiceWorkbookInterface;
use Domains\Invoice\Infrastructure\Exports\MatrixInvoiceWorkbook;
use Illuminate\Support\ServiceProvider;

class InvoiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InvoiceWorkbookInterface::class, MatrixInvoiceWorkbook::class);
    }
}
