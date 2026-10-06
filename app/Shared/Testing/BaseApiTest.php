<?php

namespace Shared\Testing;

use Illuminate\Foundation\Testing\TestCase;
use Shared\Testing\Traits\HasApiTests;

abstract class BaseApiTest extends TestCase
{
    use HasApiTests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        $this->authenticate();
    }

    protected function authenticate(): void {}
}
