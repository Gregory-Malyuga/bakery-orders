<?php

namespace Domains\Report\Application;

class WeightFormatter
{
    public static function kilograms(float|int $grams): float
    {
        return round($grams / 1000, 6);
    }

    public static function label(float|int $grams): string
    {
        $formatted = number_format(self::kilograms($grams), 6, ',', ' ');
        [$whole, $fraction] = explode(',', $formatted);

        return $whole.','.str_pad(rtrim($fraction, '0'), 3, '0');
    }
}
