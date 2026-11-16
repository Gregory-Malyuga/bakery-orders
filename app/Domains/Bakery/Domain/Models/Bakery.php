<?php

namespace Domains\Bakery\Domain\Models;

use Domains\Bakery\Domain\Database\Factories\BakeryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bakery extends Model
{
    /** @use HasFactory<BakeryFactory> */
    use HasFactory;

    protected $table = 'bakeries';

    /** @var list<string> */
    protected $fillable = ['name', 'address', 'is_active', 'discount_enabled', 'discount_percent'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'discount_enabled' => 'boolean', 'discount_percent' => 'integer'];
    }

    protected static function newFactory(): BakeryFactory
    {
        return BakeryFactory::new();
    }
}
