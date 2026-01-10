<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\FSM\TelegramFsmService;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, TelegramFsmService $fsm): \Illuminate\Http\JsonResponse
    {   Log::debug('WEBHOOK HIT FROM TELEGRAM');
        Log::debug('STEP 1: webhook controller entered');
        $update = $request->all();

        Log::debug('WEBHOOK HIT', $update);

        if (!isset($update['message']['text'])) {
            return response()->json(['ok' => true]);
        }

        $telegramId = $update['message']['from']['id'];
        $chatId     = $update['message']['chat']['id'];
        $text       = trim($update['message']['text']);

        Log::debug('TEXT RECEIVED', ['text' => $text]);

        // 🔥 ВСЯ логіка ТУТ ЗАКІНЧУЄТЬСЯ
        $fsm->handle($telegramId, $chatId, $text);

        return response()->json(['ok' => true]);
    }
}
