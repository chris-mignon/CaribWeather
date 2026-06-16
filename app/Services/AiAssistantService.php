<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AiAssistantService
{
    public function answer(string $query, array $weather, array $history = []): array
    {
        $query = trim(strip_tags($query));
        $key = config('services.openai.key');

        if (! $key || ! config('services.caribweather.use_live_providers')) {
            return [
                'answer' => $this->fallbackAnswer($query, $weather),
                'source' => 'fallback',
            ];
        }

        try {
            $response = Http::timeout(12)
                ->withToken($key)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'temperature' => 0.3,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are CaribWeather, a concise Caribbean weather assistant. Use only the provided weather context. Clearly mention uncertainty when data is unavailable. Prioritize marine, tropical storm, UV, heat, rain, and safety guidance.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'query' => $query,
                                'weather' => $weather,
                                'recentConversation' => array_slice($history, -10),
                            ]),
                        ],
                    ],
                ])->throw()->json();

            return [
                'answer' => data_get($response, 'choices.0.message.content') ?: $this->fallbackAnswer($query, $weather),
                'source' => 'openai',
            ];
        } catch (\Throwable) {
            return [
                'answer' => $this->fallbackAnswer($query, $weather),
                'source' => 'fallback',
            ];
        }
    }

    private function fallbackAnswer(string $query, array $weather): string
    {
        $lower = Str::lower($query);
        $location = data_get($weather, 'location', "St. George's, Grenada");

        if (Str::contains($lower, ['fish', 'sea', 'rough'])) {
            return sprintf(
                'For %s, seas are around %s m with %s winds at %s km/h. Small craft should monitor official marine advisories before departure.',
                $location,
                data_get($weather, 'marine.waveHeightM', 1.6),
                data_get($weather, 'current.windDirection', 'ESE'),
                data_get($weather, 'current.windKph', 24),
            );
        }

        if (Str::contains($lower, 'rain')) {
            return sprintf('Rain chance for %s is about %s%%. Carry rain protection and check radar before outdoor plans.', $location, data_get($weather, 'current.rainChance', 48));
        }

        if (Str::contains($lower, ['wear', 'uv'])) {
            return sprintf('UV is %s in %s. Wear light clothing, sunscreen, sunglasses, and stay hydrated.', data_get($weather, 'current.uvIndex', 9), $location);
        }

        if (Str::contains($lower, ['hurricane', 'storm'])) {
            return 'NOAA/NHC live storm feeds are not connected yet. Check official advisories for active watches, warnings, and cone updates.';
        }

        return sprintf('For %s, conditions are %s with a %s%% rain chance.', $location, Str::lower(data_get($weather, 'current.summary', 'warm and breezy')), data_get($weather, 'current.rainChance', 48));
    }
}
