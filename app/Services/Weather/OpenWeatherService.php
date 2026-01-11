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
    public function getTomorrow(string $city): ?array
    {
        $forecast = $this->getNormalizedWeather($city);

        if (!$forecast || empty($forecast['list'])) {
            return null;
        }

        // беремо прогноз приблизно через 24 години
        foreach ($forecast['list'] as $item) {
            if (str_contains($item['dt_txt'], '12:00:00')) {
                return $this->mapForecastItem($item);
            }
        }

        return null;
    }
    public function getThreeDays(string $city): ?array
    {
        $forecast = $this->getNormalizedWeather($city);

        if (!$forecast || empty($forecast['list'])) {
            return null;
        }

        $days = [];

        foreach ($forecast['list'] as $item) {
            $date = substr($item['dt_txt'], 0, 10);

            if (!isset($days[$date]) && count($days) < 3) {
                $days[$date] = $this->mapForecastItem($item);
            }
        }

        return array_values($days);
    }

    private function mapForecastItem(array $item): array
    {
        return [
            'temp' => round($item['main']['temp'], 1),
            'feels_like' => round($item['main']['feels_like'], 1),
            'humidity' => $item['main']['humidity'],
            'wind' => $item['wind']['speed'],
            'description' => $item['weather'][0]['description'] ?? '',
        ];
    }

}
