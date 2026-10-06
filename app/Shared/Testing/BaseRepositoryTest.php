<?php

namespace Shared\Testing;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase;
use Shared\Testing\Traits\HasRepositoryTests;

abstract class BaseRepositoryTest extends TestCase
{
    use HasRepositoryTests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }
}
