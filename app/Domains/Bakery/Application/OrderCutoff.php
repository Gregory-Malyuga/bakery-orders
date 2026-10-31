<?php

namespace Domains\Bakery\Application;

use Illuminate\Support\Facades\DB;

class OrderCutoff
{
    public function time(): string
    {
        $value = DB::table('bakery_settings')->where('key', 'order_cutoff')->value('value');

        return is_string($value) ? $value : '18:30';
    }

    public function isOpen(): bool
    {
        return now('Europe/Moscow')->format('H:i') < $this->time();
    }
}
