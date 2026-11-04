<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
// use Illuminate\Database\Seeder;
use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;


class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'iPhone 16 Pro',
                'sku' => 'IP16-PRO',
                'category' => 'phones',
                'description' => 'Apple iPhone 16 Pro 256GB',
                'price' => 4999.00,
                'stock' => 20,
                'is_active' => true,
            ],
            [
                'name' => 'Samsung Galaxy S25',
                'sku' => 'SGS25',
                'category' => 'phones',
                'description' => 'Samsung Galaxy S25 256GB',
                'price' => 3999.00,
                'stock' => 15,
                'is_active' => true,
            ],
            [
                'name' => 'MacBook Air M4',
                'sku' => 'MBA-M4',
                'category' => 'laptops',
                'description' => 'Apple MacBook Air M4 16GB RAM',
                'price' => 4999.00,
                'stock' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'iPad Air M3',
                'sku' => 'IPAD-AIR-M3',
                'category' => 'tablets',
                'description' => 'Apple iPad Air M3 11 inch',
                'price' => 2799.00,
                'stock' => 8,
                'is_active' => true,
            ],
            [
                'name' => 'AirPods Pro 2',
                'sku' => 'APP-2',
                'category' => 'audio',
                'description' => 'Apple AirPods Pro 2nd Generation',
                'price' => 999.00,
                'stock' => 30,
                'is_active' => true,
            ],
        ];

        $categoryIds = Category::pluck('id', 'slug');

        // firstOrCreate, not updateOrCreate: re-seeding must not reset stock that orders have used.
        foreach ($products as $product) {
            $product['category_id'] = $categoryIds[$product['category']] ?? null;
            unset($product['category']);

            $model = Product::firstOrCreate(['sku' => $product['sku']], $product);

            // Products seeded before categories existed get their category filled in.
            if ($model->category_id === null && $product['category_id'] !== null) {
                $model->update(['category_id' => $product['category_id']]);
            }
        }
    }
}
