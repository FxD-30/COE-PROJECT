<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_category_with_a_generated_slug(): void
    {
        $this->postJson('/api/categories', ['name' => 'Smart Watches'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'smart-watches')
            ->assertJsonPath('data.products_count', 0);
    }

    public function test_list_includes_product_counts_and_sorts_by_them(): void
    {
        $busy = Category::factory()->create();
        Category::factory()->create();
        Product::factory()->count(2)->create(['category_id' => $busy->id]);

        $this->getJson('/api/categories?sort=-products_count')
            ->assertOk()
            ->assertJsonPath('data.0.id', $busy->id)
            ->assertJsonPath('data.0.products_count', 2);
    }

    public function test_deleting_a_category_keeps_its_products(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->deleteJson("/api/categories/{$category->id}")->assertNoContent();

        $this->assertNull($product->fresh()->category_id);
    }

    public function test_unknown_api_routes_use_the_error_shape(): void
    {
        $this->getJson('/api/nope')->assertNotFound()->assertJsonPath('error.code', 'not_found');
        $this->putJson('/api/categories')->assertStatus(405)->assertJsonPath('error.code', 'method_not_allowed');
    }
}
