<?php

use Domains\Bakery\Presentation\Http\Controllers\AdminWebController;
use Domains\Bakery\Presentation\Http\Controllers\ImpersonationController;
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
    Route::get('/report', [AdminWebController::class, 'report'])->name('bakery.report');
    Route::get('/report/xlsx', [AdminWebController::class, 'downloadReport'])->name('bakery.report-xlsx');
    Route::get('/orders/{order}/print', [AdminWebController::class, 'printOne'])->name('bakery.print-one');
    Route::get('/orders/{order}/xlsx', [AdminWebController::class, 'downloadOne'])->name('bakery.xlsx-one');
    Route::get('/print', [AdminWebController::class, 'printBatch'])->name('bakery.print-batch');
    Route::get('/xlsx', [AdminWebController::class, 'downloadBatch'])->name('bakery.xlsx-batch');
});
