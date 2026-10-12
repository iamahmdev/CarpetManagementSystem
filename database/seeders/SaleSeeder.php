<?php

namespace Database\Seeders;

use App\Models\Carpet;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Piece;
use App\Models\PieceMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\UserRole;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SaleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Users
        $owner = User::query()->where('role', UserRole::OWNER->value)->first();
        if (! $owner) {
            $owner = User::query()->create([
                'name' => 'Owner',
                'email' => 'owner@example.test',
                'password' => Hash::make('password'),
                'role' => UserRole::OWNER,
            ]);
        }

        $admin = User::query()->where('role', UserRole::ADMIN->value)->first();
        if (! $admin) {
            $admin = User::query()->create([
                'name' => 'Admin',
                'email' => 'admin@example.test',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN,
            ]);
        }

        $zeeshan = User::updateOrCreate(
            ['email' => 'zeeshan@example.test'],
            [
                'name' => 'Zeeshan',
                'password' => Hash::make('password'),
                'role' => UserRole::SALES,
            ],
        );

        $ayesha = User::updateOrCreate(
            ['email' => 'ayesha@example.test'],
            [
                'name' => 'Ayesha',
                'password' => Hash::make('password'),
                'role' => UserRole::SALES,
            ],
        );

        // 2. Locations
        $hangu = Location::query()->where('name', 'Hangu Display')->first();
        $kohat = Location::query()->where('name', 'Kohat Display')->first();
        if (! $hangu || ! $kohat) {
            $this->call(LocationSeeder::class);
            $hangu = Location::query()->where('name', 'Hangu Display')->firstOrFail();
            $kohat = Location::query()->where('name', 'Kohat Display')->firstOrFail();
        }

        // 3. Customers
        $this->call(CustomerSeeder::class);
        $customers = Customer::query()->get()->keyBy('name');

        // 4. Carpets
        $carpets = Carpet::query()->get()->keyBy('name');

        // Check if sales already seeded
        if (Sale::query()->where('invoice_number', 'INV-2026-1041')->exists()) {
            return;
        }

        $seedData = [
            [
                'invoice' => 'INV-2026-1041',
                'sold_at' => Carbon::parse('2026-09-20 11:15:00'),
                'location' => $kohat,
                'user' => $ayesha,
                'customer' => $customers->get('Dr. Farhan Ali'),
                'paid_now' => 108900, // ~60% partial
                'method' => 'cash',
                'items' => [
                    ['carpet' => 'Tabriz Herati', 'price' => 181500, 'cost' => 120000],
                ],
            ],
            [
                'invoice' => 'INV-2026-1042',
                'sold_at' => Carbon::parse('2026-09-24 14:30:00'),
                'location' => $kohat,
                'user' => $ayesha,
                'customer' => $customers->get('Dr. Farhan Ali'),
                'paid_now' => 179500,
                'method' => 'bank_transfer',
                'items' => [
                    ['carpet' => 'Tabriz Herati', 'price' => 179500, 'cost' => 120000],
                ],
            ],
            [
                'invoice' => 'INV-2026-1043',
                'sold_at' => Carbon::parse('2026-09-27 10:45:00'),
                'location' => $hangu,
                'user' => $zeeshan,
                'customer' => null,
                'paid_now' => 142000,
                'method' => 'mobile_wallet',
                'items' => [
                    ['carpet' => 'Qom Garden', 'price' => 142000, 'cost' => 95000],
                ],
            ],
            [
                'invoice' => 'INV-2026-1044',
                'sold_at' => Carbon::parse('2026-09-28 09:30:00'),
                'location' => $hangu,
                'user' => $zeeshan,
                'customer' => null,
                'paid_now' => 145500,
                'method' => 'cash',
                'items' => [
                    ['carpet' => 'Qom Garden', 'price' => 145500, 'cost' => 95000],
                ],
            ],
            [
                'invoice' => 'INV-2026-1045',
                'sold_at' => Carbon::parse('2026-09-28 12:15:00'),
                'location' => $hangu,
                'user' => $zeeshan,
                'customer' => $customers->get('Sana Interiors'),
                'paid_now' => 146000,
                'method' => 'bank_transfer',
                'items' => [
                    ['carpet' => 'Qom Garden', 'price' => 146000, 'cost' => 95000],
                ],
            ],
            [
                'invoice' => 'INV-2026-1046',
                'sold_at' => Carbon::parse('2026-09-28 16:00:00'),
                'location' => $hangu,
                'user' => $zeeshan,
                'customer' => null,
                'paid_now' => 613000,
                'method' => 'mobile_wallet',
                'items' => [
                    ['carpet' => 'Isfahan Shah Abbasi', 'price' => 330000, 'cost' => 200000],
                    ['carpet' => 'Mashhad Classic', 'price' => 283000, 'cost' => 170000],
                ],
            ],
            [
                'invoice' => 'INV-2026-1047',
                'sold_at' => Carbon::parse('2026-09-30 15:20:00'),
                'location' => $hangu,
                'user' => $zeeshan,
                'customer' => $customers->get('Dr. Farhan Ali'),
                'paid_now' => 126000,
                'method' => 'cash',
                'items' => [
                    ['carpet' => 'Bakhtiari Garden', 'price' => 126000, 'cost' => 80000],
                ],
            ],
            [
                'invoice' => 'INV-2026-1048',
                'sold_at' => Carbon::parse('2026-10-05 11:00:00'),
                'location' => $kohat,
                'user' => $ayesha,
                'customer' => null,
                'paid_now' => 180500,
                'method' => 'bank_transfer',
                'items' => [
                    ['carpet' => 'Tabriz Herati', 'price' => 180500, 'cost' => 120000],
                ],
            ],
            [
                'invoice' => 'INV-2026-1049',
                'sold_at' => Carbon::parse('2026-10-08 17:10:00'),
                'location' => $kohat,
                'user' => $ayesha,
                'customer' => $customers->get('Haji Rahim Khan'),
                'paid_now' => 181500,
                'method' => 'cash',
                'items' => [
                    ['carpet' => 'Kashan Medallion', 'price' => 91000, 'cost' => 60000],
                    ['carpet' => 'Kashan Medallion', 'price' => 90500, 'cost' => 60000],
                ],
            ],
        ];

        DB::transaction(function () use ($seedData, $carpets) {
            $pieceCounter = (int) (Piece::query()->max('id') ?? 3600);

            foreach ($seedData as $row) {
                $subtotal = collect($row['items'])->sum('price');
                $total = $subtotal;

                $sale = Sale::query()->create([
                    'invoice_number' => $row['invoice'],
                    'customer_id' => $row['customer']?->id,
                    'location_id' => $row['location']->id,
                    'created_by' => $row['user']->id,
                    'status' => 'completed',
                    'subtotal' => $subtotal,
                    'discount' => 0,
                    'total' => $total,
                    'sold_at' => $row['sold_at'],
                ]);

                foreach ($row['items'] as $itemData) {
                    $carpet = $carpets->get($itemData['carpet']) ?? Carpet::query()->first();
                    $pieceCounter++;
                    $pieceCode = 'CP-'.str_pad((string) $pieceCounter, 6, '0', STR_PAD_LEFT);

                    $piece = Piece::query()->create([
                        'code' => $pieceCode,
                        'carpet_id' => $carpet->id,
                        'location_id' => $row['location']->id,
                        'status' => 'sold',
                        'grade' => 'A',
                        'landed_cost' => $itemData['cost'],
                        'price' => $itemData['price'],
                        'received_at' => $row['sold_at']->copy()->subDays(10),
                    ]);

                    SaleItem::query()->create([
                        'sale_id' => $sale->id,
                        'piece_id' => $piece->id,
                        'piece_code' => $piece->code,
                        'carpet_id' => $carpet->id,
                        'sku' => $carpet->sku,
                        'name' => "{$carpet->name} {$carpet->size}",
                        'unit_price' => $itemData['price'],
                        'unit_cost' => $itemData['cost'],
                    ]);

                    PieceMovement::query()->create([
                        'piece_id' => $piece->id,
                        'type' => 'sale',
                        'from_location_id' => $row['location']->id,
                        'to_location_id' => null,
                        'reference' => $sale->invoice_number,
                        'user_id' => $row['user']->id,
                        'moved_at' => $row['sold_at'],
                    ]);
                }

                if ($row['paid_now'] > 0) {
                    static $receiptSeq = 1000;
                    $receiptSeq++;
                    $receiptNumber = sprintf('REC-%d-%04d', $row['sold_at']->year, $receiptSeq);

                    Payment::query()->create([
                        'sale_id' => $sale->id,
                        'customer_id' => $row['customer']?->id,
                        'amount' => $row['paid_now'],
                        'method' => $row['method'],
                        'status' => 'active',
                        'receipt_number' => $receiptNumber,
                        'received_at' => $row['sold_at'],
                        'received_by' => $row['user']->id,
                    ]);
                }
            }

            // Update document_sequences so next invoice starts at 1050
            DB::table('document_sequences')->updateOrInsert(
                ['document_type' => 'invoice', 'year' => 2026],
                ['last_number' => 1049],
            );
        });
    }
}
