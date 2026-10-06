<?php

namespace Shared\Cache;

interface CacheWarmerInterface
{
    public function warm(): void;

    public function priority(): int;
}
