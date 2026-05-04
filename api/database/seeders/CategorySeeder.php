<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Groceries & Essentials',     'description' => 'Daily needs and household basics', 'status' => 1],
            ['name' => 'Food & Beverages',           'description' => 'Snacks, drinks, and ready-to-eat food items', 'status' => 1],
            ['name' => 'Health & Personal Care',     'description' => 'Medicines, vitamins, and personal hygiene products', 'status' => 1],
            ['name' => 'Beauty & Skincare',          'description' => 'Cosmetics, skincare, and grooming essentials', 'status' => 1],
            ['name' => 'Home & Living',              'description' => 'Home essentials, cleaning supplies, and utilities', 'status' => 1],
            ['name' => 'Clothing & Accessories',     'description' => 'Apparel, footwear, and fashion accessories', 'status' => 1],
            ['name' => 'Electronics & Gadgets',      'description' => 'Mobile devices, accessories, and electronics', 'status' => 1],
            ['name' => 'School & Office Supplies',   'description' => 'Stationery, books, and office essentials', 'status' => 1],
            ['name' => 'Baby & Kids',                'description' => 'Baby care, toys, and children essentials', 'status' => 1],
            ['name' => 'Pet Care',                   'description' => 'Pet food, grooming, and care supplies', 'status' => 1],
            ['name' => 'Automotive',                 'description' => 'Auto parts, tools, and accessories', 'status' => 1],
            ['name' => 'Hardware & Tools',           'description' => 'Tools, construction, and repair supplies', 'status' => 1],
            ['name' => 'Sports & Outdoor',           'description' => 'Fitness, sports gear, and outdoor items', 'status' => 1],
            ['name' => 'Others',                     'description' => 'Miscellaneous items and uncategorized products', 'status' => 1],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                $category,
            );
        }
    }
}
