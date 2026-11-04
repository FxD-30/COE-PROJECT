<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'phones', 'name' => 'Phones', 'description' => 'Smartphones'],
            ['slug' => 'laptops', 'name' => 'Laptops', 'description' => 'Notebooks and ultrabooks'],
            ['slug' => 'tablets', 'name' => 'Tablets', 'description' => 'Tablets and e-readers'],
            ['slug' => 'audio', 'name' => 'Audio', 'description' => 'Headphones and earbuds'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
