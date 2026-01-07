<?php


namespace App\Services;

use App\Models\TelegramUser;

class TelegramStateService
{
    public function reset(TelegramUser $user): void
    {
        $user->update([
            'state' => 'waiting_city',
            'location' => null,
            'date' => null,
            'time_range' => null,
        ]);
    }

    public function handleCity(TelegramUser $user, string $city): void
    {
        $user->update([
            'location' => $city,
            'state' => 'waiting_date',
        ]);
    }

    public function handleDate(TelegramUser $user, string $date): void
    {
        $user->update([
            'date' => $date,
            'state' => 'waiting_time',
        ]);
    }

    public function handleTime(TelegramUser $user, string $time): void
    {
        $user->update([
            'time_range' => $time,
            'state' => 'done',
        ]);
    }
}
