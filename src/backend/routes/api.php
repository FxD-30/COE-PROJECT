<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::apiResource('categories', CategoryController::class);
Route::apiResource('products', ProductController::class);
// Orders are kept for history: they can be cancelled via PATCH but not deleted.
Route::apiResource('orders', OrderController::class)->except('destroy');
