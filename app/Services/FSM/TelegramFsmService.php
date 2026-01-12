<?php

namespace App\Services\FSM;

use App\Models\TelegramUser;
use Telegram\Bot\Api;
use App\Services\Weather\OpenWeatherService;
use Illuminate\Support\Facades\Log;
use App\Services\Telegram\TelegramService;
use App\Services\Weather\WeatherApi2Service;

class TelegramFsmService
{
    private Api $telegram;
    private OpenWeatherService $weather;
    private WeatherApi2Service $weatherApi2;

    public function __construct(
        OpenWeatherService $weather,
        WeatherApi2Service $weatherApi2
    ) {
        $this->telegram = new Api(config('services.telegram.bot_token'));
        $this->weather = $weather;
        $this->weatherApi2 = $weatherApi2;
    }



    public function handle(int $telegramId, int $chatId, string $text): void
    { Log::debug('FSM HANDLE ENTER', [
        'telegram_id' => $telegramId,
        'chat_id' => $chatId,
        'text' => $text,
    ]);
        $user = TelegramUser::firstOrCreate(
            ['telegram_id' => $telegramId],
            ['state' => 'start']
        );

        Log::debug('USER BEFORE LOGIC', $user->toArray());

        if ($text === '/start' || $text === '🔄 Почати заново') {
            $this->reset($user, $chatId);
            return;
        }
        if ($text === 'Завтра') {
            Log::debug('BUTTON TOMORROW PRESSED');
            $this->sendTomorrowWeather($user, $chatId);
            return;
        }
        if ($text === '🏙 Інше місто') {
            $user->update(['state' => 'waiting_city']);
            $this->askCity($chatId);
            return;
        }

        if ($text === '📆 На 3 дні') {
            $this->sendThreeDaysWeather($user, $chatId);
            return;
        }
        switch ($user->state) {
            case 'start':
                $this->askCity($user, $chatId);
                break;

            case 'waiting_city':
                $this->saveCity($user, $chatId, $text);
                break;

            case 'waiting_date':
                Log::debug('FSM ENTER waiting_date');
                $this->saveDate($user, $chatId, $text);
                break;

            case 'waiting_time':
                $this->saveTime($user, $chatId, $text);
                break;

            default:
                $this->reset($user, $chatId);
                break;
        }

        Log::debug('USER AFTER LOGIC', $user->toArray());
    }

    private function askCity(TelegramUser $user, int $chatId): void
    {
        $user->update(['state' => 'waiting_city']);

        $this->send($chatId, "🌍 Введи місто");
    }

    private function saveCity(TelegramUser $user, int $chatId, string $text): void
    {
        $city = mb_strtolower(trim($text));
        $city = match ($city) {
            'львів', 'lviv' => 'Lviv',
            'київ', 'kyiv' => 'Kyiv',
            'одеса', 'odesa', 'odessa' => 'Odesa',
            default => ucfirst($city),
        };

        $user->update([
            'location' => $city,
            'state' => 'waiting_date',
        ]);

        $this->send($chatId, "📅 Введи дату (YYYY-MM-DD)");
    }

