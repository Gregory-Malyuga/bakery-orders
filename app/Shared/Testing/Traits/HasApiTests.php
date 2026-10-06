<?php

namespace Shared\Testing\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;

/**
 * @phpstan-require-extends TestCase
 */
trait HasApiTests
{
    use DatabaseTransactions;
    use MakesApiAssertions;

    abstract protected function basePath(): string;

    /** @return array<string, mixed> */
    abstract protected function makeStorePayload(): array;

    /** @return array<string, mixed> */
    abstract protected function makeUpdatePayload(): array;

    abstract protected function existingRecord(): Model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    protected function authenticate(): void {}

    public function test_index_returns_200(): void
    {
        $response = $this->getJson($this->basePath());

        $response->assertStatus(200);
    }

    public function test_show_returns_200(): void
    {
        $model = $this->existingRecord();

        /** @var int|string $key */
        $key = $model->getKey();
        $response = $this->getJson($this->basePath().'/'.(string) $key);

        $response->assertStatus(200);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->getJson("{$this->basePath()}/".PHP_INT_MAX);

        $response->assertStatus(404);
    }

    public function test_store_creates_resource_and_returns_201(): void
    {
        $response = $this->postJson($this->basePath(), $this->makeStorePayload());

        $response->assertStatus(201);
    }

    public function test_update_modifies_resource_and_returns_200(): void
    {
        $model = $this->existingRecord();

        /** @var int|string $key */
        $key = $model->getKey();
        $response = $this->putJson($this->basePath().'/'.(string) $key, $this->makeUpdatePayload());

        $response->assertStatus(200);
    }

    public function test_destroy_removes_resource_and_returns_204(): void
    {
        $model = $this->existingRecord();

        /** @var int|string $key */
        $key = $model->getKey();
        $response = $this->deleteJson($this->basePath().'/'.(string) $key);

        $response->assertStatus(204);
    }

    public function test_index_response_has_data_meta_links(): void
    {
        $response = $this->getJson($this->basePath());

        $response->assertStatus(200);
        /** @var array<string, mixed> $body */
        $body = $response->json();
        $this->assertApiPaginatedShape($body);
    }
}
