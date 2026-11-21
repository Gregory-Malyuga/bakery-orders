<?php

namespace Domains\Bakery\Infrastructure\Exports;

use Domains\Bakery\Application\Exports\InvoiceWorkbookInterface;

/** @deprecated Use Domains\Invoice\Infrastructure\Exports\MatrixInvoiceWorkbook. */
class MatrixInvoiceWorkbook extends \Domains\Invoice\Infrastructure\Exports\MatrixInvoiceWorkbook implements InvoiceWorkbookInterface {}
