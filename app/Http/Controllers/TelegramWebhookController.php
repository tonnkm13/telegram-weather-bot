<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\FSM\TelegramFsmService;
use Telegram\Bot\Api;

class TelegramWebhookController extends Controller
{
    private Api $telegram;
    public function handle(Request $request, TelegramFsmService $fsm): \Illuminate\Http\JsonResponse
    {
        $update = $request->all();
        if (isset($update['callback_query'])) {
            Log::debug('CALLBACK QUERY RECEIVED', $update['callback_query']);

            $callback = $update['callback_query'];
            $chatId = $callback['message']['chat']['id'];
            $data = $callback['data'];

            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "Натиснута кнопка: {$data}",
            ]);

            return response()->json(['ok' => true]);
        }
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
    public function __construct()
    {
        $this->telegram = new Api(config('services.telegram.bot_token'));
    }
}
