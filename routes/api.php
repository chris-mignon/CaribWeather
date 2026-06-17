<?php

use App\Services\AiAssistantService;
use App\Models\AlertNotification;
use App\Services\WeatherDataService;
use App\Models\AlertSubscription;
use App\Models\PushSubscription;
use App\Models\SavedLocation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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

Route::get('/map/tiles/{layer}/{z}/{x}/{y}', function (string $layer, int $z, int $x, string $y) {
    $key = config('services.openweather.key');
    $allowedLayers = ['clouds_new', 'precipitation_new', 'temp_new', 'wind_new', 'pressure_new'];

    abort_unless($key && in_array($layer, $allowedLayers, true), 404);

    $tileY = Str::before($y, '.');
    abort_unless(is_numeric($tileY), 404);

    $response = Http::timeout(10)->get("https://tile.openweathermap.org/map/{$layer}/{$z}/{$x}/{$tileY}.png", [
        'appid' => $key,
    ])->throw();

    return response($response->body(), 200, [
        'Content-Type' => 'image/png',
        'Cache-Control' => 'public, max-age=600',
    ]);
})->where(['y' => '.*']);

Route::get('/storms/active', function () {
    $payload = Cache::remember('storms.active.nhc', now()->addMinutes(10), function () {
        try {
            $response = Http::timeout(10)->get('https://www.nhc.noaa.gov/CurrentStorms.json')->throw()->json();

            return [
                'source' => 'nhc-current-storms',
                'updatedAt' => now()->toISOString(),
                'storms' => collect($response['activeStorms'] ?? [])
                    ->map(fn (array $storm) => [
                        'id' => $storm['id'] ?? null,
                        'name' => $storm['name'] ?? 'Unnamed storm',
                        'classification' => $storm['classification'] ?? null,
                        'intensity' => isset($storm['intensity']) ? (int) $storm['intensity'] : null,
                        'pressure' => isset($storm['pressure']) ? (int) $storm['pressure'] : null,
                        'latitude' => isset($storm['latitudeNumeric']) ? (float) $storm['latitudeNumeric'] : null,
                        'longitude' => isset($storm['longitudeNumeric']) ? (float) $storm['longitudeNumeric'] : null,
                        'movementDir' => $storm['movementDir'] ?? null,
                        'movementSpeed' => $storm['movementSpeed'] ?? null,
                        'lastUpdate' => $storm['lastUpdate'] ?? null,
                        'publicAdvisoryUrl' => data_get($storm, 'publicAdvisory.url'),
                        'forecastGraphicsUrl' => data_get($storm, 'forecastGraphics.url'),
                    ])
                    ->filter(fn (array $storm) => $storm['latitude'] !== null && $storm['longitude'] !== null)
                    ->values()
                    ->all(),
            ];
        } catch (\Throwable) {
            return [
                'source' => 'fallback',
                'updatedAt' => now()->toISOString(),
                'storms' => [],
            ];
        }
    });

    return response()->json($payload);
});

Route::post('/auth/register', function (Request $request) {
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:120'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'string', 'min:8'],
    ]);

    $user = User::create($validated);
    caribweather_claim_client_records($request, $user);
    $token = $user->createToken('caribweather-pwa')->plainTextToken;

    return response()->json(['user' => caribweather_user_payload($user), 'token' => $token], 201);
})->middleware('throttle:5,1');

Route::post('/auth/login', function (Request $request) {
    $validated = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $user = User::where('email', $validated['email'])->first();
    if (! $user || ! Hash::check($validated['password'], $user->password)) {
        return response()->json(['message' => 'Invalid email or password.'], 422);
    }

    $token = $user->createToken('caribweather-pwa')->plainTextToken;
    caribweather_claim_client_records($request, $user);

    return response()->json(['user' => caribweather_user_payload($user), 'token' => $token]);
})->middleware('throttle:5,1');

Route::get('/auth/user', function (Request $request) {
    return response()->json(['user' => caribweather_user_payload($request->user())]);
})->middleware('auth:sanctum');

Route::post('/auth/logout', function (Request $request) {
    $request->user()->currentAccessToken()?->delete();

    return response()->noContent();
})->middleware('auth:sanctum');

Route::post('/assistant/query', function (Request $request, AiAssistantService $assistant, WeatherDataService $weather) {
    $validated = $request->validate([
        'query' => ['required', 'string', 'max:1000'],
        'context' => ['nullable', 'array'],
        'history' => ['nullable', 'array'],
    ]);

    $context = $validated['context'] ?? caribweather_context_for_query($validated['query'], $weather);

    return response()->json($assistant->answer($validated['query'], $context, $validated['history'] ?? []));
})->middleware('throttle:20,60');

Route::get('/push/vapid-public-key', fn () => response()->json([
    'publicKey' => config('services.webpush.public_key'),
]));

