<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Location;
use App\Models\Store;
use App\Models\Category;
use App\Models\StoreVerification;
use App\Models\StoreVerificationLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $reviewedAt = Carbon::now();
        // ── Admins (2) ──────────────────────────────────────
        foreach ([
            ['firstname' => 'Admin',   'lastname' => 'SukiCart',  'contact_number' => '09170000001', 'email' => 'admin@sukicart.com'],
            ['firstname' => 'Jessel',    'lastname' => 'Zapanta',     'contact_number' => '09170000002', 'email' => 'jessel.zapanta@nmsc.edu.ph'],
        ] as $admin) {
            $this->upsertUser(array_merge($admin, [
                'role' => 'admin',
            ]));
        }

        $reviewer = User::where('email', 'admin@sukicart.com')->first();

        // ── Customers (10) ──────────────────────────────────
        $customers = [
            ['firstname' => 'Juan',    'lastname' => 'Dela Cruz',   'contact_number' => '09170000003', 'email' => 'juan@example.com',    'region' => 'NCR',          'province' => 'Metro Manila',   'city' => 'Quezon City',   'barangay' => 'Barangay Holy Spirit'],
            ['firstname' => 'Maria',   'lastname' => 'Garcia',      'contact_number' => '09170000004', 'email' => 'maria@example.com',   'region' => 'NCR',          'province' => 'Metro Manila',   'city' => 'Makati',        'barangay' => 'Barangay Poblacion'],
            ['firstname' => 'Jose',    'lastname' => 'Reyes',       'contact_number' => '09170000005', 'email' => 'jose@example.com',    'region' => 'Region IV-A',  'province' => 'Cavite',         'city' => 'Bacoor',        'barangay' => 'Barangay Molino'],
            ['firstname' => 'Ana',     'lastname' => 'Bautista',    'contact_number' => '09170000006', 'email' => 'ana@example.com',     'region' => 'Region III',   'province' => 'Bulacan',        'city' => 'Meycauayan',    'barangay' => 'Barangay Calvario'],
            ['firstname' => 'Ramon',   'lastname' => 'Mendoza',     'contact_number' => '09170000007', 'email' => 'ramon@example.com',   'region' => 'NCR',          'province' => 'Metro Manila',   'city' => 'Pasig',         'barangay' => 'Barangay Kapitolyo'],
            ['firstname' => 'Liza',    'lastname' => 'Tan',         'contact_number' => '09170000008', 'email' => 'liza@example.com',    'region' => 'Region IV-A',  'province' => 'Laguna',         'city' => 'San Pedro',     'barangay' => 'Barangay Landayan'],
            ['firstname' => 'Carlos',  'lastname' => 'Villanueva',  'contact_number' => '09170000009', 'email' => 'carlos@example.com',  'region' => 'NCR',          'province' => 'Metro Manila',   'city' => 'Manila',        'barangay' => 'Barangay Sampaloc'],
            ['firstname' => 'Diane',   'lastname' => 'Aquino',      'contact_number' => '09170000010', 'email' => 'diane@example.com',   'region' => 'Region III',   'province' => 'Pampanga',       'city' => 'San Fernando',  'barangay' => 'Barangay Dolores'],
            ['firstname' => 'Mark',    'lastname' => 'Gonzales',    'contact_number' => '09170000011', 'email' => 'mark@example.com',    'region' => 'NCR',          'province' => 'Metro Manila',   'city' => 'Taguig',        'barangay' => 'Barangay Ususan'],
            ['firstname' => 'Elena',   'lastname' => 'Ramos',       'contact_number' => '09170000012', 'email' => 'elena@example.com',   'region' => 'Region IV-A',  'province' => 'Rizal',          'city' => 'Antipolo',      'barangay' => 'Barangay Dela Paz'],
        ];

        foreach ($customers as $c) {
            $user = $this->upsertUser([
                'firstname'      => $c['firstname'],
                'lastname'       => $c['lastname'],
                'contact_number' => $c['contact_number'],
                'role'           => 'customer',
                'email'          => $c['email'],
            ]);

            $this->upsertLocation($user->id, 'customer', [
                'status'            => 1,
                'region'            => $c['region'],
                'province'          => $c['province'],
                'city_municipality' => $c['city'],
                'barangay'          => $c['barangay'],
            ]);
        }

        // ── Sellers (14) ────────────────────────────────────
        $sellers = [
            ['firstname' => 'Miguel',   'lastname' => 'Santos',     'contact_number' => '09170000013', 'email' => 'miguel@example.com',    'store_name' => 'Smart Mart',               'category' => 'Groceries & Essentials',     'description' => 'One-stop shop for daily groceries and household essentials.', 'region' => 'NCR',          'province' => 'Metro Manila',   'city' => 'Manila',        'barangay' => 'Barangay Ermita'],
            ['firstname' => 'Rosa',     'lastname' => 'Chua',       'contact_number' => '09170000014', 'email' => 'rosa.chua@example.com', 'store_name' => 'Food Corner',              'category' => 'Food & Beverages',           'description' => 'Quality snacks, beverages, and ready-to-eat food items.',    'region' => 'NCR',          'province' => 'Metro Manila',   'city' => 'Quezon City',   'barangay' => 'Barangay Tandang Sora'],
            ['firstname' => 'Dr. Rafael', 'lastname' => 'Medina',   'contact_number' => '09170000015', 'email' => 'rafael.med@example.com', 'store_name' => 'Wellness Hub',             'category' => 'Health & Personal Care',    'description' => 'Premium medicines, vitamins, and personal care products.',  'region' => 'Region IV-A',  'province' => 'Cavite',         'city' => 'Imus',          'barangay' => 'Barangay Poblacion'],
            ['firstname' => 'Grace',    'lastname' => 'De La Cruz', 'contact_number' => '09170000016', 'email' => 'grace.dlc@example.com', 'store_name' => 'Glam Beauty',              'category' => 'Beauty & Skincare',          'description' => 'Premium cosmetics and professional skincare solutions.',     'region' => 'NCR',          'province' => 'Metro Manila',   'city' => 'Makati',        'barangay' => 'Barangay San Antonio'],
            ['firstname' => 'Mark',     'lastname' => 'Castillo',   'contact_number' => '09170000017', 'email' => 'mark.cast@example.com', 'store_name' => 'Home & Heart',             'category' => 'Home & Living',              'description' => 'Everything you need for a comfortable and clean home.',     'region' => 'NCR',          'province' => 'Metro Manila',   'city' => 'Pasig',         'barangay' => 'Barangay Rosario'],
            ['firstname' => 'Maria',    'lastname' => 'Reyes',      'contact_number' => '09170000018', 'email' => 'maria.reyes@example.com', 'store_name' => 'Fashion Hub',              'category' => 'Clothing & Accessories',     'description' => 'Trendy fashion apparel and stylish accessories for all.',    'region' => 'Region III',   'province' => 'Bulacan',        'city' => 'Malolos',       'barangay' => 'Barangay Mojon'],
            ['firstname' => 'Carlos',   'lastname' => 'Morales',    'contact_number' => '09170000019', 'email' => 'carlos.mor@example.com', 'store_name' => 'Tech World',               'category' => 'Electronics & Gadgets',      'description' => 'Latest gadgets, electronics, and tech accessories.',         'region' => 'NCR',          'province' => 'Metro Manila',   'city' => 'Taguig',        'barangay' => 'Barangay Western Bicutan'],
            ['firstname' => 'Anna',     'lastname' => 'Tutor',      'contact_number' => '09170000020', 'email' => 'anna.tutor@example.com', 'store_name' => 'Scholar\'s Place',         'category' => 'School & Office Supplies',   'description' => 'Complete selection of stationery and academic materials.',   'region' => 'Region IV-A',  'province' => 'Laguna',         'city' => 'Santa Rosa',    'barangay' => 'Barangay Balibago'],
            ['firstname' => 'Stephanie', 'lastname' => 'Aquino',    'contact_number' => '09170000021', 'email' => 'stephanie.aq@example.com', 'store_name' => 'Little Stars',             'category' => 'Baby & Kids',                'description' => 'Quality baby care products and safe toys for children.',      'region' => 'NCR',          'province' => 'Metro Manila',   'city' => 'Manila',        'barangay' => 'Barangay Sampaloc'],
            ['firstname' => 'Vincent',  'lastname' => 'Torres',     'contact_number' => '09170000022', 'email' => 'vincent.tor@example.com', 'store_name' => 'Pet Paradise',             'category' => 'Pet Care',                   'description' => 'Comprehensive pet care supplies and premium pet food.',       'region' => 'NCR',          'province' => 'Metro Manila',   'city' => 'Makati',        'barangay' => 'Barangay Poblacion'],
            ['firstname' => 'Ramon',    'lastname' => 'Garcia',     'contact_number' => '09170000023', 'email' => 'ramon.garcia@example.com', 'store_name' => 'Auto Supplies',            'category' => 'Automotive',                 'description' => 'Professional auto parts and accessories for vehicle care.',   'region' => 'Region IV-A',  'province' => 'Rizal',          'city' => 'Antipolo',      'barangay' => 'Barangay Dela Paz'],
            ['firstname' => 'Leo',      'lastname' => 'Bautista',   'contact_number' => '09170000024', 'email' => 'leo.baut@example.com',  'store_name' => 'Build Pro',                'category' => 'Hardware & Tools',            'description' => 'Complete range of tools and construction materials.',        'region' => 'NCR',          'province' => 'Metro Manila',   'city' => 'Pasig',         'barangay' => 'Barangay Kapitolyo'],
            ['firstname' => 'James',    'lastname' => 'Santos',     'contact_number' => '09170000025', 'email' => 'james.santo@example.com', 'store_name' => 'Sport Zone',               'category' => 'Sports & Outdoor',            'description' => 'Premium sports equipment and outdoor adventure gear.',       'region' => 'Region III',   'province' => 'Pampanga',       'city' => 'San Fernando',  'barangay' => 'Barangay Dolores'],
            ['firstname' => 'Elena',    'lastname' => 'Ramos',      'contact_number' => '09170000026', 'email' => 'elena.ramos@example.com', 'store_name' => 'General Store',            'category' => 'Others',                     'description' => 'Miscellaneous items and specialty products for everyone.',   'region' => 'Region IV-A',  'province' => 'Laguna',         'city' => 'San Pedro',     'barangay' => 'Barangay Landayan'],
        ];

        foreach ($sellers as $s) {
            $user = $this->upsertUser([
                'firstname'      => $s['firstname'],
                'lastname'       => $s['lastname'],
                'contact_number' => $s['contact_number'],
                'role'           => 'seller',
                'email'          => $s['email'],
            ]);

            $category = Category::where('name', $s['category'])->first();

            if (!$category) {
                $this->command?->warn("Skipping seller store for missing category: {$s['category']}");
                continue;
            }

            $store = $this->upsertStore($user->id, [
                'store_name'  => $s['store_name'],
                'category_id' => $category->id,
                'description' => $s['description'],
            ], $reviewedAt, $s['email']);

            $this->upsertLocation($user->id, 'store', [
                'status'            => 1,
                'region'            => $s['region'],
                'province'          => $s['province'],
                'city_municipality' => $s['city'],
                'barangay'          => $s['barangay'],
            ]);

            $this->upsertStoreApproval($store, $reviewer?->uuid, $reviewedAt, $s['email']);
        }
    }

    protected function upsertUser(array $attributes): User
    {
        $user = User::firstOrNew(['email' => $attributes['email']]);
        $user->fill([
            'uuid'              => $user->uuid ?: Str::uuid(),
            'firstname'         => $attributes['firstname'],
            'lastname'          => $attributes['lastname'],
            'contact_number'    => $attributes['contact_number'],
            'role'              => $attributes['role'],
            'email'             => $attributes['email'],
            'email_verified_at' => Carbon::now(),
            'password'          => bcrypt('password'),
        ]);
        $user->save();

        return $user;
    }

    protected function upsertLocation(int $userId, string $type, array $attributes): void
    {
        Location::updateOrCreate(
            [
                'user_id' => $userId,
                'type' => $type,
            ],
            $attributes
        );
    }

    protected function upsertStore(int $userId, array $attributes, Carbon $verifiedAt, string $email = ''): Store
    {
        $store = Store::firstOrNew(['user_id' => $userId]);
        $store->fill([
            'uuid' => $store->uuid ?: Str::uuid(),
            'store_name' => $attributes['store_name'],
            'category_id' => $attributes['category_id'],
            'description' => $attributes['description'],
            'verified_at' => $verifiedAt,
        ]);
        $store->save();

        return $store;
    }

    protected function upsertStoreApproval(Store $store, ?string $reviewedBy, Carbon $reviewedAt, string $email = ''): void
    {
        StoreVerification::updateOrCreate(
            [
                'store_id' => $store->id,
            ],
            [
                'store_status' => 'approved',
                'rejection_reason' => null,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => $reviewedAt,
            ]
        );
    }
}
