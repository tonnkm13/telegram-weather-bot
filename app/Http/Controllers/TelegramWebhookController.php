<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\FSM\TelegramFsmService;
use Telegram\Bot\Api;

class TelegramWebhookController extends Controller
{
    private Api $telegram;
    public function handle(Request $request)
    {
        Log::debug('CONTROLLER HANDLE ENTERED');

        $update = $request->all();

        Log::debug('UPDATE PAYLOAD', $update);

        $message = $update['message'] ?? null;

        if (!$message) {
            Log::debug('NO MESSAGE IN UPDATE');
            return response()->json(['ok' => true]);
        }

        $chatId = $message['chat']['id'] ?? null;
        $text   = $message['text'] ?? '';

        if (!$chatId) {
            Log::error('CHAT ID NOT FOUND');
            return response()->json(['ok' => true]);
        }

        $this->fsm->handle($chatId, $text);

        return response()->json(['ok' => true]);
    }
    public function __construct()
    {
        $this->telegram = new Api(config('services.telegram.bot_token'));
    }
}
