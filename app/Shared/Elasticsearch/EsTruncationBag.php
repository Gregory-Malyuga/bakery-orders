<?php

namespace Shared\Elasticsearch;

class EsTruncationBag
{
    private bool $truncated = false;

    public function markTruncated(): void
    {
        $this->truncated = true;
    }

    public function isTruncated(): bool
    {
        return $this->truncated;
    }
}
