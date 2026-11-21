<?php

use Domains\Analytics\Presentation\Http\Controllers\AnalyticsController;
use Domains\Catalog\Presentation\Http\Controllers\CatalogController;
use Domains\DeliverySchedule\Presentation\Http\Controllers\ClosedDateController;
use Domains\DeliverySheet\Presentation\Http\Controllers\DeliverySheetController;
use Domains\Ingredient\Presentation\Http\Controllers\IngredientController;
use Domains\Invoice\Presentation\Http\Controllers\InvoiceController;
use Domains\Order\Presentation\Http\Controllers\AdminOrderController;
use Domains\Order\Presentation\Http\Controllers\OrderSettingsController;
use Domains\Order\Presentation\Http\Controllers\StoreOrderController;
use Domains\Price\Presentation\Http\Controllers\PriceController;
use Domains\Product\Presentation\Http\Controllers\ProductController;
use Domains\Production\Presentation\Http\Controllers\ProductionDashboardController;
use Domains\Recipe\Presentation\Http\Controllers\RecipeController;
use Domains\Report\Presentation\Http\Controllers\ReportController;
use Domains\StockItem\Presentation\Http\Controllers\StockDashboardController;
use Domains\StockItem\Presentation\Http\Controllers\StockItemController;
use Domains\StockMovement\Presentation\Http\Controllers\StockMovementController;
use Domains\StockPriceChange\Presentation\Http\Controllers\StockPriceChangeController;
use Domains\Store\Presentation\Http\Controllers\StoreController;
use Domains\StoreAccount\Presentation\Http\Controllers\StoreAccountController;
use Domains\StoreGroup\Presentation\Http\Controllers\StoreGroupController;
use Domains\StoreProductAvailability\Presentation\Http\Controllers\StoreProductAvailabilityController;
use Domains\StoreProductMinimum\Presentation\Http\Controllers\StoreProductMinimumController;
use Domains\User\Presentation\Http\Controllers\ImpersonationController;
use Domains\User\Presentation\Http\Controllers\WebAuthController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', function (): RedirectResponse {
    if (! auth()->check()) {
        return redirect()->route('bakery.login');
    }

    return redirect()->route(auth()->user()->role->value === 'admin' ? 'bakery.admin' : 'bakery.store');
});
Route::get('/login', [WebAuthController::class, 'loginForm'])->middleware('guest')->name('bakery.login');
Route::post('/login', [WebAuthController::class, 'login'])->middleware(['guest', 'throttle:auth-login']);
Route::post('/logout', [WebAuthController::class, 'logout'])->middleware('auth')->name('bakery.logout');
Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])->middleware('auth')->name('bakery.impersonation-stop');

Route::middleware(['auth', 'bakery.role:store'])->group(function (): void {
    Route::get('/orders', [StoreOrderController::class, 'index'])->name('bakery.store');
    Route::get('/orders/{order}/repeat', [StoreOrderController::class, 'repeat'])->name('bakery.repeat');
    Route::post('/orders/preview', [StoreOrderController::class, 'preview'])->name('bakery.preview');
    Route::post('/orders/edit-preview', [StoreOrderController::class, 'editPreview'])->name('bakery.edit-preview');
    Route::post('/orders', [StoreOrderController::class, 'place'])->name('bakery.place');
    Route::get('/orders/{order}', [StoreOrderController::class, 'show'])->name('bakery.order');
    Route::post('/orders/{order}/cancel', [StoreOrderController::class, 'cancel'])->name('bakery.cancel');
});

