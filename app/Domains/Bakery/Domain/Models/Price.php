<?php

namespace Domains\Bakery\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bakery_id
 * @property int $product_id
 * @property int $kopecks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Price newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Price newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Price query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Price whereBakeryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Price whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Price whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Price whereKopecks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Price whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Price whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Price extends Model
{
    protected $table = 'bakery_prices';

    protected $fillable = ['bakery_id', 'product_id', 'kopecks'];
}
