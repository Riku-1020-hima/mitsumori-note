<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::resource('clients', ClientController::class);

    // 見積書と請求書(同じ作り。見積書だけ「請求書にする」がある)
    foreach (['quotes' => QuoteController::class, 'invoices' => InvoiceController::class] as $base => $controller) {
        Route::get("/{$base}/{id}/pdf", [$controller, 'pdf'])->name("{$base}.pdf")->middleware('throttle:30,1');
        Route::post("/{$base}/{id}/duplicate", [$controller, 'duplicate'])->name("{$base}.duplicate");
        Route::post("/{$base}/{id}/status", [$controller, 'status'])->name("{$base}.status");
        Route::resource($base, $controller)->parameters([$base => 'id']);
    }
    Route::post('/quotes/{id}/convert', [QuoteController::class, 'convert'])->name('quotes.convert');
});
