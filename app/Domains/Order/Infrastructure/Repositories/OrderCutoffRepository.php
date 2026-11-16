<?php

namespace Domains\Order\Infrastructure\Repositories;

use Domains\Order\Application\Repositories\OrderCutoffRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class OrderCutoffRepository implements OrderCutoffRepositoryInterface
{
    public function time(): string
    {
        $value = DB::table('bakery_settings')->where('key', 'order_cutoff')->value('value');

        return is_string($value) ? $value : '18:30';
    }

    public function save(string $value): void
    {
        DB::table('bakery_settings')->updateOrInsert(['key' => 'order_cutoff'], ['value' => $value]);
    }
}
