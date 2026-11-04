<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $orders = [
            ['order_number' => 'ORD-DEMO-0001', 'status' => 'completed', 'items' => ['IP16-PRO' => 1, 'APP-2' => 2]],
            ['order_number' => 'ORD-DEMO-0002', 'status' => 'paid', 'items' => ['MBA-M4' => 1]],
            ['order_number' => 'ORD-DEMO-0003', 'status' => 'pending', 'items' => ['SGS25' => 1, 'IPAD-AIR-M3' => 1]],
        ];

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $products = Product::pluck('id', 'sku');

        foreach ($orders as $data) {
            if (Order::where('order_number', $data['order_number'])->exists()) {
                continue;
            }

            DB::transaction(function () use ($data, $user, $products) {
                $order = Order::create([
                    'order_number' => $data['order_number'],
                    'user_id' => $user->id,
                    'status' => $data['status'],
                ]);

                foreach ($data['items'] as $sku => $quantity) {
                    $product = Product::findOrFail($products[$sku]);
                    $order->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $product->price,
                    ]);
                    $product->decrement('stock', $quantity);
                }
            });
        }
    }
}
