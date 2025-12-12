<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

/**
 * Weather Service using Open-Meteo API (free, no API key required)
 */
class WeatherService
{
    private const GEOCODING_API = 'https://geocoding-api.open-meteo.com/v1/search';
    private const WEATHER_API = 'https://api.open-meteo.com/v1/forecast';

    private array $weatherCodes = [
        0 => ['description' => 'Klar', 'icon' => 'sunny'],
        1 => ['description' => 'Überwiegend klar', 'icon' => 'sunny'],
        2 => ['description' => 'Teilweise bewölkt', 'icon' => 'partly_cloudy'],
        3 => ['description' => 'Bewölkt', 'icon' => 'cloudy'],
        45 => ['description' => 'Nebel', 'icon' => 'fog'],
        48 => ['description' => 'Reifnebel', 'icon' => 'fog'],
        51 => ['description' => 'Leichter Nieselregen', 'icon' => 'drizzle'],
        53 => ['description' => 'Mäßiger Nieselregen', 'icon' => 'drizzle'],
        55 => ['description' => 'Dichter Nieselregen', 'icon' => 'drizzle'],
        56 => ['description' => 'Gefrierender Nieselregen', 'icon' => 'drizzle'],
        57 => ['description' => 'Starker gefrierender Nieselregen', 'icon' => 'drizzle'],
        61 => ['description' => 'Leichter Regen', 'icon' => 'rain'],
        63 => ['description' => 'Mäßiger Regen', 'icon' => 'rain'],
        65 => ['description' => 'Starker Regen', 'icon' => 'rain'],
        66 => ['description' => 'Gefrierender Regen', 'icon' => 'rain'],
        67 => ['description' => 'Starker gefrierender Regen', 'icon' => 'rain'],
        71 => ['description' => 'Leichter Schneefall', 'icon' => 'snow'],
        73 => ['description' => 'Mäßiger Schneefall', 'icon' => 'snow'],
        75 => ['description' => 'Starker Schneefall', 'icon' => 'snow'],
        77 => ['description' => 'Schneegriesel', 'icon' => 'snow'],
        80 => ['description' => 'Leichte Regenschauer', 'icon' => 'showers'],
        81 => ['description' => 'Mäßige Regenschauer', 'icon' => 'showers'],
        82 => ['description' => 'Heftige Regenschauer', 'icon' => 'showers'],
        85 => ['description' => 'Leichte Schneeschauer', 'icon' => 'snow'],
        86 => ['description' => 'Starke Schneeschauer', 'icon' => 'snow'],
        95 => ['description' => 'Gewitter', 'icon' => 'thunderstorm'],
        96 => ['description' => 'Gewitter mit Hagel', 'icon' => 'thunderstorm'],
        99 => ['description' => 'Gewitter mit starkem Hagel', 'icon' => 'thunderstorm'],
    ];

    /**
     * Search for a location by name
     */
    public function searchLocation(string $query): array
    {
        $url = self::GEOCODING_API . '?' . http_build_query([
            'name' => $query,
            'count' => 5,
            'language' => 'de',
            'format' => 'json',
        ]);

        $response = $this->httpGet($url);

        if (!$response || !isset($response['results'])) {
            return [];
        }

        return array_map(fn($result) => [
            'name' => $result['name'],
            'country' => $result['country'] ?? '',
            'admin1' => $result['admin1'] ?? '',
            'latitude' => $result['latitude'],
            'longitude' => $result['longitude'],
        ], $response['results']);
    }

    /**
     * Get weather for coordinates
     */
    public function getWeather(float $latitude, float $longitude): ?array
    {
        $url = self::WEATHER_API . '?' . http_build_query([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,weather_code,wind_speed_10m,wind_direction_10m',
            'hourly' => 'temperature_2m,weather_code',
            'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,sunrise,sunset,precipitation_probability_max',
            'timezone' => 'auto',
            'forecast_days' => 5,
        ]);

        $response = $this->httpGet($url);

        if (!$response) {
            return null;
        }

        return $this->formatWeatherResponse($response);
    }

