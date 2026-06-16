<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/weather/current', function (Request $request) {
    $location = trim((string) $request->query('location', "St. George's, Grenada"));
    $location = $location !== '' ? Str::limit($location, 120, '') : "St. George's, Grenada";

    $weather = Cache::remember(
        'weather.current.'.sha1($location),
        now()->addMinutes(10),
        fn () => caribweather_mock_weather($location),
    );

    return response()->json($weather);
});

Route::get('/weather/search', function (Request $request) {
    $query = Str::lower(trim((string) $request->query('q', '')));
    $locations = collect([
        ['name' => "St. George's, Grenada", 'coordinates' => [12.1165, -61.6790]],
        ['name' => 'Bridgetown, Barbados', 'coordinates' => [13.0975, -59.6167]],
        ['name' => 'Castries, Saint Lucia', 'coordinates' => [14.0101, -60.9875]],
        ['name' => 'Kingston, Jamaica', 'coordinates' => [17.9712, -76.7936]],
        ['name' => 'Port of Spain, Trinidad and Tobago', 'coordinates' => [10.6603, -61.5086]],
    ]);

    return response()->json([
        'results' => $locations
            ->filter(fn (array $location) => $query === '' || Str::contains(Str::lower($location['name']), $query))
            ->values(),
    ]);
});

Route::get('/weather/historical', function (Request $request) {
    $location = trim((string) $request->query('location', 'Grenada'));
    $start = trim((string) $request->query('start', '2026-06-01'));
    $end = trim((string) $request->query('end', '2026-06-07'));
    $cacheKey = 'weather.historical.'.sha1($location.'|'.$start.'|'.$end);

    $history = Cache::remember($cacheKey, now()->addHour(), function () use ($location, $start, $end) {
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
            'cache' => true,
        ];
    });

    return response()->json($history);
});

Route::post('/assistant/query', function (Request $request) {
    $validated = $request->validate([
        'query' => ['required', 'string', 'max:1000'],
        'context' => ['nullable', 'array'],
        'history' => ['nullable', 'array'],
    ]);

    $query = Str::lower(strip_tags($validated['query']));
    $weather = $validated['context'] ?? caribweather_mock_weather("St. George's, Grenada");
    $location = data_get($weather, 'location', "St. George's, Grenada");

    if (Str::contains($query, ['fish', 'sea', 'rough'])) {
        $answer = sprintf(
            'For %s, seas are around %s m with %s winds at %s km/h. Small craft should monitor official marine advisories before departure.',
            $location,
            data_get($weather, 'marine.waveHeightM', 1.6),
            data_get($weather, 'current.windDirection', 'ESE'),
            data_get($weather, 'current.windKph', 24),
        );
    } elseif (Str::contains($query, 'rain')) {
        $answer = sprintf('Rain chance for %s is about %s%%. Carry rain protection and check radar before outdoor plans.', $location, data_get($weather, 'current.rainChance', 48));
    } elseif (Str::contains($query, ['wear', 'uv'])) {
        $answer = sprintf('UV is %s in %s. Wear light clothing, sunscreen, sunglasses, and stay hydrated.', data_get($weather, 'current.uvIndex', 9), $location);
    } elseif (Str::contains($query, ['hurricane', 'storm'])) {
        $answer = 'The NOAA/NHC live feed is not connected yet. This endpoint is ready to proxy official advisories once configured.';
    } else {
        $answer = sprintf('For %s, conditions are %s with a %s%% rain chance.', $location, Str::lower(data_get($weather, 'current.summary', 'warm and breezy')), data_get($weather, 'current.rainChance', 48));
    }

    return response()->json([
        'answer' => $answer,
        'source' => 'laravel-mvp-fallback',
    ]);
})->middleware('throttle:20,60');

Route::get('/alerts', fn () => response()->json(['data' => []]));

Route::post('/alerts', function (Request $request) {
    $validated = $request->validate([
        'location' => ['required', 'string', 'max:120'],
        'type' => ['required', 'string', 'max:80'],
        'threshold' => ['nullable', 'string', 'max:120'],
        'quietHours' => ['nullable', 'string', 'max:80'],
    ]);

    return response()->json(['data' => ['id' => (string) Str::uuid(), ...$validated]], 201);
});

if (! function_exists('caribweather_mock_weather')) {
    function caribweather_mock_weather(string $location): array
    {
        $coordinates = match (true) {
            Str::contains(Str::lower($location), 'barbados') => [13.1939, -59.5432],
            Str::contains(Str::lower($location), 'trinidad') => [10.6918, -61.2225],
            Str::contains(Str::lower($location), 'jamaica') => [18.1096, -77.2975],
            Str::contains(Str::lower($location), 'castries') => [14.0101, -60.9875],
            default => [12.1165, -61.6790],
        };

        return [
            'location' => $location,
            'coordinates' => $coordinates,
            'cache' => true,
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
}
