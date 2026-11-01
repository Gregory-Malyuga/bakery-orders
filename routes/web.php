<?php

use Domains\Bakery\Presentation\Http\Controllers\AdminWebController;
use Domains\Bakery\Presentation\Http\Controllers\ClosedDateController;
use Domains\Bakery\Presentation\Http\Controllers\ImpersonationController;
use Domains\Bakery\Presentation\Http\Controllers\IngredientCatalogWebController;
use Domains\Bakery\Presentation\Http\Controllers\RecipeWebController;
use Domains\Bakery\Presentation\Http\Controllers\StockController;
use Domains\Bakery\Presentation\Http\Controllers\StoreGroupController;
use Domains\Bakery\Presentation\Http\Controllers\StoreWebController;
use Domains\Bakery\Presentation\Http\Controllers\WebAuthController;
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
    Route::get('/orders', [StoreWebController::class, 'index'])->name('bakery.store');
    Route::get('/orders/{order}/repeat', [StoreWebController::class, 'repeat'])->name('bakery.repeat');
    Route::post('/orders/preview', [StoreWebController::class, 'preview'])->name('bakery.preview');
    Route::post('/orders/edit-preview', [StoreWebController::class, 'editPreview'])->name('bakery.edit-preview');
    Route::post('/orders', [StoreWebController::class, 'place'])->name('bakery.place');
    Route::get('/orders/{order}', [StoreWebController::class, 'show'])->name('bakery.order');
    Route::post('/orders/{order}/cancel', [StoreWebController::class, 'cancel'])->name('bakery.cancel');
});

Route::prefix('admin')->middleware(['auth', 'bakery.role:admin'])->group(function (): void {
    Route::get('/', [AdminWebController::class, 'index'])->name('bakery.admin');
    Route::get('/catalog', [AdminWebController::class, 'catalog'])->name('bakery.catalog');
    Route::get('/groups', [StoreGroupController::class, 'index'])->name('bakery.groups');
    Route::post('/groups', [StoreGroupController::class, 'save'])->name('bakery.groups-save');
    Route::get('/groups/{group}/print', [StoreGroupController::class, 'print'])->name('bakery.group-print');
    Route::get('/groups/{group}/xlsx', [StoreGroupController::class, 'download'])->name('bakery.group-xlsx');
    Route::get('/closed-dates', [ClosedDateController::class, 'index'])->name('bakery.closed-dates');
    Route::post('/closed-dates', [ClosedDateController::class, 'save'])->name('bakery.closed-dates-save');
    Route::post('/closed-dates/{date}/open', [ClosedDateController::class, 'open'])->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')->name('bakery.closed-dates-open');
    Route::get('/stock', [StockController::class, 'index'])->name('bakery.stock');
    Route::post('/stock/packaging', [StockController::class, 'createPackaging'])->name('bakery.stock-packaging');
    Route::post('/stock/movements', [StockController::class, 'record'])->name('bakery.stock-record');
    Route::post('/stock/movements/{movement}/reverse', [StockController::class, 'reverse'])->name('bakery.stock-reverse');
    Route::get('/recipes', [RecipeWebController::class, 'index'])->name('bakery.recipes');
    Route::get('/ingredients', [IngredientCatalogWebController::class, 'index'])->name('bakery.ingredients');
    Route::post('/ingredients', [IngredientCatalogWebController::class, 'create'])->name('bakery.ingredients-create');
    Route::post('/ingredients/{ingredient}/rename', [IngredientCatalogWebController::class, 'rename'])->name('bakery.ingredients-rename');
    Route::post('/ingredients/{ingredient}/toggle', [IngredientCatalogWebController::class, 'toggle'])->name('bakery.ingredients-toggle');
    Route::post('/recipes/product', [RecipeWebController::class, 'saveProduct'])->name('bakery.recipes-product');
    Route::post('/recipes/dough', [RecipeWebController::class, 'saveDough'])->name('bakery.recipes-dough');
    Route::post('/recipes/reset', [RecipeWebController::class, 'reset'])->name('bakery.recipes-reset');
    Route::post('/stores', [AdminWebController::class, 'saveStore'])->name('bakery.save-store');
    Route::post('/products', [AdminWebController::class, 'saveProduct'])->name('bakery.save-product');
    Route::post('/prices', [AdminWebController::class, 'savePrice'])->name('bakery.save-price');
    Route::post('/minimums', [AdminWebController::class, 'saveMinimum'])->name('bakery.save-minimum');
    Route::post('/availability', [AdminWebController::class, 'saveAvailability'])->name('bakery.save-availability');
    Route::post('/accounts', [AdminWebController::class, 'saveAccount'])->name('bakery.save-account');
    Route::post('/accounts/{user}/impersonate', [ImpersonationController::class, 'start'])->name('bakery.impersonation-start');
    Route::post('/settings', [AdminWebController::class, 'saveSettings'])->name('bakery.save-settings');
    Route::get('/orders/{order}', [AdminWebController::class, 'order'])->name('bakery.admin-order');
    Route::get('/orders/{order}/edit', [AdminWebController::class, 'editOrder'])->name('bakery.admin-order-edit');
    Route::post('/orders/{order}/edit', [AdminWebController::class, 'updateOrder'])->name('bakery.admin-order-update');
    Route::post('/orders/{order}/returns', [AdminWebController::class, 'recordReturn'])->name('bakery.admin-order-return');
    Route::post('/orders/{order}/returns/{return}/cancel', [AdminWebController::class, 'cancelReturn'])->name('bakery.admin-order-return-cancel');
    Route::get('/report', [AdminWebController::class, 'report'])->name('bakery.report');
    Route::get('/report/xlsx', [AdminWebController::class, 'downloadReport'])->name('bakery.report-xlsx');
    Route::get('/orders/{order}/print', [AdminWebController::class, 'printOne'])->name('bakery.print-one');
    Route::get('/orders/{order}/xlsx', [AdminWebController::class, 'downloadOne'])->name('bakery.xlsx-one');
    Route::get('/print', [AdminWebController::class, 'printBatch'])->name('bakery.print-batch');
    Route::get('/xlsx', [AdminWebController::class, 'downloadBatch'])->name('bakery.xlsx-batch');
});
