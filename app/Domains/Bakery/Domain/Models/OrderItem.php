<?php

namespace Domains\Bakery\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property string $product_name
 * @property int $quantity
 * @property int $unit_kopecks
 * @property int $total_kopecks
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereProductName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereTotalKopecks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUnitKopecks($value)
 *
 * @mixin \Eloquent
 */
class OrderItem extends Model
{
    public $timestamps = false;

    protected $table = 'bakery_order_items';

    protected $fillable = ['order_id', 'product_id', 'product_name', 'quantity', 'unit_kopecks', 'total_kopecks'];
}
