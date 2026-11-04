<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = ['pending', 'paid', 'shipped', 'completed', 'cancelled'];

    protected $fillable = [
        'order_number',
        'user_id',
        'status',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Add a computed `total` column (sum of quantity x unit_price) so it can be
     * selected and sorted on without storing a derived value.
     */
    public function scopeWithTotal(Builder $query): Builder
    {
        return $query
            ->select('orders.*')
            ->selectSub(
                OrderItem::query()
                    ->selectRaw('COALESCE(SUM(quantity * unit_price), 0)')
                    ->whereColumn('order_items.order_id', 'orders.id'),
                'total'
            );
    }
}
