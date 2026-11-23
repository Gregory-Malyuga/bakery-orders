<?php

namespace Domains\Price\Application\Support;

final class Kopecks
{
    public static function fromRubles(string $value): int
    {
        [$rubles, $kopecks] = array_pad(explode('.', str_replace(',', '.', $value), 2), 2, '0');

        return (int) $rubles * 100 + (int) str_pad($kopecks, 2, '0');
    }
}
