<?php

namespace Tests\Feature;

use App\Mail\WeatherAlertTriggered;
use App\Models\AlertSubscription;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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

    public function test_active_storm_geojson_endpoint_returns_track_and_cone_features(): void
    {
        $trackKml = <<<KML
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <Placemark>
      <name>Track</name>
      <LineString>
        <coordinates>-61.4,15.2 -61.0,15.6</coordinates>
      </LineString>
    </Placemark>
  </Document>
</kml>
KML;

        $coneKml = <<<KML
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <Placemark>
      <name>Cone</name>
      <Polygon>
        <outerBoundaryIs>
          <LinearRing>
            <coordinates>-61.5,15.0 -61.2,15.0 -61.2,15.4 -61.5,15.4</coordinates>
          </LinearRing>
        </outerBoundaryIs>
      </Polygon>
    </Placemark>
  </Document>
</kml>
KML;

        Cache::flush();
        Http::fake([
            'https://www.nhc.noaa.gov/CurrentStorms.json' => Http::response([
                'activeStorms' => [[
                    'id' => 'al012026',
                    'name' => 'One',
                    'forecastGraphics' => ['url' => 'https://www.nhc.noaa.gov/graphics_test.shtml'],
                ]],
            ]),
            'https://www.nhc.noaa.gov/graphics_test.shtml' => Http::response(<<<HTML
<html><body>
<a href="https://www.nhc.noaa.gov/kml_test/track.kml">track</a>
<a href="https://www.nhc.noaa.gov/kml_test/cone.kml">cone</a>
</body></html>
HTML
            ),
            'https://www.nhc.noaa.gov/kml_test/track.kml' => Http::response($trackKml, 200, ['Content-Type' => 'application/vnd.google-earth.kml+xml']),
            'https://www.nhc.noaa.gov/kml_test/cone.kml' => Http::response($coneKml, 200, ['Content-Type' => 'application/vnd.google-earth.kml+xml']),
        ]);

        $response = $this->getJson('/api/storms/active-geojson');

        $response
            ->assertOk()
            ->assertJsonPath('geojson.type', 'FeatureCollection')
            ->assertJsonCount(2, 'geojson.features')
            ->assertJsonFragment(['stormId' => 'al012026', 'category' => 'track'])
            ->assertJsonFragment(['stormId' => 'al012026', 'category' => 'cone']);
    }

    public function test_openweather_tile_proxy_returns_png_when_key_configured(): void
    {
        config(['services.openweather.key' => 'test-openweather-key']);
        Http::fake([
            'tile.openweathermap.org/map/temp_new/4/5/6.png*' => Http::response('png-bytes', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $response = $this->get('/api/map/tiles/temp_new/4/5/6.png');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertSee('png-bytes');
    }

    public function test_user_can_register_and_access_authenticated_profile(): void
    {
        $register = $this->postJson('/api/auth/register', [
            'name' => 'Carib User',
            'email' => 'carib.user@example.com',
            'password' => 'password123',
        ]);

        $register
            ->assertCreated()
            ->assertJsonPath('user.email', 'carib.user@example.com')
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $this
            ->withToken($register->json('token'))
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('user.email', 'carib.user@example.com');
    }

    public function test_push_subscription_is_stored_for_guest_client(): void
    {
        $clientId = (string) Str::uuid();

        $this
            ->withHeader('X-CaribWeather-Client', $clientId)
            ->postJson('/api/push-subscriptions', [
                'endpoint' => 'https://push.example.com/subscription/1',
                'keys' => [
                    'p256dh' => 'public-key',
                    'auth' => 'auth-token',
                ],
                'contentEncoding' => 'aes128gcm',
            ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id']]);

        $this->assertDatabaseHas('push_subscriptions', [
            'client_id' => $clientId,
            'endpoint' => 'https://push.example.com/subscription/1',
        ]);
    }

    public function test_user_can_login_and_create_user_scoped_alert(): void
    {
        $user = User::create([
            'name' => 'Alert Owner',
            'email' => 'alert.owner@example.com',
            'password' => Hash::make('password123'),
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'alert.owner@example.com',
            'password' => 'password123',
        ]);

        $login->assertOk()->assertJsonStructure(['token', 'user']);

        $this
            ->withToken($login->json('token'))
            ->postJson('/api/alerts', [
                'location' => 'Bridgetown, Barbados',
                'type' => 'High Winds',
                'threshold' => 'Wind > 25 km/h',
            ])
            ->assertCreated()
            ->assertJsonPath('data.location', 'Bridgetown, Barbados');

        $this->assertDatabaseHas('alert_subscriptions', [
            'user_id' => $user->id,
            'client_id' => null,
            'location' => 'Bridgetown, Barbados',
        ]);

        $this
            ->withToken($login->json('token'))
            ->getJson('/api/alerts')
            ->assertOk()
            ->assertJsonPath('data.0.location', 'Bridgetown, Barbados');
    }

    public function test_login_claims_existing_guest_records_for_that_browser(): void
    {
        $clientId = (string) Str::uuid();
        $user = User::create([
            'name' => 'Guest Claim',
            'email' => 'guest.claim@example.com',
            'password' => Hash::make('password123'),
        ]);

        AlertSubscription::create([
            'client_id' => $clientId,
            'location' => 'Grenville, Grenada',
            'type' => 'High UV Warning',
            'threshold' => 'UV > 8',
            'enabled' => true,
        ]);

        $login = $this
            ->withHeader('X-CaribWeather-Client', $clientId)
            ->postJson('/api/auth/login', [
                'email' => 'guest.claim@example.com',
                'password' => 'password123',
            ]);

        $login->assertOk();

        $this->assertDatabaseHas('alert_subscriptions', [
            'user_id' => $user->id,
            'client_id' => null,
            'location' => 'Grenville, Grenada',
        ]);

        $this
            ->withToken($login->json('token'))
            ->getJson('/api/alerts')
            ->assertOk()
            ->assertJsonPath('data.0.location', 'Grenville, Grenada');
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
        Cache::flush();
        config(['services.caribweather.use_live_providers' => false]);

        $this->mock(\App\Services\WeatherDataService::class, function ($mock) {
            $mock->shouldReceive('current')->andReturn([
                'current' => ['uvIndex' => 2],
            ]);
        });

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

    public function test_alert_checker_sends_email_for_user_email_channel(): void
    {
        Mail::fake();
        $user = User::create([
            'name' => 'Email Alert User',
            'email' => 'email.alert@example.com',
            'password' => Hash::make('password123'),
        ]);

        AlertSubscription::create([
            'user_id' => $user->id,
            'location' => 'Grenada',
            'type' => 'High UV Warning',
            'threshold' => 'UV Index > 1',
            'channels' => ['in_app', 'email'],
            'enabled' => true,
        ]);

        $this->artisan('caribweather:check-alerts')->assertSuccessful();

        Mail::assertSent(WeatherAlertTriggered::class, function (WeatherAlertTriggered $mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->alert->type === 'High UV Warning';
        });
    }
}
