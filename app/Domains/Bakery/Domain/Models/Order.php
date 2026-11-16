<?php

namespace Domains\Bakery\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** @property Carbon $delivery_date */
class Order extends Model
{
    protected $table = 'bakery_orders';

    protected $fillable = ['bakery_id', 'delivery_date', 'status', 'active_slot', 'request_key', 'store_name', 'store_address', 'total_kopecks', 'cancelled_at'];

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    protected function casts(): array
    {
        return ['delivery_date' => 'date', 'cancelled_at' => 'datetime'];
    }
}
