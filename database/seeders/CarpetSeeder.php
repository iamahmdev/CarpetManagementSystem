<?php

namespace Database\Seeders;

use App\Models\Carpet;
use Illuminate\Database\Seeder;

class CarpetSeeder extends Seeder
{
    public function run(): void
    {
        $carpets = [
            ['TBZ-6x9-IND', 'Tabriz Herati', 'Tabriz', 'Handmade', 'Wool and silk', 'Indigo', '6x9 ft', 185000],
            ['KSN-4x6-RED', 'Kashan Medallion', 'Kashan', 'Handmade', 'Wool', 'Red', '4x6 ft', 92500],
            ['QOM-5x8-IVR', 'Qom Garden', 'Qom', 'Handmade', 'Silk', 'Ivory', '5x8 ft', 148000],
            ['ISF-8x10-NVY', 'Isfahan Shah Abbasi', 'Isfahan', 'Handmade', 'Wool and silk', 'Navy', '8x10 ft', 310000],
            ['NAN-3x5-CRM', 'Nain Fine Weave', 'Nain', 'Handmade', 'Wool and silk', 'Cream', '3x5 ft', 64000],
            ['BKH-6x9-RST', 'Bakhtiari Garden', 'Bakhtiari', 'Handmade', 'Wool', 'Rust', '6x9 ft', 128000],
            ['MSH-9x12-RED', 'Mashhad Classic', 'Mashhad', 'Handmade', 'Wool', 'Crimson', '9x12 ft', 265000],
            ['MCH-5x8-GRY', 'Modern Machine Made', 'Machine made', 'Machine made', 'Polyester', 'Grey', '5x8 ft', 24000],
        ];

        foreach ($carpets as [$sku, $name, $design, $weave, $material, $color, $size, $price]) {
            Carpet::updateOrCreate(
                ['sku' => $sku],
                compact('name', 'design', 'weave', 'material', 'color', 'size', 'price'),
            );
        }
    }
}
