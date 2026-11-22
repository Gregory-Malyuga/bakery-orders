<?php

namespace Domains\Order\Tests\Feature\Support;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;

abstract class MultiStoreOrderingCase extends TestCase
{
    use DatabaseTransactions;
}
