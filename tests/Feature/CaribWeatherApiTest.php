<?php

namespace Tests\Feature;

use App\Models\AlertSubscription;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CaribWeatherApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_current_weather_endpoint_returns_dashboard_payload(): void
    {
        $response = $this->getJson('/api/weather/current?location=Grenada');

        $response
            ->assertOk()
            ->assertJsonPath('location', 'Grenada')
            ->assertJsonStructure([
                'location',
                'coordinates',
                'lastUpdated',
                'current' => ['tempC', 'feelsLikeC', 'humidity', 'windKph', 'uvIndex', 'aqi', 'rainChance'],
                'marine' => ['waveHeightM', 'seaTempC', 'swellDirection'],
                'hourly',
                'daily',
            ]);
    }

    public function test_assistant_endpoint_returns_a_weather_answer(): void
    {
        $response = $this->postJson('/api/assistant/query', [
            'query' => 'Is it safe to go fishing this afternoon?',
            'context' => [
                'location' => 'Grenada',
                'current' => [
                    'windDirection' => 'ESE',
                    'windKph' => 24,
                    'rainChance' => 48,
                ],
                'marine' => [
                    'waveHeightM' => 1.6,
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['answer', 'source']);
    }

    public function test_location_search_endpoint_returns_results(): void
    {
        $response = $this->getJson('/api/weather/search?q=Grenada');

        $response
            ->assertOk()
            ->assertJsonStructure(['results' => [['name', 'coordinates']]]);
    }

    public function test_historical_endpoint_returns_chart_payload(): void
    {
        $response = $this->getJson('/api/weather/historical?location=Grenada&start=2026-06-01&end=2026-06-07');

        $response
            ->assertOk()
            ->assertJsonStructure(['labels', 'highs', 'means', 'lows', 'rainfall', 'wind', 'humidity']);
    }

    public function test_historical_endpoint_uses_meteostat_when_configured(): void
    {
        Cache::flush();
        config([
            'services.caribweather.use_live_providers' => true,
            'services.meteostat.key' => 'test-meteostat-key',
            'services.meteostat.host' => 'meteostat.p.rapidapi.com',
            'services.meteostat.base_url' => 'https://meteostat.p.rapidapi.com',
        ]);

        Http::fake([
            'meteostat.p.rapidapi.com/stations/nearby*' => Http::response([
                'data' => [['id' => '78958']],
            ]),
            'meteostat.p.rapidapi.com/stations/daily*' => Http::response([
                'data' => [[
                    'date' => '2026-06-01',
                    'tavg' => 28.2,
                    'tmin' => 24.1,
                    'tmax' => 31.6,
                    'prcp' => 5.4,
                    'wspd' => 18.7,
                    'pres' => 1012.4,
                ]],
            ]),
        ]);

        $response = $this->getJson('/api/weather/historical?location=12.0561,-61.7488&start=2026-06-01&end=2026-06-01');

        $response
            ->assertOk()
            ->assertJsonPath('source', 'meteostat')
            ->assertJsonPath('station', '78958')
            ->assertJsonPath('highs.0', 31.6)
            ->assertJsonPath('pressure.0', 1012.4);
    }

    public function test_active_storm_endpoint_maps_nhc_payload(): void
    {
        Cache::flush();
        Http::fake([
            'www.nhc.noaa.gov/CurrentStorms.json' => Http::response([
                'activeStorms' => [[
                    'id' => 'al012026',
                    'name' => 'One',
                    'classification' => 'TS',
                    'intensity' => '40',
                    'pressure' => '1002',
                    'latitudeNumeric' => 15.2,
                    'longitudeNumeric' => -61.4,
                    'movementDir' => 280,
                    'movementSpeed' => 12,
                    'lastUpdate' => '2026-06-17T00:00:00.000Z',
                    'publicAdvisory' => ['url' => 'https://www.nhc.noaa.gov/text/test.shtml'],
                    'forecastGraphics' => ['url' => 'https://www.nhc.noaa.gov/graphics_test.shtml'],
                ]],
            ]),
        ]);

        $this->getJson('/api/storms/active')
            ->assertOk()
            ->assertJsonPath('source', 'nhc-current-storms')
            ->assertJsonPath('storms.0.id', 'al012026')
            ->assertJsonPath('storms.0.latitude', 15.2)
            ->assertJsonPath('storms.0.longitude', -61.4);
    }

    public function test_alert_subscriptions_are_persisted_by_client_id(): void
    {
        $clientId = (string) Str::uuid();

        $create = $this
            ->withHeader('X-CaribWeather-Client', $clientId)
            ->postJson('/api/alerts', [
                'location' => 'Grenville, Grenada',
                'latitude' => 12.131,
                'longitude' => -61.6888,
                'type' => 'Heavy Rain',
                'threshold' => 'Rain > 20 mm/hr',
                'quietHours' => '10:00 PM - 6:00 AM',
                'channels' => ['in_app', 'email'],
            ]);

        $create
            ->assertCreated()
            ->assertJsonPath('data.location', 'Grenville, Grenada')
            ->assertJsonPath('data.type', 'Heavy Rain');

        $this
            ->withHeader('X-CaribWeather-Client', $clientId)
            ->getJson('/api/alerts')
            ->assertOk()
            ->assertJsonPath('data.0.location', 'Grenville, Grenada');

        $alertId = $create->json('data.id');

        $this
            ->withHeader('X-CaribWeather-Client', $clientId)
            ->deleteJson("/api/alerts/{$alertId}")
            ->assertNoContent();
    }

    public function test_saved_locations_are_persisted_by_client_id(): void
    {
        $clientId = (string) Str::uuid();

        $this
            ->withHeader('X-CaribWeather-Client', $clientId)
            ->postJson('/api/saved-locations', [
                'name' => 'St. George\'s, Grenada',
                'latitude' => 12.0561,
                'longitude' => -61.7488,
                'isDefault' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'St. George\'s, Grenada');

        $this
            ->withHeader('X-CaribWeather-Client', $clientId)
            ->getJson('/api/saved-locations')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'St. George\'s, Grenada');
    }

    public function test_alert_checker_creates_in_app_notifications(): void
    {
        $clientId = (string) Str::uuid();

        AlertSubscription::create([
            'client_id' => $clientId,
            'location' => 'Grenada',
            'type' => 'High UV Warning',
            'threshold' => 'UV Index > 1',
            'channels' => ['in_app'],
            'enabled' => true,
        ]);

        $this->artisan('caribweather:check-alerts')
            ->expectsOutputToContain('triggered 1')
            ->assertSuccessful();

        $response = $this
            ->withHeader('X-CaribWeather-Client', $clientId)
            ->getJson('/api/notifications');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.type', 'High UV Warning')
            ->assertJsonStructure(['data' => [['id', 'message', 'conditionValue', 'threshold', 'readAt']]]);

        $notificationId = $response->json('data.0.id');

        $this
            ->withHeader('X-CaribWeather-Client', $clientId)
            ->postJson("/api/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notificationId);
    }
}
