<?php

namespace App\Services\Weather;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenWeatherService implements WeatherProviderInterface
{
    public function getNormalizedWeather(string $city): ?array
    {
        $response = Http::get('https://api.openweathermap.org/data/2.5/weather', [
            'q'     => $city,
            'appid'=> config('services.openweather.key'),
            'units'=> 'metric',
            'lang' => 'uk',
        ]);

        if (!$response->ok()) {
            Log::warning('OPENWEATHER FAILED', [
                'city' => $city,
                'status' => $response->status(),
            ]);
            return null;
        }

        $data = $response->json();

        return [
            'temp'        => round($data['main']['temp'], 1),
            'feels_like'  => round($data['main']['feels_like'], 1),
            'humidity'    => (int) $data['main']['humidity'],
            'wind'        => round($data['wind']['speed'], 1),
            'description' => $data['weather'][0]['description'] ?? '—',
        ];
    }
}
