<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleAirQualityService
{
    public function currentRawAqi(float $latitude, float $longitude): ?int
    {
        $token = config('services.google.air_quality_token');
        if (! $token) {
            return null;
        }

        // Google Air Quality API "Current Conditions".
        // Docs: POST https://airquality.googleapis.com/v1/currentConditions:lookup
        // This endpoint requires OAuth, so we only call it when an access token is configured.
        $response = Http::timeout(10)
            ->withToken($token)
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->post('https://airquality.googleapis.com/v1/currentConditions:lookup', [
                'location' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ],
                'languageCode' => 'en',
                'universalAqi' => true,
                'extraComputations' => [],
            ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $indexes = $data['indexes'] ?? [];
        if (! is_array($indexes) || $indexes === []) {
            return null;
        }

        $first = $indexes[0] ?? null;
        $aqi = is_array($first) ? ($first['aqi'] ?? null) : null;
        if ($aqi === null) {
            return null;
        }

        $aqi = (int) $aqi;
        return $aqi > 0 ? $aqi : null;
    }
}
