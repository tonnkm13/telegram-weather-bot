<?php

use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

//Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);
//Route::get('/ping', function () {
//   return 'pong';});


Route::get('/ping', function () {
    return 'pong';
});

Route::post('/telegram/webhook', function () {
    \Log::debug('WEBHOOK ROUTE HIT');
    return response()->json(['ok' => true]);
});