Route::prefix('admin')->middleware(['auth', 'bakery.role:admin'])->group(function (): void {
    Route::get('/delivery-routes', [DeliverySheetController::class, 'index'])->name('bakery.delivery-routes');
    Route::post('/delivery-sheets', [DeliverySheetController::class, 'createSheet'])->name('bakery.delivery-sheets-create');
    Route::post('/delivery-sheet-prints', [DeliverySheetController::class, 'printSheet'])->name('bakery.delivery-sheet-print');
    Route::get('/delivery-sheet-prints/{print}', [DeliverySheetController::class, 'printedSheet'])->name('bakery.delivery-sheet-print-record');
    Route::get('/delivery-sheets/{sheet}/edit', [DeliverySheetController::class, 'editSheet'])->name('bakery.delivery-sheet-edit');
    Route::post('/delivery-sheets/{sheet}/edit', [DeliverySheetController::class, 'updateSheet'])->name('bakery.delivery-sheet-update');
    Route::post('/delivery-sheets/{sheet}/delete', [DeliverySheetController::class, 'deleteSheet'])->name('bakery.delivery-sheet-delete');
    Route::get('/delivery-sheets/{sheet}', [DeliverySheetController::class, 'sheet'])->name('bakery.delivery-sheet');
    Route::get('/', [ProductionDashboardController::class, 'index'])->name('bakery.admin');
    Route::get('/catalog', [CatalogController::class, 'catalog'])->name('bakery.catalog');
    Route::get('/groups', [StoreGroupController::class, 'index'])->name('bakery.groups');
    Route::post('/groups', [StoreGroupController::class, 'save'])->name('bakery.groups-save');
    Route::get('/groups/{group}/print', [StoreGroupController::class, 'print'])->name('bakery.group-print');
    Route::get('/groups/{group}/xlsx', [StoreGroupController::class, 'download'])->name('bakery.group-xlsx');
    Route::get('/closed-dates', [ClosedDateController::class, 'index'])->name('bakery.closed-dates');
    Route::post('/closed-dates', [ClosedDateController::class, 'save'])->name('bakery.closed-dates-save');
    Route::post('/closed-dates/{date}/open', [ClosedDateController::class, 'open'])->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')->name('bakery.closed-dates-open');
    Route::get('/stock', [StockDashboardController::class, 'index'])->name('bakery.stock');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('bakery.analytics');
    Route::get('/analytics/csv', [AnalyticsController::class, 'download'])->name('bakery.analytics-csv');
    Route::post('/stock/packaging', [StockItemController::class, 'createPackaging'])->name('bakery.stock-packaging');
    Route::post('/stock/prices', [StockPriceChangeController::class, 'save'])->name('bakery.stock-price');
    Route::post('/stock/movements', [StockMovementController::class, 'record'])->name('bakery.stock-record');
    Route::post('/stock/movements/{movement}/reverse', [StockMovementController::class, 'reverse'])->name('bakery.stock-reverse');
    Route::get('/recipes', [RecipeController::class, 'index'])->name('bakery.recipes');
    Route::get('/ingredients', [IngredientController::class, 'index'])->name('bakery.ingredients');
    Route::post('/ingredients', [IngredientController::class, 'create'])->name('bakery.ingredients-create');
    Route::post('/ingredients/{ingredient}/rename', [IngredientController::class, 'rename'])->name('bakery.ingredients-rename');
    Route::post('/ingredients/{ingredient}/toggle', [IngredientController::class, 'toggle'])->name('bakery.ingredients-toggle');
    Route::post('/recipes/product', [RecipeController::class, 'saveProduct'])->name('bakery.recipes-product');
    Route::post('/recipes/dough', [RecipeController::class, 'saveDough'])->name('bakery.recipes-dough');
    Route::post('/recipes/reset', [RecipeController::class, 'reset'])->name('bakery.recipes-reset');
    Route::post('/stores', [StoreController::class, 'save'])->name('bakery.save-store');
    Route::post('/products', [ProductController::class, 'save'])->name('bakery.save-product');
    Route::get('/products/{product}', [CatalogController::class, 'product'])->name('bakery.product');
    Route::post('/products/{product}/prices', [PriceController::class, 'bulk'])->name('bakery.bulk-price');
    Route::post('/products/{product}/prices/{operation}/reverse', [PriceController::class, 'reverse'])->name('bakery.bulk-price-reverse');
    Route::post('/prices', [PriceController::class, 'save'])->name('bakery.save-price');
    Route::post('/minimums', [StoreProductMinimumController::class, 'save'])->name('bakery.save-minimum');
    Route::post('/availability', [StoreProductAvailabilityController::class, 'save'])->name('bakery.save-availability');
    Route::post('/accounts', [StoreAccountController::class, 'save'])->name('bakery.save-account');
    Route::post('/accounts/{user}/impersonate', [ImpersonationController::class, 'start'])->name('bakery.impersonation-start');
    Route::post('/settings', [OrderSettingsController::class, 'save'])->name('bakery.save-settings');
    Route::get('/orders/{order}', [AdminOrderController::class, 'order'])->name('bakery.admin-order');
    Route::get('/orders/{order}/edit', [AdminOrderController::class, 'editOrder'])->name('bakery.admin-order-edit');
    Route::post('/orders/{order}/edit', [AdminOrderController::class, 'updateOrder'])->name('bakery.admin-order-update');
    Route::post('/orders/{order}/returns', [AdminOrderController::class, 'recordReturn'])->name('bakery.admin-order-return');
    Route::post('/orders/{order}/returns/{return}/cancel', [AdminOrderController::class, 'cancelReturn'])->name('bakery.admin-order-return-cancel');
    Route::get('/report', [ReportController::class, 'report'])->name('bakery.report');
    Route::get('/report/xlsx', [ReportController::class, 'downloadReport'])->name('bakery.report-xlsx');
    Route::get('/orders/{order}/print', [InvoiceController::class, 'printOne'])->name('bakery.print-one');
    Route::get('/orders/{order}/xlsx', [InvoiceController::class, 'downloadOne'])->name('bakery.xlsx-one');
    Route::get('/print', [InvoiceController::class, 'printBatch'])->name('bakery.print-batch');
    Route::get('/xlsx', [InvoiceController::class, 'downloadBatch'])->name('bakery.xlsx-batch');
});
