<?php

namespace App\Services\Weather;

class WeatherAggregatorService
{
    public function average(array $sources): array
    {
        return [
            'temp' => collect($sources)->avg('temp'),
            'humidity' => collect($sources)->avg('humidity'),
            'wind' => collect($sources)->avg('wind'),
        ];
    }
}

