<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['name' => 'Haji Rahim Khan', 'phone' => '0300 1234567', 'city' => 'Hangu'],
            ['name' => 'Gul Mohammad Traders', 'phone' => '0333 9876543', 'city' => 'Kohat'],
            ['name' => 'Dr. Farhan Ali', 'phone' => '0321 5551234', 'city' => 'Peshawar'],
            ['name' => 'Sana Interiors', 'phone' => '0345 8889900', 'city' => 'Islamabad'],
            ['name' => 'Bilal Ahmad', 'phone' => '0312 4443322', 'city' => 'Kohat'],
        ];

        foreach ($customers as $customer) {
            Customer::updateOrCreate(
                ['name' => $customer['name']],
                [
                    'phone' => $customer['phone'],
                    'city' => $customer['city'],
                ],
            );
        }
    }
}
