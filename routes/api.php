<?php

use App\Http\Controllers\CaptchaController;
use App\Http\Controllers\SendController;
use App\Http\Controllers\TelegramController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('telegram/webhook', [TelegramController::class, 'handle']);
Route::get('send', [SendController::class, 'send']);
Route::post('captcha', [CaptchaController::class, 'test']);

// all cache remover
Route::get('cache', function () {
    Cache::flush();
    return 'Cache is cleared';
});
