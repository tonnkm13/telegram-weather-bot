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
    public function getForecast(string $city): ?array
    {
        $response = Http::get('https://api.openweathermap.org/data/2.5/forecast', [
            'q' => $city,
            'appid' => config('services.openweather.key'),
            'units' => 'metric',
            'lang' => 'uk',
        ]);

        if (!$response->ok()) {
            Log::error('OPENWEATHER FORECAST ERROR', $response->json());
            return null;
        }

        return $response->json();
    }

    public function getTomorrow(string $city): ?array
    {
        $forecast = $this->getForecast($city);

        if (!$forecast || empty($forecast['list'])) {
            return null;
        }

        $tomorrow = now()->addDay()->format('Y-m-d');

        foreach ($forecast['list'] as $item) {
            if (str_starts_with($item['dt_txt'], $tomorrow)
                && str_contains($item['dt_txt'], '12:00:00')) {

                return $this->mapForecastItem($item);
            }
        }

        return null;
    }
    public function getThreeDays(string $city): ?array
    {
        $forecast = $this->getForecast($city);

        if (!$forecast || empty($forecast['list'])) {
            return null;
        }

        $days = [];

        foreach ($forecast['list'] as $item) {
            $date = substr($item['dt_txt'], 0, 10);

            if (!isset($days[$date]) && str_contains($item['dt_txt'], '12:00:00')) {
                $days[$date] = [
                    'date' => $date,
                    ...$this->mapForecastItem($item),
                ];
            }

            if (count($days) === 3) {
                break;
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
