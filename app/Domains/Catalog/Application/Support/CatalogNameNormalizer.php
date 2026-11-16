<?php

namespace Domains\Catalog\Application\Support;

final class CatalogNameNormalizer
{
    public function key(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)) ?? $name);
    }
}
