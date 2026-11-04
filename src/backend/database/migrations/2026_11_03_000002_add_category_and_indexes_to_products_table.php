<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Deleting a category leaves its products uncategorised rather than deleting them.
            $table->foreignId('category_id')->nullable()->after('id')->constrained()->nullOnDelete();

            // Postgres does not index foreign keys automatically; these back the list filters and sorts.
            $table->index('category_id');
            $table->index('name');
            $table->index('price');
            $table->index(['is_active', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_stock_non_negative CHECK (stock >= 0)');
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_non_negative CHECK (price >= 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_stock_non_negative');
            DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_price_non_negative');
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'created_at']);
            $table->dropIndex(['price']);
            $table->dropIndex(['name']);
            $table->dropIndex(['category_id']);
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
