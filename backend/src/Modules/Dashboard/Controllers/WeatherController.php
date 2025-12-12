<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\Dashboard\Services\WeatherService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class WeatherController
{
    public function __construct(
        private readonly WeatherService $weatherService
    ) {}

    /**
     * Search for locations
     */
    public function searchLocation(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $query = $params['q'] ?? '';

        if (empty($query) || strlen($query) < 2) {
            return JsonResponse::error('Query must be at least 2 characters', 400);
        }

        $locations = $this->weatherService->searchLocation($query);

        return JsonResponse::success($locations);
    }

    /**
     * Get weather by coordinates
     */
    public function getWeather(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();

        // Get weather by coordinates
        if (isset($params['lat']) && isset($params['lon'])) {
            $latitude = (float) $params['lat'];
            $longitude = (float) $params['lon'];

            $weather = $this->weatherService->getWeather($latitude, $longitude);

            if (!$weather) {
                return JsonResponse::error('Failed to fetch weather data', 500);
            }

            return JsonResponse::success($weather);
        }

        // Get weather by location name
        if (isset($params['location'])) {
            $weather = $this->weatherService->getWeatherByLocation($params['location']);

            if (!$weather) {
                return JsonResponse::error('Location not found', 404);
            }

            return JsonResponse::success($weather);
        }

        return JsonResponse::error('Either lat/lon or location parameter is required', 400);
    }
}
