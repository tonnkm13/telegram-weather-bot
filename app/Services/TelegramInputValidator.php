<?php

namespace App\Services;

use Carbon\Carbon;

class TelegramInputValidator
{
    /**
     * Перевірка міста
     */
    public static function city(string $text): bool
    {
        return mb_strlen($text) >= 2 && preg_match('/^[\p{L}\s\-]+$/u', $text);
    }

    /**
     * Перевірка дати YYYY-MM-DD
     */
    public static function date(string $text): bool
    {
        try {
            $date = Carbon::createFromFormat('Y-m-d', $text);
            return $date->isToday() || $date->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Перевірка часу HH:MM-HH:MM
     */
    public static function timeRange(string $text): bool
    {
        if (!preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $text)) {
            return false;
        }

        [$start, $end] = explode('-', $text);

        return strtotime($start) < strtotime($end);
    }
}

