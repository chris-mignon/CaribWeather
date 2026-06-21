<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class NhcStormGeometryService
{
    public function __construct(private readonly NhcKmlToGeoJsonService $kmlConverter)
    {
    }

    public function activeGeoJson(): array
    {
        $payload = [
            'source' => 'nhc-storm-geojson',
            'updatedAt' => now()->toISOString(),
            'geojson' => [
                'type' => 'FeatureCollection',
                'features' => [],
            ],
        ];

        $active = $this->fetchCurrentStorms();
        if (empty($active)) {
            return $payload;
        }

        $features = [];
        foreach ($active as $storm) {
            $stormId = $storm['id'] ?? null;
            if (!is_string($stormId) || $stormId === '') {
                continue;
            }

            $graphicsUrl = $storm['forecastGraphicsUrl'] ?? null;
            if (!is_string($graphicsUrl) || $graphicsUrl === '') {
                continue;
            }

            $links = $this->discoverKmlKmzLinks($graphicsUrl);
            foreach ($links as $link) {
                $category = $this->inferGeometryCategory($link);

                try {
                    $fileResponse = Http::timeout(20)->get($link);
                    if (! $fileResponse->successful()) {
                        continue;
                    }

                    $fileBytes = (string) $fileResponse->body();
                    if ($fileBytes === '') {
                        continue;
                    }

                    $features = array_merge(
                        $features,
                        $this->kmlConverter->convertToFeatures(
                            $fileBytes,
                            $link,
                            [
                                'stormId' => $stormId,
                                'stormName' => $storm['name'] ?? null,
                                'category' => $category,
                                'sourceUrl' => $link,
                            ],
                        ),
                    );
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        $payload['geojson']['features'] = array_values($features);

        return $payload;
    }

    private function fetchCurrentStorms(): array
    {
        try {
            $response = Http::timeout(10)->get('https://www.nhc.noaa.gov/CurrentStorms.json')->throw();
            $json = $response->json();

            return collect($json['activeStorms'] ?? [])
                ->map(fn (array $storm) => [
                    'id' => $storm['id'] ?? null,
                    'name' => $storm['name'] ?? null,
                    'forecastGraphicsUrl' => data_get($storm, 'forecastGraphics.url'),
                ])
                ->filter(fn (array $storm) => !empty($storm['id']) && !empty($storm['forecastGraphicsUrl']))
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    private function discoverKmlKmzLinks(string $graphicsUrl): array
    {
        $direct = trim($graphicsUrl);
        if ($this->looksLikeKmlKmz($direct)) {
            return [$direct];
        }

        try {
            $html = Http::timeout(10)->get($direct)->throw()->body();
        } catch (\Throwable) {
            return [];
        }

        preg_match_all('/https?:\/\/[^"\'<>\s]+?\.(?:kml|kmz)/i', $html, $matches);
        $links = $matches[0] ?? [];
        if (!is_array($links)) {
            return [];
        }

        // If the graphics page contains relative links, fall back to extracting those.
        if (count($links) === 0) {
            preg_match_all('/(?:href|src)=["\']([^"\']+?\.(?:kml|kmz))["\']/i', $html, $relMatches);
            $rels = $relMatches[1] ?? [];
            if (is_array($rels) && count($rels) > 0) {
                return array_values(array_filter(array_map(fn (string $rel) => $this->resolveUrl($direct, $rel), $rels)));
            }
        }

        return array_values(array_unique($links));
    }

    private function looksLikeKmlKmz(string $url): bool
    {
        $lower = strtolower($url);
        return str_ends_with($lower, '.kml') || str_ends_with($lower, '.kmz');
    }

    private function inferGeometryCategory(string $url): ?string
    {
        $lower = strtolower($url);
        if (str_contains($lower, 'track')) {
            return 'track';
        }
        if (str_contains($lower, 'cone')) {
            return 'cone';
        }
        return 'unknown';
    }

    private function resolveUrl(string $baseUrl, string $link): string
    {
        $base = parse_url($baseUrl);
        if ($base === false || !isset($base['scheme'], $base['host'])) {
            return $link;
        }

        if (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
            return $link;
        }

        if (str_starts_with($link, '/')) {
            return $base['scheme'] . '://' . $base['host'] . $link;
        }

        $path = $base['path'] ?? '';
        $dir = $path !== '' ? rtrim(dirname($path), '/') : '';

        return $base['scheme'] . '://' . $base['host'] . ($dir !== '' ? $dir . '/' : '/') . $link;
    }
}
