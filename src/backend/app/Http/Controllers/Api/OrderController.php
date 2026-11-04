<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ListsResources;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    use ListsResources;

    /**
     * GET /api/orders
     *
     * Filters: status, user_id, from, to (created_at date range)
     * Search:  q (order number)
     * Sort:    created_at, total, status (default -created_at)
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            ...$this->listRules(),
            'status' => ['sometimes', 'nullable', Rule::in(Order::STATUSES)],
            'user_id' => ['sometimes', 'nullable', 'integer'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = Order::query()
            ->withTotal()
            ->withCount('items')
            ->with('user')
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->whereLike('order_number', "%{$q}%"))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to));

        $this->applySort($query, $request, [
            'created_at' => 'created_at',
            'total' => 'total',
            'status' => 'status',
        ], '-created_at');

        return OrderResource::collection($query->paginate($this->perPage($request))->withQueryString());
    }

    public function show(Order $order)
    {
        return new OrderResource($this->loadDetail($order));
    }

    /**
     * Place an order. Prices are copied from the products and stock is reserved
     * in the same transaction, so two orders cannot oversell the same item.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $order = DB::transaction(function () use ($data) {
            $products = Product::query()
                ->whereIn('id', array_column($data['items'], 'product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $errors = [];
            foreach ($data['items'] as $i => $item) {
                $product = $products[$item['product_id']];

                if (! $product->is_active) {
                    $errors["items.{$i}.product_id"] = ["{$product->name} is not available."];
                } elseif ($product->stock < $item['quantity']) {
                    $errors["items.{$i}.quantity"] = ["Only {$product->stock} of {$product->name} left in stock."];
                }
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $order = Order::create([
                'order_number' => 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'user_id' => $data['user_id'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $product = $products[$item['product_id']];
                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                ]);
                $product->decrement('stock', $item['quantity']);
            }

            return $order;
        });

        return (new OrderResource($this->loadDetail($order)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update status and notes. Cancelling returns the items to stock.
     * Completed and cancelled orders are final.
     */
    public function update(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(Order::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        abort_if(
            in_array($order->status, ['completed', 'cancelled'], true),
            Response::HTTP_CONFLICT,
            "Order {$order->order_number} is {$order->status} and can no longer be changed."
        );

        DB::transaction(function () use ($order, $data) {
            if (($data['status'] ?? null) === 'cancelled') {
                foreach ($order->items as $item) {
                    Product::whereKey($item->product_id)->increment('stock', $item->quantity);
                }
            }

            $order->update($data);
        });

        return new OrderResource($this->loadDetail($order));
    }

    private function loadDetail(Order $order): Order
    {
        return Order::query()
            ->withTotal()
            ->withCount('items')
            ->with(['user', 'items.product'])
            ->findOrFail($order->id);
    }
}