    /**
     * Get weather by location name
     */
    public function getWeatherByLocation(string $location): ?array
    {
        $locations = $this->searchLocation($location);

        if (empty($locations)) {
            return null;
        }

        $loc = $locations[0];
        $weather = $this->getWeather($loc['latitude'], $loc['longitude']);

        if ($weather) {
            $weather['location'] = [
                'name' => $loc['name'],
                'country' => $loc['country'],
                'admin1' => $loc['admin1'],
                'latitude' => $loc['latitude'],
                'longitude' => $loc['longitude'],
            ];
        }

        return $weather;
    }

    /**
     * Format weather API response
     */
    private function formatWeatherResponse(array $response): array
    {
        $current = $response['current'] ?? [];
        $daily = $response['daily'] ?? [];
        $hourly = $response['hourly'] ?? [];

        $weatherCode = $current['weather_code'] ?? 0;
        $weatherInfo = $this->weatherCodes[$weatherCode] ?? ['description' => 'Unbekannt', 'icon' => 'unknown'];

        $result = [
            'current' => [
                'temperature' => round($current['temperature_2m'] ?? 0),
                'feels_like' => round($current['apparent_temperature'] ?? 0),
                'humidity' => $current['relative_humidity_2m'] ?? 0,
                'wind_speed' => round($current['wind_speed_10m'] ?? 0),
                'wind_direction' => $current['wind_direction_10m'] ?? 0,
                'weather_code' => $weatherCode,
                'description' => $weatherInfo['description'],
                'icon' => $weatherInfo['icon'],
            ],
            'forecast' => [],
            'hourly' => [],
        ];

        // Daily forecast
        if (!empty($daily['time'])) {
            for ($i = 0; $i < min(5, count($daily['time'])); $i++) {
                $code = $daily['weather_code'][$i] ?? 0;
                $info = $this->weatherCodes[$code] ?? ['description' => 'Unbekannt', 'icon' => 'unknown'];

                $result['forecast'][] = [
                    'date' => $daily['time'][$i],
                    'day' => $this->getDayName($daily['time'][$i]),
                    'temp_max' => round($daily['temperature_2m_max'][$i] ?? 0),
                    'temp_min' => round($daily['temperature_2m_min'][$i] ?? 0),
                    'weather_code' => $code,
                    'description' => $info['description'],
                    'icon' => $info['icon'],
                    'precipitation_probability' => $daily['precipitation_probability_max'][$i] ?? 0,
                    'sunrise' => $daily['sunrise'][$i] ?? null,
                    'sunset' => $daily['sunset'][$i] ?? null,
                ];
            }
        }

        // Hourly forecast (next 24 hours)
        if (!empty($hourly['time'])) {
            $now = time();
            $count = 0;
            for ($i = 0; $i < count($hourly['time']) && $count < 24; $i++) {
                $hourTime = strtotime($hourly['time'][$i]);
                if ($hourTime >= $now) {
                    $code = $hourly['weather_code'][$i] ?? 0;
                    $info = $this->weatherCodes[$code] ?? ['description' => 'Unbekannt', 'icon' => 'unknown'];

                    $result['hourly'][] = [
                        'time' => $hourly['time'][$i],
                        'hour' => date('H:i', $hourTime),
                        'temperature' => round($hourly['temperature_2m'][$i] ?? 0),
                        'weather_code' => $code,
                        'icon' => $info['icon'],
                    ];
                    $count++;
                }
            }
        }

        return $result;
    }

    /**
     * Get German day name
     */
    private function getDayName(string $date): string
    {
        $days = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
        $dayNum = (int) date('w', strtotime($date));
        return $days[$dayNum];
    }

    /**
     * HTTP GET request
     */
    private function httpGet(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        return json_decode($response, true);
    }
}
