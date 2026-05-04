<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Convert weight string to decimal kg value.
     * Supports formats like '1kg', '1.2kg', '500g', '330g'.
     */
    private function parseWeight(string $weightString): float
    {
        $weightString = trim(strtolower($weightString));

        if (preg_match('/^([\d.]+)\s*(kg|g|gram|kilogram)?$/', $weightString, $matches)) {
            $value = (float) $matches[1];
            $unit = $matches[2] ?? 'kg';

            if (in_array($unit, ['g', 'gram'])) {
                $value = $value / 1000;
            }

            return round($value, 4);
        }

        return 1.0000;
    }

    /**
     * @return array<string, array<int, array{name: string, description: string, weight: string, dimension: string, specs: array<string, mixed>}>>
     */
    private function catalog(): array
    {
        return [
            'Smart Mart' => [
                ['name' => 'Premium Jasmine Rice 5kg', 'description' => 'Soft and fragrant jasmine rice for daily meals.', 'weight' => '5kg', 'dimension' => '35x25x8cm', 'specs' => ['category' => 'rice', 'pack' => '5kg']],
                ['name' => 'Cane Sugar 1kg', 'description' => 'Fine cane sugar for cooking and beverages.', 'weight' => '1kg', 'dimension' => '20x12x5cm', 'specs' => ['category' => 'baking', 'pack' => '1kg']],
                ['name' => 'Laundry Detergent Powder 1kg', 'description' => 'Fresh-scent detergent powder for everyday laundry.', 'weight' => '1kg', 'dimension' => '24x18x6cm', 'specs' => ['category' => 'cleaning', 'scent' => 'fresh']],
                ['name' => 'Dishwashing Liquid 500ml', 'description' => 'Grease-cutting dishwashing liquid for kitchen use.', 'weight' => '550g', 'dimension' => '8x6x20cm', 'specs' => ['category' => 'cleaning', 'volume' => '500ml']],
                ['name' => 'Toilet Tissue 12 Rolls', 'description' => 'Soft two-ply bathroom tissue for household use.', 'weight' => '900g', 'dimension' => '35x25x18cm', 'specs' => ['category' => 'paper goods', 'rolls' => 12]],
            ],
            'Food Corner' => [
                ['name' => 'Classic Potato Chips', 'description' => 'Crispy salted potato chips for snacking.', 'weight' => '160g', 'dimension' => '24x18x6cm', 'specs' => ['flavor' => 'classic salted', 'type' => 'snack']],
                ['name' => 'Chocolate Wafer Pack', 'description' => 'Layered chocolate wafers in a shareable pack.', 'weight' => '220g', 'dimension' => '22x14x5cm', 'specs' => ['flavor' => 'chocolate', 'type' => 'wafer']],
                ['name' => 'Iced Tea Bottles 6 Pack', 'description' => 'Refreshing ready-to-drink iced tea bottles.', 'weight' => '2kg', 'dimension' => '24x16x18cm', 'specs' => ['flavor' => 'lemon', 'count' => 6]],
                ['name' => 'Instant Noodle Bowl', 'description' => 'Convenient noodle bowl with savory seasoning.', 'weight' => '90g', 'dimension' => '12x12x9cm', 'specs' => ['type' => 'instant noodles', 'serving' => 'single']],
                ['name' => 'Ground Coffee 250g', 'description' => 'Aromatic medium-roast ground coffee.', 'weight' => '250g', 'dimension' => '18x10x6cm', 'specs' => ['roast' => 'medium', 'pack' => '250g']],
            ],
            'Wellness Hub' => [
                ['name' => 'Vitamin C Tablets', 'description' => 'Daily vitamin C supplement tablets.', 'weight' => '120g', 'dimension' => '8x8x10cm', 'specs' => ['count' => 100, 'strength' => '500mg']],
                ['name' => 'Alcohol Sanitizer 500ml', 'description' => 'Ethyl alcohol sanitizer for hand and surface use.', 'weight' => '520g', 'dimension' => '8x6x20cm', 'specs' => ['volume' => '500ml', 'type' => 'sanitizer']],
                ['name' => 'Digital Thermometer', 'description' => 'Fast-read digital thermometer for home health checks.', 'weight' => '60g', 'dimension' => '16x5x3cm', 'specs' => ['type' => 'digital', 'use' => 'oral/underarm']],
                ['name' => 'Adult Face Masks 50 Pack', 'description' => 'Disposable face masks with comfortable ear loops.', 'weight' => '250g', 'dimension' => '20x12x10cm', 'specs' => ['count' => 50, 'layers' => 3]],
                ['name' => 'Gentle Body Wash 400ml', 'description' => 'Mild body wash for daily personal care.', 'weight' => '430g', 'dimension' => '9x6x22cm', 'specs' => ['volume' => '400ml', 'skin_type' => 'all']],
            ],
            'Glam Beauty' => [
                ['name' => 'Hydrating Facial Wash', 'description' => 'Gentle hydrating facial wash for all skin types.', 'weight' => '130g', 'dimension' => '8x6x12cm', 'specs' => ['volume' => '120ml', 'type' => 'cleanser']],
                ['name' => 'Volumizing Hair Shampoo', 'description' => 'Volumizing shampoo for fuller hair.', 'weight' => '270g', 'dimension' => '8x6x15cm', 'specs' => ['volume' => '250ml', 'type' => 'shampoo']],
                ['name' => 'Matte Lip Tint', 'description' => 'Lightweight matte lip tint with rich color.', 'weight' => '30g', 'dimension' => '10x2x2cm', 'specs' => ['finish' => 'matte', 'type' => 'lip tint']],
                ['name' => 'Daily Sunscreen SPF50', 'description' => 'Lightweight sunscreen for daily face protection.', 'weight' => '80g', 'dimension' => '12x5x3cm', 'specs' => ['spf' => 50, 'type' => 'sunscreen']],
                ['name' => 'Micellar Cleansing Water', 'description' => 'No-rinse makeup remover and facial cleanser.', 'weight' => '320g', 'dimension' => '7x5x18cm', 'specs' => ['volume' => '300ml', 'type' => 'cleanser']],
            ],
            'Home & Heart' => [
                ['name' => 'Microfiber Towel Set', 'description' => 'Absorbent microfiber towels for home cleaning.', 'weight' => '350g', 'dimension' => '28x18x8cm', 'specs' => ['count' => 6, 'material' => 'microfiber']],
                ['name' => 'LED Desk Lamp', 'description' => 'Adjustable LED desk lamp for study or work.', 'weight' => '700g', 'dimension' => '30x14x14cm', 'specs' => ['type' => 'LED', 'power' => 'USB']],
                ['name' => 'Storage Basket', 'description' => 'Durable woven basket for organizing household items.', 'weight' => '600g', 'dimension' => '32x24x18cm', 'specs' => ['material' => 'plastic weave', 'use' => 'storage']],
                ['name' => 'Nonstick Frying Pan', 'description' => 'Everyday nonstick frying pan for easy cooking.', 'weight' => '900g', 'dimension' => '42x26x8cm', 'specs' => ['diameter' => '26cm', 'coating' => 'nonstick']],
                ['name' => 'Scented Candle Jar', 'description' => 'Long-burning candle for cozy home fragrance.', 'weight' => '400g', 'dimension' => '9x9x11cm', 'specs' => ['scent' => 'vanilla', 'burn_time' => '30 hours']],
            ],
            'Fashion Hub' => [
                ['name' => 'Cotton Crew Neck Shirt', 'description' => 'Soft cotton shirt for casual everyday wear.', 'weight' => '220g', 'dimension' => '28x22x3cm', 'specs' => ['material' => 'cotton', 'fit' => 'regular']],
                ['name' => 'Slim Denim Jeans', 'description' => 'Classic slim-fit denim jeans.', 'weight' => '650g', 'dimension' => '35x28x5cm', 'specs' => ['material' => 'denim', 'fit' => 'slim']],
                ['name' => 'Canvas Tote Bag', 'description' => 'Reusable canvas tote bag with roomy storage.', 'weight' => '250g', 'dimension' => '38x34x2cm', 'specs' => ['material' => 'canvas', 'type' => 'tote']],
                ['name' => 'Everyday Sneakers', 'description' => 'Comfortable sneakers for daily walking.', 'weight' => '800g', 'dimension' => '32x22x12cm', 'specs' => ['type' => 'sneakers', 'upper' => 'mesh']],
                ['name' => 'Leather Belt', 'description' => 'Adjustable leather belt with metal buckle.', 'weight' => '180g', 'dimension' => '18x12x4cm', 'specs' => ['material' => 'leather', 'buckle' => 'metal']],
            ],
            'Tech World' => [
                ['name' => 'Fast Charging USB-C Cable', 'description' => 'Durable USB-C cable for fast charging.', 'weight' => '50g', 'dimension' => '15x8x2cm', 'specs' => ['length' => '1m', 'type' => 'USB-C']],
                ['name' => '20W Wall Charger', 'description' => 'Fast wall charger for smartphones.', 'weight' => '100g', 'dimension' => '8x8x3cm', 'specs' => ['wattage' => '20W', 'port' => 'USB-C']],
                ['name' => 'Wireless Earbuds', 'description' => 'Wireless earbuds with compact charging case.', 'weight' => '50g', 'dimension' => '10x8x6cm', 'specs' => ['connectivity' => 'Bluetooth', 'battery' => '8 hours']],
                ['name' => 'Phone Tripod Stand', 'description' => 'Adjustable tripod stand for phones and content capture.', 'weight' => '450g', 'dimension' => '32x8x8cm', 'specs' => ['height' => 'adjustable', 'mount' => 'phone']],
                ['name' => 'Bluetooth Speaker', 'description' => 'Portable speaker with clear wireless audio.', 'weight' => '520g', 'dimension' => '18x8x8cm', 'specs' => ['connectivity' => 'Bluetooth', 'battery' => '10 hours']],
            ],
            'Scholar\'s Place' => [
                ['name' => 'A4 Bond Paper Ream', 'description' => 'Smooth white A4 paper for printing and copying.', 'weight' => '2.4kg', 'dimension' => '30x22x5cm', 'specs' => ['size' => 'A4', 'sheets' => 500]],
                ['name' => 'Ballpoint Pens 12 Pack', 'description' => 'Reliable ballpoint pens for school and office use.', 'weight' => '180g', 'dimension' => '16x8x3cm', 'specs' => ['count' => 12, 'ink' => 'blue']],
                ['name' => 'Spiral Notebook Set', 'description' => 'Lined spiral notebooks for class notes.', 'weight' => '900g', 'dimension' => '25x18x6cm', 'specs' => ['count' => 3, 'pages' => 80]],
                ['name' => 'Desk Organizer', 'description' => 'Compact organizer for pens, clips, and notes.', 'weight' => '380g', 'dimension' => '20x12x10cm', 'specs' => ['material' => 'plastic', 'compartments' => 5]],
                ['name' => 'Highlighter Set', 'description' => 'Bright highlighters for reviewing notes.', 'weight' => '120g', 'dimension' => '14x8x2cm', 'specs' => ['count' => 6, 'type' => 'chisel tip']],
            ],
            'Little Stars' => [
                ['name' => 'Baby Diapers Pack', 'description' => 'Soft disposable diapers with leak protection.', 'weight' => '1.2kg', 'dimension' => '34x24x14cm', 'specs' => ['count' => 36, 'type' => 'diaper']],
                ['name' => 'Baby Wipes 3 Pack', 'description' => 'Gentle unscented baby wipes for daily care.', 'weight' => '900g', 'dimension' => '24x14x12cm', 'specs' => ['count' => 240, 'type' => 'wipes']],
                ['name' => 'Feeding Bottle 250ml', 'description' => 'BPA-free feeding bottle with soft nipple.', 'weight' => '120g', 'dimension' => '7x7x18cm', 'specs' => ['volume' => '250ml', 'material' => 'BPA-free plastic']],
                ['name' => 'Soft Plush Toy', 'description' => 'Cuddly plush toy for toddlers.', 'weight' => '280g', 'dimension' => '24x18x12cm', 'specs' => ['material' => 'plush', 'age' => '1+']],
                ['name' => 'Kids Coloring Book', 'description' => 'Fun coloring book for young children.', 'weight' => '220g', 'dimension' => '28x22x1cm', 'specs' => ['pages' => 48, 'age' => '3+']],
            ],
            'Pet Paradise' => [
                ['name' => 'Adult Dog Food 2kg', 'description' => 'Balanced dry food for adult dogs.', 'weight' => '2kg', 'dimension' => '34x22x9cm', 'specs' => ['pet' => 'dog', 'type' => 'dry food']],
                ['name' => 'Cat Litter 5L', 'description' => 'Absorbent cat litter for odor control.', 'weight' => '3kg', 'dimension' => '30x22x10cm', 'specs' => ['pet' => 'cat', 'volume' => '5L']],
                ['name' => 'Pet Shampoo 300ml', 'description' => 'Gentle shampoo for clean and fresh coats.', 'weight' => '330g', 'dimension' => '8x5x18cm', 'specs' => ['volume' => '300ml', 'use' => 'coat care']],
                ['name' => 'Chew Toy Bone', 'description' => 'Durable chew toy for dogs.', 'weight' => '180g', 'dimension' => '16x6x5cm', 'specs' => ['pet' => 'dog', 'material' => 'rubber']],
                ['name' => 'Pet Feeding Bowl', 'description' => 'Non-slip feeding bowl for cats and dogs.', 'weight' => '260g', 'dimension' => '18x18x6cm', 'specs' => ['material' => 'stainless steel', 'base' => 'non-slip']],
            ],
            'Auto Supplies' => [
                ['name' => 'Microfiber Car Cloths', 'description' => 'Soft cloths for car cleaning and detailing.', 'weight' => '300g', 'dimension' => '26x18x6cm', 'specs' => ['count' => 6, 'material' => 'microfiber']],
                ['name' => 'Car Shampoo 1L', 'description' => 'Foaming shampoo for exterior car washing.', 'weight' => '1.1kg', 'dimension' => '10x8x24cm', 'specs' => ['volume' => '1L', 'use' => 'car wash']],
                ['name' => 'Windshield Wiper Pair', 'description' => 'Replacement windshield wipers for clear visibility.', 'weight' => '450g', 'dimension' => '60x8x4cm', 'specs' => ['count' => 2, 'type' => 'wiper']],
                ['name' => 'Tire Pressure Gauge', 'description' => 'Compact gauge for checking tire pressure.', 'weight' => '160g', 'dimension' => '14x6x3cm', 'specs' => ['unit' => 'PSI', 'type' => 'analog']],
                ['name' => 'Dashboard Phone Holder', 'description' => 'Secure phone mount for dashboard use.', 'weight' => '220g', 'dimension' => '12x10x8cm', 'specs' => ['mount' => 'dashboard', 'rotation' => '360 degree']],
            ],
            'Build Pro' => [
                ['name' => 'Steel Claw Hammer', 'description' => 'Durable hammer for repairs and construction.', 'weight' => '800g', 'dimension' => '32x12x4cm', 'specs' => ['material' => 'steel', 'type' => 'claw hammer']],
                ['name' => 'Screwdriver Set', 'description' => 'Multi-size screwdriver set for home repairs.', 'weight' => '600g', 'dimension' => '24x16x4cm', 'specs' => ['pieces' => 8, 'type' => 'screwdriver']],
                ['name' => 'Measuring Tape 5m', 'description' => 'Locking measuring tape for accurate measurements.', 'weight' => '250g', 'dimension' => '8x8x4cm', 'specs' => ['length' => '5m', 'lock' => true]],
                ['name' => 'Work Gloves Pair', 'description' => 'Protective work gloves with textured grip.', 'weight' => '180g', 'dimension' => '24x12x3cm', 'specs' => ['count' => 2, 'grip' => 'textured']],
                ['name' => 'Utility Knife', 'description' => 'Retractable utility knife for cutting tasks.', 'weight' => '150g', 'dimension' => '16x5x2cm', 'specs' => ['blade' => 'retractable', 'use' => 'cutting']],
            ],
            'Sport Zone' => [
                ['name' => 'Yoga Mat', 'description' => 'Non-slip mat for yoga and floor workouts.', 'weight' => '900g', 'dimension' => '60x12x12cm', 'specs' => ['thickness' => '6mm', 'surface' => 'non-slip']],
                ['name' => 'Insulated Water Bottle', 'description' => 'Reusable bottle that keeps drinks cold or hot.', 'weight' => '420g', 'dimension' => '8x8x26cm', 'specs' => ['volume' => '750ml', 'type' => 'insulated']],
                ['name' => 'Resistance Band Set', 'description' => 'Exercise bands for strength and mobility training.', 'weight' => '300g', 'dimension' => '20x14x5cm', 'specs' => ['count' => 5, 'type' => 'resistance bands']],
                ['name' => 'Basketball', 'description' => 'Durable basketball for indoor and outdoor play.', 'weight' => '650g', 'dimension' => '24x24x24cm', 'specs' => ['size' => 7, 'use' => 'indoor/outdoor']],
                ['name' => 'Camping Flashlight', 'description' => 'Bright portable flashlight for outdoor trips.', 'weight' => '280g', 'dimension' => '16x5x5cm', 'specs' => ['mode' => 'multi-mode', 'power' => 'battery']],
            ],
            'General Store' => [
                ['name' => 'Reusable Shopping Bag', 'description' => 'Foldable shopping bag for daily errands.', 'weight' => '90g', 'dimension' => '18x12x2cm', 'specs' => ['material' => 'polyester', 'type' => 'foldable']],
                ['name' => 'Umbrella', 'description' => 'Compact umbrella for sun and rain protection.', 'weight' => '350g', 'dimension' => '28x6x6cm', 'specs' => ['type' => 'compact', 'canopy' => 'water resistant']],
                ['name' => 'Basic Sewing Kit', 'description' => 'Portable sewing kit for quick clothing fixes.', 'weight' => '120g', 'dimension' => '14x10x3cm', 'specs' => ['type' => 'sewing kit', 'use' => 'repairs']],
                ['name' => 'AA Batteries 8 Pack', 'description' => 'Reliable AA batteries for household devices.', 'weight' => '200g', 'dimension' => '12x8x2cm', 'specs' => ['count' => 8, 'size' => 'AA']],
                ['name' => 'Travel Organizer Pouch', 'description' => 'Compact pouch for cables, toiletries, or small items.', 'weight' => '180g', 'dimension' => '22x14x5cm', 'specs' => ['material' => 'nylon', 'use' => 'travel']],
            ],
        ];

    }

    public function run(): void
    {
        $storesByName = Store::query()->get()->keyBy('store_name');
        $categoriesByName = Category::query()->get()->keyBy('name');

        foreach ($this->catalog() as $storeName => $products) {
            $store = $storesByName->get($storeName);

            if (!$store) {
                $this->command?->warn("Skipping products for missing store: {$storeName}");
                continue;
            }

            $category = $categoriesByName->firstWhere('id', $store->category_id);

            if (!$category) {
                $this->command?->warn("Skipping products for store without category: {$storeName}");
                continue;
            }

            foreach ($products as $productData) {
                $product = Product::firstOrNew([
                    'store_id' => $store->id,
                    'name' => $productData['name'],
                ]);

                $product->fill([
                    'uuid' => $product->uuid ?: Str::uuid(),
                    'store_id' => $store->id,
                    'category_id' => $category->id,
                    'name' => $productData['name'],
                    'description' => $productData['description'],
                    'weight' => $this->parseWeight($productData['weight']),
                    'dimension' => $productData['dimension'],
                    'specs' => $productData['specs'],
                    'status' => 'active',
                ]);
                $product->save();
            }
        }
    }
}
