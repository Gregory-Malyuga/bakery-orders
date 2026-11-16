<?php

namespace Domains\Bakery\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $table = 'bakery_order_items';

    protected $fillable = ['order_id', 'product_id', 'product_name', 'quantity', 'unit_kopecks', 'total_kopecks'];
}
