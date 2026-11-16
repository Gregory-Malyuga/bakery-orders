<?php

namespace Domains\Bakery\Application;

class PackagingPlanner
{
    /** @return array{one:int,two:int,three:int,five:int,loose:int} */
    public function plan(int $quantity, string $mode): array
    {
        $packs = ['one' => 0, 'two' => 0, 'three' => 0, 'five' => 0, 'loose' => 0];
        if ($mode === 'single') {
            $packs['one'] = $quantity;

            return $packs;
        }
        if ($mode === 'pair') {
            $packs['two'] = intdiv($quantity, 2);
            $packs['loose'] = $quantity % 2;

            return $packs;
        }

        $bestRemainder = $quantity + 1;
        for ($three = 0; $three <= intdiv($quantity, 3); $three++) {
            $remaining = $quantity - $three * 3;
            $remainder = $remaining % 5;
            if ($remainder < $bestRemainder) {
                $bestRemainder = $remainder;
                $packs['three'] = $three;
                $packs['five'] = intdiv($remaining, 5);
                $packs['loose'] = $remainder;
            }
        }

        return $packs;
    }
}
