<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\QuoteRequestController as AdminQuoteRequestController;
use App\Http\Controllers\Admin\StatsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\QuoteRequestController;
use App\Http\Controllers\TrustController;
use App\Http\Controllers\Admin\TrustController as AdminTrustController;
use Illuminate\Support\Facades\Route;

Route::post('/quote-requests', [QuoteRequestController::class, 'store'])
    ->middleware('throttle:quote-requests');

// A weboldal bizalomépítő szakasza (csak publikált tartalom)
Route::get('/trust', [TrustController::class, 'index'])->middleware('throttle:60,1');
Route::get('/partners/{partner}/logo', [TrustController::class, 'logo']);

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
        Route::get('/quote-requests/{quoteRequest}/documents/{index}', [AdminQuoteRequestController::class, 'document'])->whereNumber('index');
        Route::get('/testimonials', [AdminTrustController::class, 'testimonials']);
        Route::post('/testimonials', [AdminTrustController::class, 'storeTestimonial']);
        Route::patch('/testimonials/{testimonial}', [AdminTrustController::class, 'updateTestimonial']);
        Route::delete('/testimonials/{testimonial}', [AdminTrustController::class, 'destroyTestimonial']);
        Route::get('/partners', [AdminTrustController::class, 'partners']);
        Route::post('/partners', [AdminTrustController::class, 'storePartner']);
        Route::post('/partners/{partner}', [AdminTrustController::class, 'updatePartner']);
        Route::delete('/partners/{partner}', [AdminTrustController::class, 'destroyPartner']);
        Route::get('/partners/{partner}/logo', [AdminTrustController::class, 'partnerLogo']);
        Route::post('/quote-requests/{quoteRequest}/resend-mail', [AdminQuoteRequestController::class, 'resendMail']);
    });
});
