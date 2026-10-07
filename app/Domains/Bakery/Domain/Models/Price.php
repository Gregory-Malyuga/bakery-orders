<?php

namespace Domains\Bakery\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    protected $table = 'bakery_prices';

    protected $fillable = ['bakery_id', 'product_id', 'kopecks'];
}
