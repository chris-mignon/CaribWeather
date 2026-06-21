<?php

namespace App\Services;

use ZipArchive;

class NhcKmlToGeoJsonService
{
    public function convertToFeatures(string $payload, string $sourceUrl, array $context): array
    {
        $lowerUrl = strtolower($sourceUrl);
        $kml = str_ends_with($lowerUrl, '.kmz')
            ? $this->extractKmlFromKmz($payload)
            : $payload;

        if ($kml === '') {
            return [];
        }

        return $this->parseKmlToFeatures($kml, $context);
    }

    private function extractKmlFromKmz(string $kmzBinary): string
    {
        if ($kmzBinary === '') {
            return '';
        }

        $tmpKmz = tempnam(sys_get_temp_dir(), 'caribweather_kmz_');
        if ($tmpKmz === false) {
            return '';
        }

        $tmpKmlContent = '';
        try {
            file_put_contents($tmpKmz, $kmzBinary);

            $zip = new ZipArchive();
            if ($zip->open($tmpKmz) !== true) {
                return '';
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!is_array($stat) || empty($stat['name'])) {
                    continue;
                }
                $name = (string) $stat['name'];
                if (!str_ends_with(strtolower($name), '.kml')) {
                    continue;
                }

                $contents = $zip->getFromIndex($i);
                if (is_string($contents) && $contents !== '') {
                    $tmpKmlContent = $contents;
                    break;
                }
            }
        } finally {
            @unlink($tmpKmz);
        }

        return $tmpKmlContent;
    }

    private function parseKmlToFeatures(string $kml, array $context): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($kml);
        if ($xml === false) {
            return [];
        }

        $placemarks = $xml->xpath('//*[local-name()="Placemark"]') ?: [];
        if (count($placemarks) === 0) {
            return [];
        }

        $features = [];

        foreach ($placemarks as $pm) {
            $pmName = $this->firstTextByLocalName($pm, 'name');

            $coordsNode = $pm->xpath('.//*[local-name()="Point"]/*[local-name()="coordinates"]');
            if (is_array($coordsNode) && isset($coordsNode[0])) {
                $coord = $this->parseCoordinateListToLonLatPairs(trim((string) $coordsNode[0]));
                if (count($coord) > 0) {
                    [$lon, $lat] = $coord[0];
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'stormId' => $context['stormId'] ?? null,
                            'stormName' => $context['stormName'] ?? null,
                            'category' => $context['category'] ?? null,
                            'placemark' => $pmName,
                            'sourceUrl' => $context['sourceUrl'] ?? null,
                        ],
                        'geometry' => [
                            'type' => 'Point',
                            'coordinates' => [$lon, $lat],
                        ],
                    ];
                }
                continue;
            }

            $lineCoordsNode = $pm->xpath('.//*[local-name()="LineString"]/*[local-name()="coordinates"]');
            if (is_array($lineCoordsNode) && isset($lineCoordsNode[0])) {
                $pairs = $this->parseCoordinateListToLonLatPairs(trim((string) $lineCoordsNode[0]));
                if (count($pairs) >= 2) {
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'stormId' => $context['stormId'] ?? null,
                            'stormName' => $context['stormName'] ?? null,
                            'category' => $context['category'] ?? null,
                            'placemark' => $pmName,
                            'sourceUrl' => $context['sourceUrl'] ?? null,
                        ],
                        'geometry' => [
                            'type' => 'LineString',
                            'coordinates' => array_map(fn (array $p) => [$p[0], $p[1]], $pairs),
                        ],
                    ];
                }
                continue;
            }

            $outerPolyCoordsNode = $pm->xpath('.//*[local-name()="Polygon"]//*[local-name()="outerBoundaryIs"]//*[local-name()="coordinates"]');
            if (is_array($outerPolyCoordsNode) && isset($outerPolyCoordsNode[0])) {
                $pairs = $this->parseCoordinateListToLonLatPairs(trim((string) $outerPolyCoordsNode[0]));
                if (count($pairs) >= 3) {
                    $ring = array_map(fn (array $p) => [$p[0], $p[1]], $pairs);
                    if ($ring[0] !== $ring[count($ring) - 1]) {
                        $ring[] = $ring[0];
                    }

                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'stormId' => $context['stormId'] ?? null,
                            'stormName' => $context['stormName'] ?? null,
                            'category' => $context['category'] ?? null,
                            'placemark' => $pmName,
                            'sourceUrl' => $context['sourceUrl'] ?? null,
                        ],
                        'geometry' => [
                            'type' => 'Polygon',
                            'coordinates' => [$ring],
                        ],
                    ];
                }
            }
        }

        return $features;
    }

    private function firstTextByLocalName($xml, string $localName): ?string
    {
        try {
            $nodes = $xml->xpath('./*[local-name()="' . $localName . '"]/text()');
            if (is_array($nodes) && isset($nodes[0])) {
                $text = trim((string) $nodes[0]);
                return $text !== '' ? $text : null;
            }
        } catch (\Throwable) {
            // ignore
        }
        return null;
    }

    /**
     * @return array<int, array{0: float, 1: float}>
     */
    private function parseCoordinateListToLonLatPairs(string $coordText): array
    {
        $coordText = trim($coordText);
        if ($coordText === '') {
            return [];
        }

        $tokens = preg_split('/\s+/', $coordText);
        if (!is_array($tokens)) {
            return [];
        }

        $pairs = [];
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }

            $parts = array_map('trim', explode(',', $token));
            if (count($parts) < 2) {
                continue;
            }

            $lon = (float) $parts[0];
            $lat = (float) $parts[1];
            if (is_nan($lon) || is_nan($lat)) {
                continue;
            }
            $pairs[] = [$lon, $lat];
        }

        return $pairs;
    }
}
