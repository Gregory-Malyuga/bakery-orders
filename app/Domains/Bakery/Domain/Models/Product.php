<?php

namespace Domains\Bakery\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'bakery_products';

    protected $fillable = ['name', 'sort_order', 'minimum_quantity', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer', 'minimum_quantity' => 'integer'];
    }
}
