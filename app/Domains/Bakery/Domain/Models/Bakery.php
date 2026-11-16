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
    protected $fillable = ['name', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function newFactory(): BakeryFactory
    {
        return BakeryFactory::new();
    }
}
