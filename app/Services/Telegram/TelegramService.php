<?php


namespace App\Services\Telegram;

use Telegram\Bot\Api;

class TelegramService
{
    private Api $telegram;

    public function __construct()
    {
        $this->telegram = new Api(config('telegram.bot_token'));
    }

    public function sendMessage(int $chatId, string $text, array $replyMarkup = null): void
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        $this->telegram->sendMessage($params);
    }
}
