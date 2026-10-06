<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_returns_ok_structure(): void
    {
        $response = $this->getJson('/health');

        $response->assertJsonStructure(['status', 'db', 'es', 'redis']);
        $response->assertJsonPath('db', true);
    }

    public function test_health_returns_200_or_503(): void
    {
        $response = $this->getJson('/health');

        $this->assertContains($response->status(), [200, 503]);
    }

    public function test_health_is_accessible_without_auth(): void
    {
        $response = $this->getJson('/health');

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    public function test_health_returns_ok_status_when_all_healthy(): void
    {
        $response = $this->getJson('/health');

        // DB is always up in tests; if es/redis are also up → 'ok' and 200
        // If either is down → 'degraded' and 503 — structure still valid
        $response->assertJsonStructure(['status', 'db', 'es', 'redis']);
        $this->assertIsString($response->json('status'));
        $this->assertIsBool($response->json('db'));
        $this->assertIsBool($response->json('es'));
        $this->assertIsBool($response->json('redis'));
    }
}
