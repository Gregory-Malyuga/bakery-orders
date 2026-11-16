<?php

namespace Domains\Bakery\Domain\Exceptions;

use RuntimeException;

final class BakeryNotFoundException extends RuntimeException
{
    public static function forId(int|string $id): self
    {
        return new self("Bakery not found: {$id}");
    }
}
