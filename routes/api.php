<?php

use Domains\User\Presentation\Http\Controllers\LoginController;
use Domains\User\Presentation\Http\Controllers\LogoutController;
use Domains\User\Presentation\Http\Controllers\RegisterController;
use Domains\User\Presentation\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', RegisterController::class)->middleware('throttle:auth-login')->name('auth.register');
    Route::post('/login', LoginController::class)->middleware('throttle:auth-login')->name('auth.login');
    Route::post('/logout', LogoutController::class)->middleware('auth:sanctum')->name('auth.logout');
});

Route::prefix('v1')->middleware(['auth:sanctum', 'user.verified'])->group(function (): void {
    Route::apiResource('users', UserController::class);
});
