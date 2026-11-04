<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ListsResources;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    use ListsResources;

    /**
     * GET /api/categories
     *
     * Search: q (name, slug)
     * Sort:   name, created_at, products_count (default name)
     */
    public function index(Request $request)
    {
        $filters = $request->validate($this->listRules());

        $query = Category::query()
            ->withCount('products')
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($query) => $query
                ->whereLike('name', "%{$q}%")
                ->orWhereLike('slug', "%{$q}%")));

        $this->applySort($query, $request, [
            'name' => 'name',
            'created_at' => 'created_at',
            'products_count' => 'products_count',
        ], 'name');

        return CategoryResource::collection($query->paginate($this->perPage($request))->withQueryString());
    }

    public function show(Category $category)
    {
        return new CategoryResource($category->loadCount('products'));
    }

    public function store(Request $request)
    {
        $category = Category::create($this->validated($request));

        return (new CategoryResource($category->loadCount('products')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, Category $category)
    {
        $category->update($this->validated($request, $category));

        return new CategoryResource($category->loadCount('products'));
    }

    public function destroy(Category $category)
    {
        // products.category_id is nullOnDelete, so its products become uncategorised.
        $category->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        // Derive the slug from the name when it isn't given.
        if (! $request->filled('slug') && $request->filled('name')) {
            $request->merge(['slug' => Str::slug($request->input('name'))]);
        }

        return $request->validate([
            'name' => [$category ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($category)],
            'description' => ['nullable', 'string'],
        ]);
    }
}
