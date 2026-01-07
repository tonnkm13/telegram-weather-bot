<?php


namespace App\Services\Weather;

interface WeatherProviderInterface
{
    /**
     * Повертає нормалізовані дані погоди
     *
     * @return array{
     *   temp: float,
     *   feels_like: float,
     *   humidity: int,
     *   wind: float,
     *   description: string
     * }|null
     */
    public function getNormalizedWeather(string $city): ?array;
}
