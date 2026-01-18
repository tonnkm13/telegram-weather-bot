<?php

namespace App\Services\FSM;

use App\Models\TelegramUser;
use Telegram\Bot\Api;
use App\Services\Weather\OpenWeatherService;
use Illuminate\Support\Facades\Log;

class TelegramFsmService
{
    private Api $telegram;
    private OpenWeatherService $weather;

    public function __construct(OpenWeatherService $weather)
    {
        $this->telegram = new Api(config('services.telegram.bot_token'));
        $this->weather  = $weather;
    }

    public function handle(int $telegramId, int $chatId, string $text): void
    {
        Log::debug('FSM HANDLE', compact('telegramId', 'chatId', 'text'));

        $user = TelegramUser::firstOrCreate(
            ['telegram_id' => $telegramId],
            ['state' => 'start']
        );

        // ===== DONE STATE =====
        if ($user->state === 'done') {
            match ($text) {
                '🌤 Зараз'      => $this->sendNow($user, $chatId),
                '📅 Завтра'    => $this->sendTomorrow($user, $chatId),
                '📆 На 3 дні'  => $this->sendThreeDays($user, $chatId),
                '🔄 Почати заново' => $this->reset($user, $chatId),
                default        => $this->send($chatId, 'Вибери дію 👇', $this->keyboard()),
            };
            return;
        }

        // ===== FSM =====
        switch ($user->state) {
            case 'start':
                $this->askCity($user, $chatId);
                break;

            case 'waiting_city':
                $this->saveCity($user, $chatId, $text);
                break;

            default:
                $this->reset($user, $chatId);
        }
    }

    // ================= FSM STEPS =================

    private function askCity(TelegramUser $user, int $chatId): void
    {
        $user->update(['state' => 'waiting_city']);
        $this->send($chatId, '🌍 Введи місто');
    }

    private function saveCity(TelegramUser $user, int $chatId, string $text): void
    {
        $city = ucfirst(mb_strtolower(trim($text)));

        $user->update([
            'location' => $city,
            'state'    => 'done',
        ]);

        $this->send(
            $chatId,
            "✅ Місто збережено: {$city}\n\nОбери дію 👇",
            $this->keyboard()
        );
    }

    private function reset(TelegramUser $user, int $chatId): void
    {
        $user->update([
            'state'    => 'start',
            'location' => null,
        ]);

        $this->send($chatId, '🔄 Почнемо спочатку');
        $this->askCity($user, $chatId);
    }

    // ================= WEATHER =================

    private function sendNow(TelegramUser $user, int $chatId): void
    {
        $w = $this->weather->getNormalizedWeather($user->location);

        if (!$w) {
            $this->send($chatId, '❌ Не вдалося отримати погоду');
            return;
        }

        $this->send($chatId,
            "🌤 Погода у {$user->location}\n\n" .
            "🌡 {$w['temp']}°C\n" .
            "🤍 {$w['feels_like']}°C\n" .
            "💧 {$w['humidity']}%\n" .
            "🌬 {$w['wind']} м/с\n" .
            "📖 {$w['description']}",
            $this->keyboard()
        );
    }

    private function sendTomorrow(TelegramUser $user, int $chatId): void
    {
        $w = $this->weather->getTomorrow($user->location);

        if (!$w) {
            $this->send($chatId, '❌ Немає прогнозу на завтра');
            return;
        }

        $this->send($chatId,
            "📅 Завтра у {$user->location}\n\n" .
            "🌡 {$w['temp']}°C\n" .
            "🤍 {$w['feels_like']}°C\n" .
            "💧 {$w['humidity']}%\n" .
            "🌬 {$w['wind']} м/с\n" .
            "📖 {$w['description']}",
            $this->keyboard()
        );
    }

    private function sendThreeDays(TelegramUser $user, int $chatId): void
    {
        $days = $this->weather->getThreeDays($user->location);

        if (!$days) {
            $this->send($chatId, '❌ Немає прогнозу на 3 дні');
            return;
        }

        $text = "📆 Погода на 3 дні у {$user->location}\n\n";

        foreach ($days as $d) {
            $text .=
                "📅 {$d['date']}\n" .
                "🌡 {$d['temp']}°C\n" .
                "🌬 {$d['wind']} м/с\n\n";
        }

        $this->send($chatId, $text, $this->keyboard());
    }

    // ================= HELPERS =================

    private function send(int $chatId, string $text, ?string $keyboard = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text'    => $text,
        ];

        if ($keyboard) {
            $payload['reply_markup'] = $keyboard;
        }

        $this->telegram->sendMessage($payload);
    }

    private function keyboard(): string
    {
        return json_encode([
            'keyboard' => [
                [['text' => '🌤 Зараз'], ['text' => '📅 Завтра']],
                [['text' => '📆 На 3 дні'], ['text' => '🔄 Почати заново']],
            ],
            'resize_keyboard' => true,
        ]);
    }
}
