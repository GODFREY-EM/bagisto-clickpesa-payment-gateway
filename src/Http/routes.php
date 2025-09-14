<?php

use Illuminate\Support\Facades\Route;
use Webkul\ClickPesa\Http\Controllers\ClickPesaController;

Route::group([
    'middleware' => ['web', 'theme', 'locale', 'currency']
], function () {
    Route::prefix('clickpesa')->group(function () {

        // Redirect customer to ClickPesa hosted checkout page
        Route::get('/redirect', [ClickPesaController::class, 'redirect'])
            ->name('clickpesa.redirect');

        // Customer is redirected here after payment success/failure
        Route::get('/success', [ClickPesaController::class, 'success'])
            ->name('clickpesa.success');

        // If customer cancels payment
        Route::get('/cancel', [ClickPesaController::class, 'cancel'])
            ->name('clickpesa.cancel');

        // Server-to-server callback from ClickPesa
        Route::post('/callback', [ClickPesaController::class, 'callback'])
            ->name('clickpesa.callback');
    });
});
