<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\QuoteRequestController as AdminQuoteRequestController;
use App\Http\Controllers\Admin\StatsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\QuoteRequestController;
use Illuminate\Support\Facades\Route;

Route::post('/quote-requests', [QuoteRequestController::class, 'store'])
    ->middleware('throttle:quote-requests');

// Admin felület
Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:admin-login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::put('/password', [UserController::class, 'updatePassword']);
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        Route::get('/stats', [AdminQuoteRequestController::class, 'stats']);
        Route::get('/stats/overview', StatsController::class);
        Route::get('/quote-requests', [AdminQuoteRequestController::class, 'index']);
        // Az export a {quoteRequest} útvonal előtt legyen, különben azonosítónak venné.
        Route::get('/quote-requests/export', [AdminQuoteRequestController::class, 'export']);
        Route::get('/quote-requests/{quoteRequest}', [AdminQuoteRequestController::class, 'show']);
        Route::patch('/quote-requests/{quoteRequest}', [AdminQuoteRequestController::class, 'update']);
        Route::delete('/quote-requests/{quoteRequest}', [AdminQuoteRequestController::class, 'destroy']);
        Route::get('/quote-requests/{quoteRequest}/document', [AdminQuoteRequestController::class, 'document']);
        Route::post('/quote-requests/{quoteRequest}/resend-mail', [AdminQuoteRequestController::class, 'resendMail']);
    });
});
