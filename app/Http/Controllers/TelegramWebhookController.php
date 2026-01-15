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

        $chatId = $update['message']['chat']['id'] ?? null;

        if (!$chatId) {
            Log::error('CHAT ID NOT FOUND');
            return response()->json(['ok' => true]);
        }

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => '🟢 Webhook живий',
        ]);

        return response()->json(['ok' => true]);
    }

    public function __construct()
    {
        $this->telegram = new Api(config('services.telegram.bot_token'));
    }
}
