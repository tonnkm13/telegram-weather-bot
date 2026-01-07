<?php

namespace App\Services\Weather;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherApi2Service implements WeatherProviderInterface
{
    public function getNormalizedWeather(string $city): ?array
    {
        $response = Http::get('https://api.weatherapi.com/v1/current.json', [
            'key'  => config('services.weatherapi.key'),
            'q'    => $city,
            'lang' => 'uk',
        ]);

        if (!$response->ok()) {
            Log::warning('WEATHERAPI FAILED', [
                'city' => $city,
                'status' => $response->status(),
            ]);
            return null;
        }

        $data = $response->json();

        if (!isset($data['current'])) {
            Log::warning('WEATHERAPI INVALID RESPONSE', $data);
            return null;
        }

        return [
            'temp'        => round($data['current']['temp_c'], 1),
            'feels_like'  => round($data['current']['feelslike_c'], 1),
            'humidity'    => (int) $data['current']['humidity'],
            'wind'        => round($data['current']['wind_kph'] / 3.6, 1), // km/h → m/s
            'description' => mb_strtolower($data['current']['condition']['text']),
        ];
    }
}
