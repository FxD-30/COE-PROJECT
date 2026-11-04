<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ListsResources;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use ListsResources;

    /**
     * GET /api/products
     *
     * Filters: category_id, is_active, in_stock, min_price, max_price
     * Search:  q (name, SKU, description)
     * Sort:    name, sku, price, stock, created_at (default -created_at)
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            ...$this->listRules(),
            'category_id' => ['sometimes', 'nullable', 'integer'],
            'is_active' => $this->booleanRule(),
            'in_stock' => $this->booleanRule(),
            'min_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        $query = Product::query()
            ->with('category')
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($query) => $query
                ->whereLike('name', "%{$q}%")
                ->orWhereLike('sku', "%{$q}%")
                ->orWhereLike('description', "%{$q}%")))
            ->when(isset($filters['category_id']), fn ($query) => $query->where('category_id', $filters['category_id']))
            ->when(isset($filters['is_active']), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when(isset($filters['in_stock']), fn ($query) => $request->boolean('in_stock')
                ? $query->where('stock', '>', 0)
                : $query->where('stock', 0))
            ->when(isset($filters['min_price']), fn ($query) => $query->where('price', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn ($query) => $query->where('price', '<=', $filters['max_price']));

        $this->applySort($query, $request, [
            'name' => 'name',
            'sku' => 'sku',
            'price' => 'price',
            'stock' => 'stock',
            'created_at' => 'created_at',
        ], '-created_at');

        return ProductResource::collection($query->paginate($this->perPage($request))->withQueryString());
    }

    public function show(Product $product)
    {
        return new ProductResource($product->load('category'));
    }

    public function store(Request $request)
    {
        $product = Product::create($request->validate($this->rules()));

        return (new ProductResource($product->load('category')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, Product $product)
    {
        $product->update($request->validate($this->rules($product)));

        return new ProductResource($product->load('category'));
    }

    public function destroy(Product $product)
    {
        // order_items.product_id restricts deletes; say so clearly instead of surfacing a DB error.
        abort_if(
            $product->orderItems()->exists(),
            Response::HTTP_CONFLICT,
            'This product appears on existing orders and cannot be deleted. Set is_active to false instead.'
        );

        $product->delete();

        return response()->noContent();
    }

    /**
     * Rules for create, or for a partial update when $product is given.
     */
    private function rules(?Product $product = null): array
    {
        $required = $product ? 'sometimes' : 'required';

        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => [$required, 'string', 'max:255'],
            'sku' => [$required, 'string', 'max:64', Rule::unique('products', 'sku')->ignore($product)],
            'description' => ['nullable', 'string'],
            'price' => [$required, 'numeric', 'min:0', 'max:99999999.99'],
            'stock' => [$required, 'integer', 'min:0'],
            'image' => ['nullable', 'url', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
