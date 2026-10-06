<?php

namespace Tests\Unit\Shared\Elasticsearch;

use Shared\Elasticsearch\EsTruncationBag;
use Tests\TestCase;

class EsTruncationBagTest extends TestCase
{
    public function test_is_not_truncated_by_default(): void
    {
        $bag = new EsTruncationBag;

        $this->assertFalse($bag->isTruncated());
    }

    public function test_is_truncated_after_mark(): void
    {
        $bag = new EsTruncationBag;
        $bag->markTruncated();

        $this->assertTrue($bag->isTruncated());
    }
}
