<?php

namespace Tests\Unit\Shared\Console;

use Illuminate\Filesystem\Filesystem;

class MakeDomainCommandSharedTest extends StubGenTestCase
{
    private static bool $domainReady = false;

    private static string $originalProviders = '';

    private static string $originalApiRoutes = '';

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$domainReady) {
            static::stripTestArtifacts($this->files);
            self::$originalProviders = $this->files->get(base_path('bootstrap/providers.php'));
            self::$originalApiRoutes = $this->files->get(base_path('routes/api.php'));
            static::deleteDomainArtifacts($this->files);
            $this->artisan('make:domain '.static::$domainName)->assertSuccessful();
            self::$domainReady = true;
        }
    }

    protected function tearDown(): void
    {
        // Domain stays alive for the whole class; tearDownAfterClass does the final cleanup.
        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$domainReady) {
            $files = new Filesystem;
            static::deleteDomainArtifacts($files);

            if (self::$originalProviders !== '') {
                $files->put(base_path('bootstrap/providers.php'), self::$originalProviders);
            }
            if (self::$originalApiRoutes !== '') {
                $files->put(base_path('routes/api.php'), self::$originalApiRoutes);
            }

            self::$domainReady = false;
        }

        parent::tearDownAfterClass();
    }

    // ── SHARED TESTS ──────────────────────────────────────────────────────────
    // All tests here read the single pre-generated StubGen domain. No artisan call in the body.

    public function test_creates_standard_domain_structure(): void
    {
        $this->assertFileExists("{$this->domainBase}/Application/Data/CreateStubGenData.php");
        $this->assertFileExists("{$this->domainBase}/Application/Data/UpdateStubGenData.php");
        $this->assertFileExists("{$this->domainBase}/Application/Data/StubGenResource.php");
        $this->assertFileExists("{$this->domainBase}/Application/Data/StubGenFilterData.php");
        $this->assertFileDoesNotExist("{$this->domainBase}/Application/Data/StubGenData.php");
        $this->assertFileExists("{$this->domainBase}/Domain/Exceptions/StubGenNotFoundException.php");
        $this->assertFileExists("{$this->domainBase}/Infrastructure/Repositories/StubGenRepository.php");
        $this->assertFileExists("{$this->domainBase}/Providers/StubGenServiceProvider.php");
        $this->assertFileExists("{$this->unitTestBase}/StubGenRepositoryTest.php");
    }

    public function test_no_cache_warmer_without_flag(): void
    {
        $this->assertFileDoesNotExist("{$this->domainBase}/Infrastructure/Cache/StubGenCacheWarmer.php");
    }

    public function test_repository_without_elasticsearch_by_default(): void
    {
        $content = $this->files->get("{$this->domainBase}/Infrastructure/Repositories/StubGenRepository.php");

        $this->assertStringNotContainsString('ElasticsearchSearchable', $content);
    }

    public function test_generated_repository_has_correct_namespace(): void
    {
        $content = $this->files->get("{$this->domainBase}/Infrastructure/Repositories/StubGenRepository.php");

        $this->assertStringContainsString('namespace Domains\StubGen\Infrastructure\Repositories;', $content);
        $this->assertStringContainsString('class StubGenRepository extends BaseRepository', $content);
    }

    public function test_creates_commands_queries_events_directories(): void
    {
        $this->assertDirectoryExists("{$this->domainBase}/Application/Commands");
        $this->assertDirectoryExists("{$this->domainBase}/Application/Queries");
        $this->assertDirectoryExists("{$this->domainBase}/Domain/Events");
    }

    public function test_creates_cqrs_files(): void
    {
        $this->assertFileExists("{$this->domainBase}/Application/Commands/Create/CreateStubGenCommand.php");
        $this->assertFileExists("{$this->domainBase}/Application/Commands/Create/CreateStubGenHandler.php");
        $this->assertFileExists("{$this->domainBase}/Application/Commands/Update/UpdateStubGenCommand.php");
        $this->assertFileExists("{$this->domainBase}/Application/Commands/Update/UpdateStubGenHandler.php");
        $this->assertFileExists("{$this->domainBase}/Application/Commands/Delete/DeleteStubGenCommand.php");
        $this->assertFileExists("{$this->domainBase}/Application/Commands/Delete/DeleteStubGenHandler.php");
        $this->assertFileExists("{$this->domainBase}/Application/Queries/ListAll/ListStubGensQuery.php");
        $this->assertFileExists("{$this->domainBase}/Application/Queries/ListAll/ListStubGensHandler.php");
        $this->assertFileExists("{$this->domainBase}/Application/Queries/FindById/FindStubGenByIdQuery.php");
        $this->assertFileExists("{$this->domainBase}/Application/Queries/FindById/FindStubGenByIdHandler.php");
    }

    public function test_creates_model_in_domain(): void
    {
        $this->assertFileExists("{$this->domainBase}/Domain/Models/StubGen.php");
        $content = $this->files->get("{$this->domainBase}/Domain/Models/StubGen.php");
        $this->assertStringContainsString('namespace Domains\StubGen\Domain\Models;', $content);
    }

    public function test_creates_factory_in_domain(): void
    {
        $this->assertFileExists("{$this->domainBase}/Domain/Database/Factories/StubGenFactory.php");
    }

    public function test_creates_controller(): void
    {
        $this->assertFileExists("{$this->domainBase}/Presentation/Http/Controllers/StubGenController.php");
        $content = $this->files->get("{$this->domainBase}/Presentation/Http/Controllers/StubGenController.php");
        $this->assertStringContainsString('CommandBusInterface', $content);
        $this->assertStringContainsString('QueryBusInterface', $content);
        $this->assertStringContainsString('ListStubGensRequest', $content);
        $this->assertStringContainsString('StoreStubGenRequest', $content);
        $this->assertStringContainsString('UpdateStubGenRequest', $content);
    }

    public function test_creates_form_requests(): void
    {
        $this->assertFileExists("{$this->domainBase}/Presentation/Http/Requests/ListStubGensRequest.php");
        $this->assertFileExists("{$this->domainBase}/Presentation/Http/Requests/StoreStubGenRequest.php");
        $this->assertFileExists("{$this->domainBase}/Presentation/Http/Requests/UpdateStubGenRequest.php");

        $listContent = $this->files->get("{$this->domainBase}/Presentation/Http/Requests/ListStubGensRequest.php");
        $this->assertStringContainsString('extends ListRequest', $listContent);
        $this->assertStringContainsString('ListStubGensQuery::SORTABLE', $listContent);

        $storeContent = $this->files->get("{$this->domainBase}/Presentation/Http/Requests/StoreStubGenRequest.php");
        $this->assertStringContainsString('CreateStubGenData::rules()', $storeContent);

        $updateContent = $this->files->get("{$this->domainBase}/Presentation/Http/Requests/UpdateStubGenRequest.php");
        $this->assertStringContainsString('UpdateStubGenData::rules()', $updateContent);
    }

    public function test_list_handler_uses_typed_paginator(): void
    {
        $content = $this->files->get("{$this->domainBase}/Application/Queries/ListAll/ListStubGensHandler.php");
        $this->assertStringContainsString('LengthAwarePaginator<int, StubGen>', $content);
        $this->assertStringNotContainsString('mixed', $content);
    }

    public function test_list_query_extends_list_entity_query(): void
    {
        $content = $this->files->get("{$this->domainBase}/Application/Queries/ListAll/ListStubGensQuery.php");
        $this->assertStringContainsString('extends ListEntityQuery', $content);
        $this->assertStringContainsString('SORTABLE', $content);
    }

    public function test_appends_routes_to_global_api_file(): void
    {
        $content = $this->files->get(base_path('routes/api.php'));
        $this->assertStringContainsString('StubGenController', $content);
    }

    public function test_creates_api_test(): void
    {
        $this->assertFileExists("{$this->featureTestBase}/StubGenApiTest.php");
        $content = $this->files->get("{$this->featureTestBase}/StubGenApiTest.php");
        $this->assertStringContainsString('BaseApiTest', $content);
    }

    public function test_creates_events(): void
    {
        $this->assertFileExists("{$this->domainBase}/Domain/Events/StubGenCreated.php");
        $this->assertFileExists("{$this->domainBase}/Domain/Events/StubGenUpdated.php");
        $this->assertFileExists("{$this->domainBase}/Domain/Events/StubGenDeleted.php");
    }

    public function test_registers_provider_in_bootstrap(): void
    {
        $content = $this->files->get(base_path('bootstrap/providers.php'));
        $this->assertStringContainsString('StubGenServiceProvider', $content);
    }

    public function test_service_provider_has_handler_bindings(): void
    {
        $content = $this->files->get("{$this->domainBase}/Providers/StubGenServiceProvider.php");
        $this->assertStringContainsString('CreateStubGenHandler::class', $content);
        $this->assertStringContainsString('UpdateStubGenHandler::class', $content);
        $this->assertStringContainsString('DeleteStubGenHandler::class', $content);
        $this->assertStringContainsString('ListStubGensHandler::class', $content);
        $this->assertStringContainsString('FindStubGenByIdHandler::class', $content);
    }
}
