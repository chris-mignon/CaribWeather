<?php

use App\Services\AiAssistantService;
use App\Models\AlertNotification;
use App\Services\WeatherDataService;
use App\Models\AlertSubscription;
use App\Models\SavedLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Arr;
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

Route::get('/alerts', function (Request $request) {
    $clientId = $request->user() ? null : caribweather_client_id($request);

    return response()->json([
        'data' => AlertSubscription::query()
            ->where(fn ($query) => $query
                ->when($request->user(), fn ($query, $user) => $query->where('user_id', $user->id))
                ->when(! $request->user(), fn ($query) => $query->where('client_id', $clientId))
            )
            ->latest()
            ->get()
            ->map(fn (AlertSubscription $alert) => caribweather_alert_payload($alert)),
    ]);
});

Route::post('/alerts', function (Request $request) {
    $validated = $request->validate([
        'location' => ['required', 'string', 'max:120'],
        'latitude' => ['nullable', 'numeric', 'between:-90,90'],
        'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        'type' => ['required', 'string', 'max:80'],
        'threshold' => ['nullable', 'string', 'max:120'],
        'quietHours' => ['nullable', 'string', 'max:80'],
        'channels' => ['nullable', 'array'],
    ]);

    $alert = AlertSubscription::create([
        'user_id' => $request->user()?->id,
        'client_id' => $request->user() ? null : caribweather_client_id($request),
        'location' => $validated['location'],
        'latitude' => $validated['latitude'] ?? null,
        'longitude' => $validated['longitude'] ?? null,
        'type' => $validated['type'],
        'threshold' => $validated['threshold'] ?? null,
        'quiet_hours' => $validated['quietHours'] ?? null,
        'channels' => $validated['channels'] ?? ['in_app'],
        'enabled' => true,
    ]);

    return response()->json(['data' => caribweather_alert_payload($alert)], 201);
});

Route::put('/alerts/{alert}', function (Request $request, AlertSubscription $alert) {
    abort_unless(caribweather_owns_record($request, $alert), 404);

    $validated = $request->validate([
        'location' => ['sometimes', 'required', 'string', 'max:120'],
        'latitude' => ['nullable', 'numeric', 'between:-90,90'],
        'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        'type' => ['sometimes', 'required', 'string', 'max:80'],
        'threshold' => ['nullable', 'string', 'max:120'],
        'quietHours' => ['nullable', 'string', 'max:80'],
        'channels' => ['nullable', 'array'],
        'enabled' => ['nullable', 'boolean'],
    ]);

    $alert->update([
        ...Arr::only($validated, ['location', 'latitude', 'longitude', 'type', 'threshold', 'channels', 'enabled']),
        ...(array_key_exists('quietHours', $validated) ? ['quiet_hours' => $validated['quietHours']] : []),
    ]);

    return response()->json(['data' => caribweather_alert_payload($alert->refresh())]);
});

Route::delete('/alerts/{alert}', function (Request $request, AlertSubscription $alert) {
    abort_unless(caribweather_owns_record($request, $alert), 404);

    $alert->delete();

    return response()->noContent();
});

Route::get('/notifications', function (Request $request) {
    $clientId = $request->user() ? null : caribweather_client_id($request);

    return response()->json([
        'data' => AlertNotification::query()
            ->where(fn ($query) => $query
                ->when($request->user(), fn ($query, $user) => $query->where('user_id', $user->id))
                ->when(! $request->user(), fn ($query) => $query->where('client_id', $clientId))
            )
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (AlertNotification $notification) => caribweather_notification_payload($notification)),
    ]);
});

Route::post('/notifications/{notification}/read', function (Request $request, AlertNotification $notification) {
    abort_unless(caribweather_owns_record($request, $notification), 404);

    $notification->forceFill(['read_at' => now()])->save();

    return response()->json(['data' => caribweather_notification_payload($notification->refresh())]);
});

Route::get('/saved-locations', function (Request $request) {
    $clientId = $request->user() ? null : caribweather_client_id($request);

    return response()->json([
        'data' => SavedLocation::query()
            ->where(fn ($query) => $query
                ->when($request->user(), fn ($query, $user) => $query->where('user_id', $user->id))
                ->when(! $request->user(), fn ($query) => $query->where('client_id', $clientId))
            )
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(),
    ]);
});

Route::post('/saved-locations', function (Request $request) {
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:120'],
        'latitude' => ['required', 'numeric', 'between:-90,90'],
        'longitude' => ['required', 'numeric', 'between:-180,180'],
        'isDefault' => ['nullable', 'boolean'],
    ]);

    $clientId = $request->user() ? null : caribweather_client_id($request);
    if ($validated['isDefault'] ?? false) {
        SavedLocation::query()
            ->when($request->user(), fn ($query, $user) => $query->where('user_id', $user->id))
            ->when(! $request->user(), fn ($query) => $query->where('client_id', $clientId))
            ->update(['is_default' => false]);
    }

    $location = SavedLocation::create([
        'user_id' => $request->user()?->id,
        'client_id' => $clientId,
        'name' => $validated['name'],
        'latitude' => $validated['latitude'],
        'longitude' => $validated['longitude'],
        'is_default' => $validated['isDefault'] ?? false,
    ]);

    return response()->json(['data' => $location], 201);
});

Route::delete('/saved-locations/{savedLocation}', function (Request $request, SavedLocation $savedLocation) {
    abort_unless(caribweather_owns_record($request, $savedLocation), 404);

    $savedLocation->delete();

    return response()->noContent();
});

if (! function_exists('caribweather_client_id')) {
    function caribweather_client_id(Request $request): string
    {
        $clientId = (string) $request->header('X-CaribWeather-Client', $request->query('client_id', ''));

        abort_unless(Str::isUuid($clientId), 422, 'A valid CaribWeather client ID is required.');

        return $clientId;
    }
}

if (! function_exists('caribweather_owns_record')) {
    function caribweather_owns_record(Request $request, AlertSubscription|SavedLocation|AlertNotification $record): bool
    {
        if ($request->user()) {
            return $record->user_id === $request->user()->id;
        }

        return $record->client_id === caribweather_client_id($request);
    }
}

if (! function_exists('caribweather_alert_payload')) {
    function caribweather_alert_payload(AlertSubscription $alert): array
    {
        return [
            'id' => $alert->id,
            'location' => $alert->location,
            'latitude' => $alert->latitude,
            'longitude' => $alert->longitude,
            'type' => $alert->type,
            'threshold' => $alert->threshold,
            'quietHours' => $alert->quiet_hours,
            'channels' => $alert->channels ?? ['in_app'],
            'enabled' => $alert->enabled,
            'createdAt' => $alert->created_at?->toISOString(),
        ];
    }
}

if (! function_exists('caribweather_notification_payload')) {
    function caribweather_notification_payload(AlertNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'location' => $notification->location,
            'conditionValue' => $notification->condition_value,
            'threshold' => $notification->threshold,
            'message' => $notification->message,
            'channels' => $notification->channels ?? ['in_app'],
            'deliveredAt' => $notification->delivered_at?->toISOString(),
            'readAt' => $notification->read_at?->toISOString(),
            'createdAt' => $notification->created_at?->toISOString(),
        ];
    }
}
