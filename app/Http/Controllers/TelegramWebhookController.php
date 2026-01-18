<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\FSM\TelegramFsmService;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private TelegramFsmService $fsm
    ) {}

    public function handle(Request $request)
    {
        Log::debug('CONTROLLER HANDLE ENTERED');

        $update = $request->all();
        Log::debug('UPDATE PAYLOAD', $update);

        // працюємо ТІЛЬКИ з message
        if (!isset($update['message'])) {
            Log::debug('NO MESSAGE IN UPDATE');
            return response()->json(['ok' => true]);
        }

        $message = $update['message'];

        $telegramId = $message['from']['id'] ?? null;
        $chatId     = $message['chat']['id'] ?? null;
        $text       = trim($message['text'] ?? '');

        if (!$telegramId || !$chatId || $text === '') {
            Log::error('INVALID MESSAGE DATA', [
                'telegram_id' => $telegramId,
                'chat_id' => $chatId,
                'text' => $text,
            ]);
            return response()->json(['ok' => true]);
        }

        Log::debug('TEXT RECEIVED', ['text' => $text]);

        // 🔥 ЄДИНА ЛОГІКА — передаємо все у FSM
        $this->fsm->handle(
            telegramId: $telegramId,
            chatId: $chatId,
            text: $text
        );

        return response()->json(['ok' => true]);
    }
}
