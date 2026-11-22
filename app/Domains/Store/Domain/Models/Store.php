<?php

namespace Domains\Store\Domain\Models;

use Domains\Store\Domain\Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $address
 * @property bool $is_active
 * @property bool $is_cashless
 * @property int|null $price_source_id
 * @property bool $discount_enabled
 * @property int $discount_percent
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereDiscountEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereDiscountPercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store wherePriceSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    protected $table = 'bakeries';

    /** @var list<string> */
    protected $fillable = ['name', 'address', 'is_active', 'is_cashless', 'discount_enabled', 'discount_percent'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_cashless' => 'boolean', 'discount_enabled' => 'boolean', 'discount_percent' => 'integer'];
    }

    protected static function newFactory(): StoreFactory
    {
        return StoreFactory::new();
    }
}
