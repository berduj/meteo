<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\LocationCoordinates;
use App\Service\Forecast\ForecastAggregator;
use App\Service\Geocode\GeocodeService;
use App\Service\HourlyForecast\HourlyForecastAggregator;
use App\Service\Weather\WeatherAggregator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ApiWeatherController extends AbstractController
{
    public function __construct(
        private WeatherAggregator $weatherAggregator,
        private ForecastAggregator $forecastAggregator,
        private HourlyForecastAggregator $hourlyForecastAggregator,
    ) {
    }

    #[Route('/api/weather', name: 'api_weather', defaults: ['location' => null])]
    #[Route('/api/weather/{location}', name: 'api_weather_location')]
    public function __invoke(?string $location, GeocodeService $geocodeService): JsonResponse
    {
        try {
            if ($location !== null) {
                $locationCoordinates = $geocodeService->get($location);
            } else {
                $locationCoordinates = new LocationCoordinates(
                    $this->getParameter('meteo_name'),
                    $this->getParameter('meteo_latitude'),
                    $this->getParameter('meteo_longitude'),
                    $this->getParameter('meteo_timezone'),
                );
            }
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        }

        $current = [];
        foreach ($this->weatherAggregator->getAll($locationCoordinates) as $weather) {
            if (!$weather->enabled) {
                continue;
            }
            $current[] = [
                'provider' => $weather->provider,
                'temperature' => $weather->temperature,
                'description' => $weather->description,
                'humidity' => $weather->humidity,
                'wind' => $weather->wind,
                'sourceName' => $weather->sourceName,
                'logoUrl' => $weather->logoUrl,
                'sourceUrl' => $weather->sourceUrl,
                'icon' => $weather->icon,
            ];
        }

        $forecast = [];
        foreach ($this->forecastAggregator->getAll($locationCoordinates) as $forecastData) {
            $forecast[$forecastData->provider][] = [
                'date' => $forecastData->date->format('Y-m-d'),
                'tmin' => $forecastData->tmin,
                'tmax' => $forecastData->tmax,
                'icon' => $forecastData->icon,
                'emoji' => $forecastData->emoji,
            ];
        }

        $hourly = [];
        foreach ($this->hourlyForecastAggregator->getAll($locationCoordinates) as $provider => $hourlyData) {
            $hourly[$provider] = array_map(fn ($h) => [
                'time' => $h->time->format('c'),
                'temperature' => $h->temperature,
                'description' => $h->description,
                'emoji' => $h->emoji,
            ], $hourlyData);
        }

        return $this->json([
            'location' => $locationCoordinates->toArray(),
            'generatedAt' => (new \DateTimeImmutable())->format('c'),
            'current' => $current,
            'forecast' => $forecast,
            'hourly' => $hourly,
        ]);
    }
}
