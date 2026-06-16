<?php

use App\Services\AiAssistantService;
use App\Services\WeatherDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/weather/current', function (Request $request, WeatherDataService $weather) {
    return response()->json($weather->current((string) $request->query('location', "St. George's, Grenada")));
});

Route::get('/weather/search', function (Request $request, WeatherDataService $weather) {
    return response()->json($weather->search((string) $request->query('q', '')));
});

Route::get('/weather/historical', function (Request $request, WeatherDataService $weather) {
    return response()->json($weather->historical(
        (string) $request->query('location', 'Grenada'),
        (string) $request->query('start', now()->subDays(7)->toDateString()),
        (string) $request->query('end', now()->subDay()->toDateString()),
    ));
});

Route::post('/assistant/query', function (Request $request, AiAssistantService $assistant, WeatherDataService $weather) {
    $validated = $request->validate([
        'query' => ['required', 'string', 'max:1000'],
        'context' => ['nullable', 'array'],
        'history' => ['nullable', 'array'],
    ]);

    return response()->json($assistant->answer(
        $validated['query'],
        $validated['context'] ?? $weather->current("St. George's, Grenada"),
        $validated['history'] ?? [],
    ));
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
