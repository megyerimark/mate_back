<?php

use App\Http\Controllers\FrontendController;
use Illuminate\Support\Facades\Route;

// Minden nem /api cím az előrenderelt frontend oldalakat kapja (lásd FrontendController).
Route::get('/{path?}', FrontendController::class)->where('path', '(?!api(/|$)|up$).*');
