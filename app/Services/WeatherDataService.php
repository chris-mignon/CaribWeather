<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WeatherDataService
{
    public function __construct(private readonly GoogleAirQualityService $googleAirQuality)
    {
    }

    public function current(string $location): array
    {
        $location = $this->cleanLocation($location);
        $ttl = now()->addMinutes(config('services.caribweather.cache_ttl_minutes', 10));

        return Cache::remember('weather.current.'.sha1($location), $ttl, function () use ($location) {
            if (! config('services.caribweather.use_live_providers')) {
                return $this->fallbackWeather($location);
            }

            try {
                $place = $this->resolveLocation($location);
                $forecast = $this->fetchForecast($place['latitude'], $place['longitude']);
                $marine = $this->fetchMarine($place['latitude'], $place['longitude']);
                $aqi = $this->fetchAirQuality($place['latitude'], $place['longitude']);

                return $this->mapCurrentWeather($location, $place, $forecast, $marine, $aqi);
            } catch (ConnectionException) {
                return $this->fallbackWeather($location);
            } catch (\Throwable) {
                return $this->fallbackWeather($location);
            }
        });
    }

    public function search(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return ['results' => $this->defaultLocations()];
        }

        if (! config('services.caribweather.use_live_providers')) {
            return [
                'results' => collect($this->defaultLocations())
                    ->filter(fn (array $location) => Str::contains(Str::lower($location['name']), Str::lower($query)))
                    ->values()
                    ->all(),
            ];
        }

        try {
            $response = Http::timeout(6)->get('https://geocoding-api.open-meteo.com/v1/search', [
                'name' => $query,
                'count' => 8,
                'language' => 'en',
                'format' => 'json',
            ])->throw()->json();

            return [
                'results' => collect($response['results'] ?? [])
                    ->map(fn (array $result) => [
                        'name' => trim(collect([$result['name'] ?? null, $result['admin1'] ?? null, $result['country'] ?? null])->filter()->implode(', ')),
                        'coordinates' => [(float) $result['latitude'], (float) $result['longitude']],
                    ])
                    ->filter(fn (array $result) => $this->isCaribbeanCoordinate($result['coordinates'][0], $result['coordinates'][1]))
                    ->values()
                    ->all(),
            ];
        } catch (\Throwable) {
            return ['results' => $this->defaultLocations()];
        }
    }

    public function historical(string $location, string $start, string $end): array
    {
        $location = $this->cleanLocation($location);
        $start = $this->cleanDate($start, now()->subDays(7)->toDateString());
        $end = $this->cleanDate($end, now()->subDay()->toDateString());
        $ttl = now()->addMinutes(config('services.caribweather.historical_cache_ttl_minutes', 60));

        $providerKey = config('services.meteostat.key') ? 'meteostat' : 'open-meteo';

        return Cache::remember('weather.historical.'.sha1($providerKey.'|'.$location.'|'.$start.'|'.$end), $ttl, function () use ($location, $start, $end) {
            if (! config('services.caribweather.use_live_providers')) {
                return $this->fallbackHistorical($location, $start, $end);
            }

            try {
                $place = $this->resolveLocation($location);

                return $this->fetchMeteostatHistorical($place, $start, $end)
                    ?? $this->fetchOpenMeteoHistorical($place, $start, $end);
            } catch (\Throwable) {
                return $this->fallbackHistorical($location, $start, $end);
            }
        });
    }

    public function fallbackWeather(string $location): array
    {
        $coordinates = $this->guessCoordinates($location);

        return [
            'location' => $location,
            'coordinates' => $coordinates,
            'cache' => true,
            'source' => 'fallback',
            'lastUpdated' => now()->format('M j, Y, g:i A'),
            'current' => [
                'tempC' => 30,
                'feelsLikeC' => 34,
                'humidity' => 78,
                'windKph' => 24,
                'windDirection' => 'ESE',
                'windDegrees' => 112,
                'uvIndex' => 9,
                'aqi' => 42,
                'aqiAdvisory' => 'Air quality is generally acceptable.',
                'rainChance' => 48,
                'cloudCover' => 62,
                'summary' => 'Warm, breezy, and humid with scattered showers nearby',
            ],
            'marine' => [
                'waveHeightM' => 1.6,
                'seaTempC' => 28.7,
                'swellDirection' => 'ENE',
            ],
            'sun' => [
                'sunrise' => '5:43 AM',
                'sunset' => '6:31 PM',
            ],
            'hourly' => collect(range(0, 7))->map(fn (int $index) => [
                'time' => str_pad((string) ($index * 3 + 6), 2, '0', STR_PAD_LEFT).':00',
                'tempC' => 27 + (int) round(sin($index / 2) * 3 + $index / 3),
                'rain' => [28, 34, 42, 54, 48, 36, 30, 24][$index],
            ])->all(),
            'daily' => collect(['Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun', 'Mon'])->map(fn (string $day, int $index) => [
                'day' => $day,
                'condition' => ['Showers', 'Partly Cloudy', 'Humid', 'Thunderstorms', 'Breezy', 'Sunny', 'Scattered Rain'][$index],
                'highC' => 30 + ($index % 3),
                'lowC' => 24 + ($index % 2),
                'rain' => [55, 35, 30, 62, 42, 24, 39][$index],
            ])->all(),
        ];
    }

    private function mapCurrentWeather(string $requestedLocation, array $place, array $forecast, array $marine, array $aqi): array
    {
        $current = $forecast['current'] ?? [];
        $daily = $forecast['daily'] ?? [];
        $hourly = $forecast['hourly'] ?? [];
        $dailyUv = Arr::first($daily['uv_index_max'] ?? [], fn ($value) => $value !== null) ?? 0;
        $windDegrees = (int) round((float) ($current['wind_direction_10m'] ?? 0));

        return [
            'location' => $place['name'] ?: $requestedLocation,
            'coordinates' => [(float) $place['latitude'], (float) $place['longitude']],
            'cache' => false,
            'source' => 'open-meteo',
            'lastUpdated' => now()->format('M j, Y, g:i A'),
            'current' => [
                'tempC' => $this->roundValue($current['temperature_2m'] ?? null, 30),
                'feelsLikeC' => $this->roundValue($current['apparent_temperature'] ?? null, 34),
                'humidity' => $this->roundValue($current['relative_humidity_2m'] ?? null, 78),
                'windKph' => $this->roundValue($current['wind_speed_10m'] ?? null, 24),
                'windDirection' => $this->cardinalDirection($windDegrees),
                'windDegrees' => $windDegrees,
                'uvIndex' => $this->roundValue($dailyUv, 9),
                'aqi' => $aqi['value'],
                'aqiAdvisory' => $aqi['advisory'],
                'rainChance' => $this->roundValue(Arr::first($hourly['precipitation_probability'] ?? [], fn ($value) => $value !== null), 48),
                'cloudCover' => $this->roundValue($current['cloud_cover'] ?? null, 62),
                'summary' => $this->weatherCodeLabel((int) ($current['weather_code'] ?? 2)),
            ],
            'marine' => [
                'waveHeightM' => $this->roundValue(data_get($marine, 'current.wave_height'), 1.6, 1),
                'seaTempC' => $this->roundValue(data_get($marine, 'current.sea_surface_temperature'), 28.7, 1),
                'swellDirection' => $this->cardinalDirection((int) round((float) data_get($marine, 'current.swell_wave_direction', 70))),
            ],
            'sun' => [
                'sunrise' => $this->timeLabel(Arr::first($daily['sunrise'] ?? []), '5:43 AM'),
                'sunset' => $this->timeLabel(Arr::first($daily['sunset'] ?? []), '6:31 PM'),
            ],
            'hourly' => collect($hourly['time'] ?? [])->take(24)->values()->map(fn (string $time, int $index) => [
                'time' => Carbon::parse($time)->format('H:i'),
                'tempC' => $this->roundValue($hourly['temperature_2m'][$index] ?? null, 28),
                'rain' => $this->roundValue($hourly['precipitation_probability'][$index] ?? null, 0),
            ])->all(),
            'daily' => collect($daily['time'] ?? [])->take(7)->values()->map(fn (string $date, int $index) => [
                'day' => Carbon::parse($date)->format('D'),
                'condition' => $this->weatherCodeLabel((int) ($daily['weather_code'][$index] ?? 2)),
                'highC' => $this->roundValue($daily['temperature_2m_max'][$index] ?? null, 30),
                'lowC' => $this->roundValue($daily['temperature_2m_min'][$index] ?? null, 24),
                'rain' => $this->roundValue($daily['precipitation_probability_max'][$index] ?? null, 0),
            ])->all(),
        ];
    }

    private function resolveLocation(string $location): array
    {
        if (preg_match('/(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/', $location, $matches)) {
            return ['name' => $location, 'latitude' => (float) $matches[1], 'longitude' => (float) $matches[2]];
        }

        $response = Http::timeout(6)->get('https://geocoding-api.open-meteo.com/v1/search', [
            'name' => $location,
            'count' => 10,
            'language' => 'en',
            'format' => 'json',
        ])->throw()->json();

        $result = collect($response['results'] ?? [])->first(fn (array $result) => $this->isCaribbeanCoordinate((float) $result['latitude'], (float) $result['longitude']))
            ?? Arr::first($response['results'] ?? []);

        if (! $result) {
            $coordinates = $this->guessCoordinates($location);

            return ['name' => $location, 'latitude' => $coordinates[0], 'longitude' => $coordinates[1]];
        }

        return [
            'name' => trim(collect([$result['name'] ?? null, $result['admin1'] ?? null, $result['country'] ?? null])->filter()->implode(', ')),
            'latitude' => (float) $result['latitude'],
            'longitude' => (float) $result['longitude'],
        ];
    }

    private function fetchForecast(float $latitude, float $longitude): array
    {
        return Http::timeout(10)->get('https://api.open-meteo.com/v1/forecast', [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,rain,cloud_cover,weather_code,wind_speed_10m,wind_direction_10m',
            'hourly' => 'temperature_2m,precipitation_probability,weather_code',
            'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,uv_index_max,sunrise,sunset',
            'timezone' => 'auto',
            'forecast_days' => 7,
        ])->throw()->json();
    }

    private function fetchMarine(float $latitude, float $longitude): array
    {
        return Http::timeout(8)->get('https://marine-api.open-meteo.com/v1/marine', [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current' => 'wave_height,sea_surface_temperature,swell_wave_direction',
            'timezone' => 'auto',
        ])->throw()->json();
    }

    private function fetchAirQuality(float $latitude, float $longitude): array
    {
        // Prefer Google Air Quality when an OAuth token is configured.
        if (config('services.google.air_quality_token')) {
            try {
                $rawAqi = $this->googleAirQuality->currentRawAqi($latitude, $longitude);
                if ($rawAqi !== null) {
                    $scale = $this->googleRawAqiToScale($rawAqi);
                    return ['value' => $scale, 'advisory' => $this->aqiAdvisory($scale)];
                }
            } catch (\Throwable) {
                // fall through to OpenWeather
            }
        }

        $key = config('services.openweather.key');

        if (! $key) {
            return ['value' => 42, 'advisory' => 'AQI provider key is not configured; showing fallback advisory.'];
        }

        try {
            $response = Http::timeout(8)->get('https://api.openweathermap.org/data/2.5/air_pollution', [
                'lat' => $latitude,
                'lon' => $longitude,
                'appid' => $key,
            ])->throw()->json();

            $aqi = (int) data_get($response, 'list.0.main.aqi', 1);

            return ['value' => $this->openWeatherAqiValue($aqi), 'advisory' => $this->aqiAdvisory($aqi)];
        } catch (\Throwable) {
            return ['value' => 42, 'advisory' => 'AQI provider unavailable; showing fallback advisory.'];
        }
    }

    private function googleRawAqiToScale(int $rawAqi): int
    {
        // Generic AQI (0-500) to our 1..5 buckets (matches existing UI expectations).
        return match (true) {
            $rawAqi <= 50 => 1,
            $rawAqi <= 100 => 2,
            $rawAqi <= 150 => 3,
            $rawAqi <= 200 => 4,
            default => 5,
        };
    }

    private function fetchMeteostatHistorical(array $place, string $start, string $end): ?array
    {
        $key = config('services.meteostat.key');

        if (! $key) {
            return null;
        }

        try {
            $client = Http::timeout(12)->withHeaders([
                'X-RapidAPI-Key' => $key,
                'X-RapidAPI-Host' => config('services.meteostat.host', 'meteostat.p.rapidapi.com'),
            ]);

            $baseUrl = rtrim((string) config('services.meteostat.base_url', 'https://meteostat.p.rapidapi.com'), '/');
            $stationResponse = $client->get($baseUrl.'/stations/nearby', [
                'lat' => $place['latitude'],
                'lon' => $place['longitude'],
                'limit' => 1,
            ])->throw()->json();

            $stationId = data_get($stationResponse, 'data.0.id');
            if (! $stationId) {
                return null;
            }

            $dailyResponse = $client->get($baseUrl.'/stations/daily', [
                'station' => $stationId,
                'start' => $start,
                'end' => $end,
            ])->throw()->json();

            $rows = collect($dailyResponse['data'] ?? []);
            if ($rows->isEmpty()) {
                return null;
            }

            return [
                'location' => $place['name'],
                'station' => $stationId,
                'start' => $start,
                'end' => $end,
                'labels' => $rows->map(fn (array $row) => Carbon::parse($row['date'])->format('M j'))->all(),
                'highs' => $this->numericList($rows->pluck('tmax')->all()),
                'means' => $this->numericList($rows->pluck('tavg')->all()),
                'lows' => $this->numericList($rows->pluck('tmin')->all()),
                'rainfall' => $this->numericList($rows->pluck('prcp')->all()),
                'wind' => $this->numericList($rows->pluck('wspd')->all()),
                'humidity' => array_fill(0, $rows->count(), null),
                'pressure' => $this->numericList($rows->pluck('pres')->all()),
                'cache' => false,
                'source' => 'meteostat',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchOpenMeteoHistorical(array $place, string $start, string $end): array
    {
        $response = Http::timeout(10)->get('https://archive-api.open-meteo.com/v1/archive', [
            'latitude' => $place['latitude'],
            'longitude' => $place['longitude'],
            'start_date' => $start,
            'end_date' => $end,
            'daily' => 'temperature_2m_max,temperature_2m_min,temperature_2m_mean,precipitation_sum,wind_speed_10m_max',
            'timezone' => 'auto',
        ])->throw()->json();

        $daily = $response['daily'] ?? [];
        $labels = collect($daily['time'] ?? [])->map(fn (string $date) => Carbon::parse($date)->format('M j'))->all();

        return [
            'location' => $place['name'],
            'start' => $start,
            'end' => $end,
            'labels' => $labels,
            'highs' => $this->numericList($daily['temperature_2m_max'] ?? []),
            'means' => $this->numericList($daily['temperature_2m_mean'] ?? []),
            'lows' => $this->numericList($daily['temperature_2m_min'] ?? []),
            'rainfall' => $this->numericList($daily['precipitation_sum'] ?? []),
            'wind' => $this->numericList($daily['wind_speed_10m_max'] ?? []),
            'humidity' => array_fill(0, count($labels), null),
            'pressure' => array_fill(0, count($labels), null),
            'cache' => false,
            'source' => 'open-meteo-archive',
        ];
    }

    private function fallbackHistorical(string $location, string $start, string $end): array
    {
        return [
            'location' => $location,
            'start' => $start,
            'end' => $end,
            'labels' => ['Jun 1', 'Jun 2', 'Jun 3', 'Jun 4', 'Jun 5', 'Jun 6', 'Jun 7'],
            'highs' => [31, 32, 31, 30, 33, 32, 31],
            'means' => [28, 29, 28, 27, 29, 29, 28],
            'lows' => [25, 25, 24, 24, 25, 26, 25],
            'rainfall' => [4, 12, 0, 18, 6, 2, 9],
            'wind' => [22, 26, 18, 31, 24, 20, 23],
            'humidity' => [78, 82, 76, 85, 80, 74, 79],
            'pressure' => [1012, 1011, 1013, 1010, 1012, 1014, 1011],
            'cache' => true,
            'source' => 'fallback',
        ];
    }

    private function cleanLocation(string $location): string
    {
        $location = trim(strip_tags($location));

        return $location !== '' ? Str::limit($location, 120, '') : "St. George's, Grenada";
    }

    private function cleanDate(string $date, string $fallback): string
    {
        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function defaultLocations(): array
    {
        return [
            ['name' => "St. George's, Grenada", 'coordinates' => [12.1165, -61.6790]],
            ['name' => 'Bridgetown, Barbados', 'coordinates' => [13.0975, -59.6167]],
            ['name' => 'Castries, Saint Lucia', 'coordinates' => [14.0101, -60.9875]],
            ['name' => 'Kingston, Jamaica', 'coordinates' => [17.9712, -76.7936]],
            ['name' => 'Port of Spain, Trinidad and Tobago', 'coordinates' => [10.6603, -61.5086]],
        ];
    }

    private function guessCoordinates(string $location): array
    {
        $key = Str::lower($location);

        return match (true) {
            Str::contains($key, 'barbados') => [13.1939, -59.5432],
            Str::contains($key, 'trinidad') => [10.6918, -61.2225],
            Str::contains($key, 'jamaica') => [18.1096, -77.2975],
            Str::contains($key, 'castries') => [14.0101, -60.9875],
            default => [12.1165, -61.6790],
        };
    }

    private function isCaribbeanCoordinate(float $latitude, float $longitude): bool
    {
        return $latitude >= 5 && $latitude <= 28 && $longitude >= -90 && $longitude <= -52;
    }

    private function cardinalDirection(int $degrees): string
    {
        $directions = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];

        return $directions[(int) round(($degrees % 360) / 22.5) % 16];
    }

    private function weatherCodeLabel(int $code): string
    {
        return match (true) {
            $code === 0 => 'Clear sky',
            in_array($code, [1, 2, 3], true) => 'Partly cloudy',
            in_array($code, [45, 48], true) => 'Foggy',
            in_array($code, [51, 53, 55, 56, 57], true) => 'Drizzle nearby',
            in_array($code, [61, 63, 65, 66, 67, 80, 81, 82], true) => 'Rain showers',
            in_array($code, [95, 96, 99], true) => 'Thunderstorms possible',
            default => 'Warm and breezy',
        };
    }

    private function aqiAdvisory(int $aqi): string
    {
        return match ($aqi) {
            1 => 'Air quality is good.',
            2 => 'Air quality is fair for most people.',
            3 => 'Air quality is moderate; sensitive groups should monitor symptoms.',
            4 => 'Air quality is poor; reduce prolonged outdoor exertion.',
            default => 'Air quality is very poor; limit outdoor exposure.',
        };
    }

    private function openWeatherAqiValue(int $aqi): int
    {
        return [1 => 25, 2 => 60, 3 => 110, 4 => 160, 5 => 220][$aqi] ?? 42;
    }

    private function roundValue(mixed $value, int|float $fallback, int $precision = 0): int|float
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $rounded = round((float) $value, $precision);

        return $precision === 0 ? (int) $rounded : $rounded;
    }

    private function timeLabel(?string $value, string $fallback): string
    {
        if (! $value) {
            return $fallback;
        }

        return Carbon::parse($value)->format('g:i A');
    }

    private function numericList(array $values): array
    {
        return collect($values)->map(fn ($value) => $value === null ? null : round((float) $value, 1))->all();
    }
}
