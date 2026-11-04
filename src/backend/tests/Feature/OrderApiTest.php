<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_placing_an_order_snapshots_prices_and_reserves_stock(): void
    {
        $user = User::factory()->create();
        $phone = Product::factory()->create(['price' => 100, 'stock' => 5]);
        $case = Product::factory()->create(['price' => 15.5, 'stock' => 10]);

        $this->postJson('/api/orders', [
            'user_id' => $user->id,
            'items' => [
                ['product_id' => $phone->id, 'quantity' => 2],
                ['product_id' => $case->id, 'quantity' => 1],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total', '215.50')
            ->assertJsonPath('data.items_count', 2);

        $this->assertSame(3, $phone->fresh()->stock);
        $this->assertSame(9, $case->fresh()->stock);
    }

    public function test_it_rejects_orders_that_exceed_stock(): void
    {
        $product = Product::factory()->create(['stock' => 1]);

        $this->postJson('/api/orders', [
            'user_id' => User::factory()->create()->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['items.0.quantity']]]);

        $this->assertSame(1, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cancelling_returns_stock_and_locks_the_order(): void
    {
        $product = Product::factory()->create(['stock' => 4]);
        $id = $this->postJson('/api/orders', [
            'user_id' => User::factory()->create()->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->json('data.id');

        $this->patchJson("/api/orders/{$id}", ['status' => 'cancelled'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(4, $product->fresh()->stock);

        $this->patchJson("/api/orders/{$id}", ['status' => 'paid'])->assertStatus(409)->assertJsonPath('error.code', 'conflict');
    }

    public function test_orders_can_be_filtered_and_sorted_by_total(): void
    {
        $user = User::factory()->create();
        $cheap = Product::factory()->create(['price' => 10, 'stock' => 10]);
        $dear = Product::factory()->create(['price' => 500, 'stock' => 10]);

        foreach ([$cheap, $dear] as $product) {
            $this->postJson('/api/orders', ['user_id' => $user->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]]]);
        }

        $totals = collect($this->getJson('/api/orders?sort=-total&status=pending')->assertOk()->json('data'))->pluck('total')->all();
        $this->assertSame(['500.00', '10.00'], $totals);

        $this->getJson('/api/orders?status=shipped')->assertOk()->assertJsonCount(0, 'data');
    }
}
