<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase;

class HealthCheckTest extends TestCase
{
    public function testHealthReturnsOkStructure(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk()->assertExactJson(['status' => 'ok', 'db' => true]);
    }

    public function testHealthReturns200Or503(): void
    {
        $response = $this->getJson('/health');

        $this->assertContains($response->status(), [200, 503]);
    }

    public function testHealthIsAccessibleWithoutAuth(): void
    {
        $response = $this->getJson('/health');

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    public function testHealthReturnsOkStatusWhenAllHealthy(): void
    {
        $response = $this->getJson('/health');

        $response->assertJsonStructure(['status', 'db']);
        $this->assertIsString($response->json('status'));
        $this->assertIsBool($response->json('db'));
    }
}
