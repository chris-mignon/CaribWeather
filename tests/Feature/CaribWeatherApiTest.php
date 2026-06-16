<?php

namespace Tests\Feature;

use Tests\TestCase;

class CaribWeatherApiTest extends TestCase
{
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

    public function test_historical_endpoint_returns_chart_payload(): void
    {
        $response = $this->getJson('/api/weather/historical?location=Grenada&start=2026-06-01&end=2026-06-07');

        $response
            ->assertOk()
            ->assertJsonStructure(['labels', 'highs', 'means', 'lows', 'rainfall', 'wind', 'humidity']);
    }
}
