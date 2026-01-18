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
        $this->weather = $weather;
    }

    public function handle(int $telegramId, int $chatId, string $text): void
    {
        Log::debug('FSM HANDLE', compact('telegramId', 'chatId', 'text'));

        $user = TelegramUser::firstOrCreate(
            ['telegram_id' => $telegramId],
            ['state' => 'start']
        );

        /* =====================
         * ГЛОБАЛЬНІ КНОПКИ
         * ===================== */
        if ($text === '🔄 Почати заново') {
            $this->reset($user, $chatId);
            return;
        }

        if ($text === '🌤 Зараз') {
            $this->sendNow($user, $chatId);
            return;
        }

        if ($text === '📅 Завтра') {
            $this->sendTomorrow($user, $chatId);
            return;
        }

        if ($text === '📆 На 3 дні') {
            $this->sendThreeDays($user, $chatId);
            return;
        }

        if ($text === '🏙 Інше місто') {
            $user->update(['state' => 'waiting_city']);
            $this->askCity($chatId);
            return;
        }

        /* =====================
         * FSM
         * ===================== */
        if ($user->state === 'start' || $user->state === 'waiting_city') {
            $this->saveCity($user, $chatId, $text);
            return;
        }

        // fallback — нічого не ламаємо
        $this->send($chatId, 'Натисни кнопку 👇', $this->keyboard());
    }

    /* =====================
     * FSM ACTIONS
     * ===================== */

    private function askCity(int $chatId): void
    {
        $this->send($chatId, '🌍 Введи місто');
    }

    private function saveCity(TelegramUser $user, int $chatId, string $text): void
    {
        $city = ucfirst(trim($text));

        $user->update([
            'location' => $city,
            'state' => 'done',
        ]);

        $this->send($chatId, "✅ Місто збережено: {$city}", $this->keyboard());
        $this->sendNow($user, $chatId);
    }

    private function reset(TelegramUser $user, int $chatId): void
    {
        $user->update([
            'state' => 'waiting_city',
            'location' => null,
        ]);

        $this->send($chatId, '🔄 Почнемо спочатку. Введи місто');
    }

    /* =====================
     * WEATHER
     * ===================== */

    private function sendNow(TelegramUser $user, int $chatId): void
    {
        if (!$user->location) {
            $this->askCity($chatId);
            return;
        }

        $w = $this->weather->getNormalizedWeather($user->location);

        if (!$w) {
            $this->send($chatId, '❌ Не вдалося отримати погоду');
            return;
        }

        $this->sendWeather($chatId, $user->location, $w, '🌤 Зараз');
    }

    private function sendTomorrow(TelegramUser $user, int $chatId): void
    {
        if (!$user->location) {
            $this->askCity($chatId);
            return;
        }

        $w = $this->weather->getTomorrow($user->location);

        if (!$w) {
            $this->send($chatId, '❌ Немає прогнозу на завтра');
            return;
        }

        $this->sendWeather($chatId, $user->location, $w, '📅 Завтра');
    }

    private function sendThreeDays(TelegramUser $user, int $chatId): void
    {
        if (!$user->location) {
            $this->askCity($chatId);
            return;
        }

        $days = $this->weather->getThreeDays($user->location);

        if (!$days) {
            $this->send($chatId, '❌ Немає прогнозу на 3 дні');
            return;
        }

        $text = "📆 Погода на 3 дні у {$user->location}\n\n";
        foreach ($days as $d) {
            $text .= "📅 {$d['date']}\n🌡 {$d['temp']}°C\n📖 {$d['description']}\n\n";
        }

        $this->send($chatId, $text, $this->keyboard());
    }

    private function sendWeather(int $chatId, string $city, array $w, string $title): void
    {
        $text =
            "{$title}\n\n" .
            "🌍 {$city}\n" .
            "🌡 {$w['temp']}°C\n" .
            "🤍 {$w['feels_like']}°C\n" .
            "💧 {$w['humidity']}%\n" .
            "🌬 {$w['wind']} м/с\n" .
            "📖 {$w['description']}";

        $this->send($chatId, $text, $this->keyboard());
    }

    /* =====================
     * UI
     * ===================== */

    private function send(int $chatId, string $text, ?string $keyboard = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
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
                [
                    ['text' => '🌤 Зараз'],
                    ['text' => '📅 Завтра'],
                ],
                [
                    ['text' => '📆 На 3 дні'],
                    ['text' => '🏙 Інше місто'],
                ],
                [
                    ['text' => '🔄 Почати заново'],
                ],
            ],
            'resize_keyboard' => true,
        ]);
    }
}
