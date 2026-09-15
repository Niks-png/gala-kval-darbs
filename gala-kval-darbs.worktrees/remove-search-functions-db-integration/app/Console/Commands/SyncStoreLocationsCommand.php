<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SyncStoreLocationsCommand extends Command
{
    protected $signature = 'stores:sync';

    protected $description = 'Download Top and Maxima store locations from OpenStreetMap';

    public function handle(): int
    {
        $query = <<<'OVERPASS'
[out:json][timeout:90];
(
  nwr["brand"~"maxima|top",i](55.6,20.9,58.1,28.3);
  nwr["name"~"maxima|top",i](55.6,20.9,58.1,28.3);
);
out center tags;
OVERPASS;

        $response = null;
        foreach ([
            'https://overpass-api.de/api/interpreter',
            'https://overpass.kumi.systems/api/interpreter',
            'https://overpass.private.coffee/api/interpreter',
        ] as $endpoint) {
            try {
                $candidate = Http::asForm()->timeout(120)->post($endpoint, ['data' => $query]);
                if ($candidate->successful()) {
                    $response = $candidate->json();
                    break;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if (! is_array($response) || ! isset($response['elements'])) {
            throw new RuntimeException('No store location API responded successfully.');
        }

        $locations = collect($response['elements'])
            ->map(function (array $store): ?array {
                $tags = $store['tags'] ?? [];
                $latitude = $store['lat'] ?? ($store['center']['lat'] ?? null);
                $longitude = $store['lon'] ?? ($store['center']['lon'] ?? null);
                $name = $tags['brand'] ?? $tags['name'] ?? '';

                if (! is_numeric($latitude) || ! is_numeric($longitude)) {
                    return null;
                }

                $network = str_contains(mb_strtolower($name), 'top') && ! str_contains(mb_strtolower($name), 'maxima')
                    ? 'Top'
                    : 'Maxima';

                return [
                    'network' => $network,
                    'name' => $tags['name'] ?? $network,
                    'latitude' => (float) $latitude,
                    'longitude' => (float) $longitude,
                    'address' => collect([
                        $tags['addr:street'] ?? null,
                        $tags['addr:housenumber'] ?? null,
                        $tags['addr:city'] ?? null,
                    ])->filter()->implode(' '),
                ];
            })
            ->filter()
            ->unique(fn (array $location): string => $location['latitude'].':'.$location['longitude'])
            ->values();

        if ($locations->isEmpty()) {
            throw new RuntimeException('The API returned no store locations.');
        }

        $path = public_path('data/store-locations.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode([
            'updated_at' => now()->toIso8601String(),
            'locations' => $locations,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $this->info("Saved {$locations->count()} store locations to {$path}.");

        return self::SUCCESS;
    }
}
