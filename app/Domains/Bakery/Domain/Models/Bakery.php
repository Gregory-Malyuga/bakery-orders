<?php

namespace Domains\Bakery\Domain\Models;

use Domains\Bakery\Domain\Database\Factories\BakeryFactory;
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
 * @method static \Domains\Bakery\Domain\Database\Factories\BakeryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bakery newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bakery newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bakery query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bakery whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bakery whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bakery whereDiscountEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bakery whereDiscountPercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bakery whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bakery whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bakery whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bakery wherePriceSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bakery whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Bakery extends Model
{
    /** @use HasFactory<BakeryFactory> */
    use HasFactory;

    protected $table = 'bakeries';

    /** @var list<string> */
    protected $fillable = ['name', 'address', 'is_active', 'is_cashless', 'discount_enabled', 'discount_percent'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_cashless' => 'boolean', 'discount_enabled' => 'boolean', 'discount_percent' => 'integer'];
    }

    protected static function newFactory(): BakeryFactory
    {
        return BakeryFactory::new();
    }
}
