<?php

namespace Domains\Bakery\Domain\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bakery_id
 * @property Carbon $delivery_date
 * @property string $status
 * @property int|null $active_slot
 * @property string $request_key
 * @property string $store_name
 * @property string $store_address
 * @property int $total_kopecks
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $comment
 * @property string|null $admin_note
 * @property-read Collection<int, OrderItem> $items
 * @property-read int|null $items_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereActiveSlot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereAdminNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereBakeryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCancelledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereDeliveryDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereRequestKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStoreAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStoreName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTotalKopecks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Order extends Model
{
    protected $table = 'bakery_orders';

    protected $fillable = ['bakery_id', 'delivery_date', 'status', 'active_slot', 'request_key', 'store_name', 'store_address', 'total_kopecks', 'comment', 'admin_note', 'cancelled_at'];

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
