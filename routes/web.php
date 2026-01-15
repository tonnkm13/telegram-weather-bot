<?php

use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

//Route::post('/telegram/webhook', function () {
 //   Log::debug('INSIDE ROUTE CLOSURE');
 //   return response()->json(['ok' => true]);});

//Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);
//Route::get('/ping', function () {
//   return 'pong';});


//Route::get('/ping', function () {    return 'pong';});

Route::post('/telegram/webhook', function () {
    Log::debug('WEBHOOK ROUTE HIT');
    return response()->json(['ok' => true]);});
