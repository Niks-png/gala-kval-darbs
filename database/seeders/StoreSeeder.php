<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Real Maxima branch locations in Riga, geocoded from OpenStreetMap data
     * (chain matches the "maxima.lv" value used in the products table).
     */
    public function run(): void
    {
        $stores = [
            ['name' => 'Maxima X, Brīvības gatve 428A', 'address' => 'Brīvības gatve 428A', 'lat' => 56.9885829, 'lng' => 24.2378794],
            ['name' => 'Maxima X, Lidoņu iela 3', 'address' => 'Lidoņu iela 3', 'lat' => 56.9708079, 'lng' => 24.0712398],
            ['name' => 'Maxima X, Jaunciema gatve 174', 'address' => 'Jaunciema gatve 174', 'lat' => 57.0439615, 'lng' => 24.1737501],
            ['name' => 'Maxima X, Dagmāras iela 11', 'address' => 'Dagmāras iela 11', 'lat' => 56.9646527, 'lng' => 24.0638291],
            ['name' => 'Maxima X, Brīvības gatve 310', 'address' => 'Brīvības gatve 310', 'lat' => 56.9788367, 'lng' => 24.1858749],
            ['name' => 'Maxima X, Latgales iela 118', 'address' => 'Latgales iela 118', 'lat' => 56.9385509, 'lng' => 24.1406044],
            ['name' => 'Maxima X, Dzirciema iela 51', 'address' => 'Dzirciema iela 51', 'lat' => 56.9598177, 'lng' => 24.0516940],
            ['name' => 'Maxima XXX, Slokas iela 115', 'address' => 'Slokas iela 115', 'lat' => 56.9604644, 'lng' => 24.0373227],
            ['name' => 'Maxima X, Jūrmalas gatve 85', 'address' => 'Jūrmalas gatve 85', 'lat' => 56.9541383, 'lng' => 24.0065101],
            ['name' => 'Maxima X, Brīvības gatve 406', 'address' => 'Brīvības gatve 406', 'lat' => 56.9875495, 'lng' => 24.2287083],
        ];

        foreach ($stores as $store) {
            Store::query()->updateOrCreate(
                ['address' => $store['address']],
                [
                    'name' => $store['name'],
                    'chain' => 'maxima.lv',
                    'city' => 'Rīga',
                    'lat' => $store['lat'],
                    'lng' => $store['lng'],
                ]
            );
        }
    }
}