Route::post('/push-subscriptions', function (Request $request) {
    $user = caribweather_user($request);
    $validated = $request->validate([
        'endpoint' => ['required', 'url', 'max:2000'],
        'keys.p256dh' => ['required', 'string'],
        'keys.auth' => ['required', 'string'],
        'contentEncoding' => ['nullable', 'string', 'max:40'],
    ]);

    $subscription = PushSubscription::updateOrCreate(
        ['endpoint' => $validated['endpoint']],
        [
            'user_id' => $user?->id,
            'client_id' => $user ? null : caribweather_client_id($request),
            'public_key' => $validated['keys']['p256dh'],
            'auth_token' => $validated['keys']['auth'],
            'content_encoding' => $validated['contentEncoding'] ?? 'aes128gcm',
        ],
    );

    return response()->json(['data' => ['id' => $subscription->id]], 201);
});

Route::get('/alerts', function (Request $request) {
    $user = caribweather_user($request);
    $clientId = $user ? null : caribweather_client_id($request);

    return response()->json([
        'data' => AlertSubscription::query()
            ->where(fn ($query) => $query
                ->when($user, fn ($query, $user) => $query->where('user_id', $user->id))
                ->when(! $user, fn ($query) => $query->where('client_id', $clientId))
            )
            ->latest()
            ->get()
            ->map(fn (AlertSubscription $alert) => caribweather_alert_payload($alert)),
    ]);
});

Route::post('/alerts', function (Request $request) {
    $user = caribweather_user($request);
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
        'user_id' => $user?->id,
        'client_id' => $user ? null : caribweather_client_id($request),
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
    $user = caribweather_user($request);
    $clientId = $user ? null : caribweather_client_id($request);

    return response()->json([
        'data' => AlertNotification::query()
            ->where(fn ($query) => $query
                ->when($user, fn ($query, $user) => $query->where('user_id', $user->id))
                ->when(! $user, fn ($query) => $query->where('client_id', $clientId))
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
    $user = caribweather_user($request);
    $clientId = $user ? null : caribweather_client_id($request);

    return response()->json([
        'data' => SavedLocation::query()
            ->where(fn ($query) => $query
                ->when($user, fn ($query, $user) => $query->where('user_id', $user->id))
                ->when(! $user, fn ($query) => $query->where('client_id', $clientId))
            )
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(),
    ]);
});

Route::post('/saved-locations', function (Request $request) {
    $user = caribweather_user($request);
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:120'],
        'latitude' => ['required', 'numeric', 'between:-90,90'],
        'longitude' => ['required', 'numeric', 'between:-180,180'],
        'isDefault' => ['nullable', 'boolean'],
    ]);

    $clientId = $user ? null : caribweather_client_id($request);
    if ($validated['isDefault'] ?? false) {
        SavedLocation::query()
            ->when($user, fn ($query, $user) => $query->where('user_id', $user->id))
            ->when(! $user, fn ($query) => $query->where('client_id', $clientId))
            ->update(['is_default' => false]);
    }

    $location = SavedLocation::create([
        'user_id' => $user?->id,
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

if (! function_exists('caribweather_user')) {
    function caribweather_user(Request $request): ?User
    {
        return $request->bearerToken() ? auth('sanctum')->user() : null;
    }
}

if (! function_exists('caribweather_user_payload')) {
    function caribweather_user_payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}

if (! function_exists('caribweather_claim_client_records')) {
    function caribweather_claim_client_records(Request $request, User $user): void
    {
        $clientId = (string) $request->header('X-CaribWeather-Client', '');
        if (! Str::isUuid($clientId)) {
            return;
        }

        AlertSubscription::where('client_id', $clientId)->update(['user_id' => $user->id, 'client_id' => null]);
        SavedLocation::where('client_id', $clientId)->update(['user_id' => $user->id, 'client_id' => null]);
        AlertNotification::where('client_id', $clientId)->update(['user_id' => $user->id, 'client_id' => null]);
        PushSubscription::where('client_id', $clientId)->update(['user_id' => $user->id, 'client_id' => null]);
    }
}

if (! function_exists('caribweather_context_for_query')) {
    function caribweather_context_for_query(string $query, WeatherDataService $weather): array
    {
        $locations = [
            'grenville' => 'Grenville, Grenada',
            'grenada' => "St. George's, Grenada",
            'barbados' => 'Bridgetown, Barbados',
            'bridgetown' => 'Bridgetown, Barbados',
            'castries' => 'Castries, Saint Lucia',
            'saint lucia' => 'Castries, Saint Lucia',
            'kingston' => 'Kingston, Jamaica',
            'jamaica' => 'Kingston, Jamaica',
            'trinidad' => 'Port of Spain, Trinidad and Tobago',
            'tobago' => 'Scarborough, Trinidad and Tobago',
            'san juan' => 'San Juan, Puerto Rico',
            'puerto rico' => 'San Juan, Puerto Rico',
        ];

        $lower = Str::lower($query);
        foreach ($locations as $needle => $location) {
            if (Str::contains($lower, $needle)) {
                return $weather->current($location);
            }
        }

        return $weather->current("St. George's, Grenada");
    }
}

if (! function_exists('caribweather_owns_record')) {
    function caribweather_owns_record(Request $request, AlertSubscription|SavedLocation|AlertNotification $record): bool
    {
        $user = caribweather_user($request);
        if ($user) {
            return $record->user_id === $user->id;
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
