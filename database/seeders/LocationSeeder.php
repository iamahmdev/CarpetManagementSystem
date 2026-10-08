<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $hanguWarehouse = Location::updateOrCreate(
            ['name' => 'Hangu Warehouse'],
            [
                'type' => 'warehouse',
                'city' => 'Hangu',
                'parent_id' => null,
                'active' => true,
            ],
        );

        $kohatWarehouse = Location::updateOrCreate(
            ['name' => 'Kohat Warehouse'],
            [
                'type' => 'warehouse',
                'city' => 'Kohat',
                'parent_id' => null,
                'active' => true,
            ],
        );

        Location::updateOrCreate(
            ['name' => 'Hangu Display'],
            [
                'type' => 'display',
                'city' => 'Hangu',
                'parent_id' => $hanguWarehouse->id,
                'active' => true,
            ],
        );

        Location::updateOrCreate(
            ['name' => 'Kohat Display'],
            [
                'type' => 'display',
                'city' => 'Kohat',
                'parent_id' => $kohatWarehouse->id,
                'active' => true,
            ],
        );
    }
}
