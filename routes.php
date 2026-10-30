<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Gateways\FampayGateway\FampayGateway;

/*
|--------------------------------------------------------------------------
| FampayGateway Routes
|--------------------------------------------------------------------------
*/

// Payment verification — polled by the payment page every N seconds
Route::post('/extensions/gateways/FampayGateway/check-payment/{invoice}', [FampayGateway::class, 'checkPayment'])
    ->middleware(['web'])
    ->name('extensions.gateways.FampayGateway.check-payment');

// Admin test page — visit in browser to test all connections
Route::get('/extensions/gateways/FampayGateway/test', [FampayGateway::class, 'testConnection'])
    ->middleware(['web', 'auth'])
    ->name('extensions.gateways.FampayGateway.test');

// Debug route — shows exact error for troubleshooting 500s (remove after fixing)
Route::post('/extensions/gateways/FampayGateway/debug-check/{invoice}', function ($invoiceId) {
    try {
        $gateway = app(FampayGateway::class);
        return $gateway->checkPayment(request(), $invoiceId);
    } catch (\Throwable $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'file' => basename($e->getFile()) . ':' . $e->getLine(),
            'trace' => collect($e->getTrace())->take(5)->map(fn($t) => ($t['file'] ?? '?') . ':' . ($t['line'] ?? '?') . ' ' . ($t['function'] ?? ''))->toArray(),
        ]);
    }
})->middleware(['web', 'auth'])->name('extensions.gateways.FampayGateway.debug-check');
