<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_reads_updates_and_deletes_a_product(): void
    {
        $category = Category::factory()->create();

        $id = $this->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'Test Phone',
            'sku' => 'TP-1',
            'price' => 999.5,
            'stock' => 7,
        ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'TP-1')
            ->assertJsonPath('data.price', '999.50')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.category.id', $category->id)
            ->json('data.id');

        $this->getJson("/api/products/{$id}")->assertOk()->assertJsonPath('data.name', 'Test Phone');

        $this->patchJson("/api/products/{$id}", ['stock' => 3])
            ->assertOk()
            ->assertJsonPath('data.stock', 3)
            ->assertJsonPath('data.name', 'Test Phone');

        $this->deleteJson("/api/products/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('products', ['id' => $id]);
    }

    public function test_list_is_paginated(): void
    {
        Product::factory()->count(15)->create();

        $this->getJson('/api/products?per_page=5&page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_list_supports_search_filters_and_sorting(): void
    {
        $phones = Category::factory()->create();
        Product::factory()->create(['name' => 'Galaxy Phone', 'sku' => 'GAL-1', 'price' => 300, 'stock' => 5, 'category_id' => $phones->id]);
        Product::factory()->create(['name' => 'Pixel Phone', 'sku' => 'PIX-1', 'price' => 200, 'stock' => 0, 'category_id' => $phones->id]);
        Product::factory()->create(['name' => 'Laptop', 'sku' => 'LAP-1', 'price' => 900, 'stock' => 2, 'is_active' => false]);

        $skus = fn (string $query) => collect($this->getJson("/api/products?{$query}")->assertOk()->json('data'))->pluck('sku')->all();

        $this->assertSame(['PIX-1', 'GAL-1'], $skus('q=phone&sort=price'));
        $this->assertSame(['GAL-1', 'PIX-1'], $skus("category_id={$phones->id}&sort=-price"));
        $this->assertSame(['GAL-1', 'LAP-1'], $skus('in_stock=true&sort=sku'));
        $this->assertSame(['LAP-1'], $skus('is_active=false'));
        $this->assertSame(['GAL-1'], $skus('min_price=250&max_price=500'));
    }

    public function test_invalid_sort_field_returns_validation_error(): void
    {
        $this->getJson('/api/products?sort=password')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['status', 'code', 'message', 'details' => ['sort']]]);
    }

    public function test_validation_errors_use_the_error_shape(): void
    {
        Product::factory()->create(['sku' => 'DUP-1']);

        $this->postJson('/api/products', ['sku' => 'DUP-1', 'price' => -1])
            ->assertStatus(422)
            ->assertExactJsonStructure(['error' => ['status', 'code', 'message', 'details' => ['name', 'sku', 'price', 'stock']]])
            ->assertJsonPath('error.status', 422);
    }

    public function test_missing_product_returns_not_found_shape(): void
    {
        $this->getJson('/api/products/999')
            ->assertNotFound()
            ->assertExactJson(['error' => ['status' => 404, 'code' => 'not_found', 'message' => 'Product 999 not found.']]);
    }

    public function test_product_on_an_order_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();
        $order = Order::create(['order_number' => 'ORD-1', 'user_id' => User::factory()->create()->id]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1]);

        $this->deleteJson("/api/products/{$product->id}")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');
    }
}
