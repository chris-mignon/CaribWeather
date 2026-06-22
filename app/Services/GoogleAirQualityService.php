<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleAirQualityService
{
    public function currentRawAqi(float $latitude, float $longitude): ?int
    {
        $key = config('services.google.key');
        if (! $key) {
            return null;
        }

        // Google Air Quality API "Current Conditions".
        // Docs: POST https://airquality.googleapis.com/v1/currentConditions:lookup
        // Note: Google may require OAuth depending on project configuration; we still attempt with API key
        // and fall back if it fails.
        $response = Http::timeout(10)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Goog-Api-Key' => $key,
            ])
            ->post('https://airquality.googleapis.com/v1/currentConditions:lookup?key='.$key, [
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