    private function saveDate(TelegramUser $user, int $chatId, string $text): void
    {
        // очікуємо формат YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "❌ Невірний формат дати.\nВведи дату у форматі: 2025-12-31",
            ]);
            return;
        }

        $user->update([
            'date'  => $text,
            'state' => 'waiting_time',
        ]);

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "⏰ Вкажи часовий діапазон (наприклад: 09:00-18:00)",
        ]);
    }


    private function saveTime(TelegramUser $user, int $chatId, string $text): void
    {
        if (!preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $text)) {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "❌ Невірний формат часу.\nПриклад: 09:00-18:00",
            ]);
            return;
        }

        $user->update([
            'time_range' => $text,
        ]);

        $this->sendFinalMessage($user, $chatId);
    }

    private function reset(TelegramUser $user, int $chatId): void
    {
        $user->update([
            'state' => 'waiting_city',
            'location' => null,
            'date' => null,
            'time_range' => null,
        ]);

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "👋 Почнемо спочатку. Введи місто.",
            'reply_markup' => json_encode([
                'remove_keyboard' => true,
            ]),
        ]);
    }

    private function send(int $chatId, string $text): void
    {
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }
    private function sendFinalMessage(TelegramUser $user, int $chatId): void
    {
        $text =
            "✅ Дані отримано!\n\n" .
            "🌍 {$user->location}\n" .
            "📅 {$user->date}\n" .
            "⏰ {$user->time_range}";

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        $user->update([
            'state' => 'done',
        ]);

        // ⬇️ ВАЖЛИВО: викликаємо одразу
        $this->sendWeather($user);
    }
    private function sendWeather(TelegramUser $user): void
    {
        Log::debug('STEP 3: sendWeather entered', ['city' => $user->location]);

        $data1 = $this->weather->getNormalizedWeather($user->location);
        Log::debug('WEATHER API 1', ['ok' => (bool) $data1]);

        $data2 = $this->weatherApi2->getNormalizedWeather($user->location);
        Log::debug('WEATHER API 2', ['ok' => (bool) $data2]);

        $sources = array_filter([$data1, $data2]);

        if (count($sources) === 0) {
            $this->telegram->sendMessage([
                'chat_id' => $user->telegram_id,
                'text' => '❌ Не вдалося отримати погоду з жодного сервісу',
            ]);
            return;
        }

        // Якщо тільки одне джерело — беремо його
        if (count($sources) === 1) {
            $w = array_values($sources)[0];
        } else {
            // Усереднюємо
            $w = [
                'temp'        => round(($data1['temp'] + $data2['temp']) / 2, 1),
                'feels_like'  => round(($data1['feels_like'] + $data2['feels_like']) / 2, 1),
                'humidity'    => round(($data1['humidity'] + $data2['humidity']) / 2),
                'wind'        => round(($data1['wind'] + $data2['wind']) / 2, 1),
                'description' => $data1['description'] ?? $data2['description'],
            ];
        }

        $this->telegram->sendMessage([
            'chat_id' => $user->telegram_id,
            'text' =>
                "🌤 Погода у {$user->location}\n\n" .
                "🌡 Температура: {$w['temp']}°C\n" .
                "🤍 Відчувається як: {$w['feels_like']}°C\n" .
                "💧 Вологість: {$w['humidity']}%\n" .
                "🌬 Вітер: {$w['wind']} м/с\n" .
                "📖 {$w['description']}",
            'reply_markup' => json_encode([
                'keyboard' => [
                    [
                        ['text' => '🌤 Зараз'],
                        ['text' => '📅 Завтра'],
                    ],
                    [
                        ['text' => '📆 На 3 дні'],
                        ['text' => '🔄 Почати заново'],
                    ],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ]),

        ]);

    }

    private function avg(?float $a, ?float $b): ?float
    {
        if ($a === null || $b === null) {
            return null;
        }

        return round(($a + $b) / 2, 1);
    }
private function weatherKeyboard(): array
{
    return [
        'inline_keyboard' => [
            [
                ['text' => '🌤 Завтра', 'callback_data' => 'weather_tomorrow'],
                ['text' => '📅 На 3 дні', 'callback_data' => 'weather_3days'],
            ],
            [
                ['text' => '🏙 Інше місто', 'callback_data' => 'change_city'],
            ],
        ],
    ];
}

    private function sendTomorrowWeather(TelegramUser $user, int $chatId): void
    {
        Log::debug('SEND TOMORROW WEATHER', [
            'city' => $user->location,
        ]);

        $weather = $this->weather->getTomorrow($user->location);

        if (!$weather) {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '❌ Не вдалося отримати погоду на завтра',
            ]);
            return;
        }

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' =>
                "🌤 Погода завтра у {$user->location}\n\n" .
                "🌡 Температура: {$weather['temp']}°C\n" .
                "🤍 Відчувається як: {$weather['feels']}°C\n" .
                "💧 Вологість: {$weather['humidity']}%\n" .
                "🌬 Вітер: {$weather['wind']} м/с\n" .
                "📖 {$weather['description']}",
        ]);
    }
    private function sendThreeDaysWeather(TelegramUser $user, int $chatId): void
    {
        $forecast = $this->weather->getThreeDays($user->location);

        if (!$forecast) {
            $this->sendError($chatId);
            return;
        }

        $text = "📆 Погода на 3 дні у {$user->location}\n\n";

        foreach ($forecast as $day) {
            $text .=
                "📅 {$day['date']}\n" .
                "🌡 {$day['temp']}°C (відч. {$day['feels']}°C)\n" .
                "🌬 {$day['wind']} м/с\n" .
                "📖 {$day['desc']}\n\n";
        }

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode($this->weatherKeyboard()),
        ]);
    }
    private function sendError(int $chatId, string $text = '❌ Сталася помилка'): void
    {
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }
    private function sendWeatherMessage(
        int $chatId,
        string $city,
        array $weather,
        string $title
    ): void {
        $message = "{$title}\n\n";
        $message .= "🌤 Погода у {$city}\n";
        $message .= "🌡 Температура: {$weather['temp']}°C\n";
        $message .= "🤍 Відчувається як: {$weather['feels_like']}°C\n";
        $message .= "💧 Вологість: {$weather['humidity']}%\n";
        $message .= "🌬 Вітер: {$weather['wind']} м/с\n";
        $message .= "📖 {$weather['description']}";

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
        ]);
    }
}
