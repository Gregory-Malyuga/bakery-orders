<?php

namespace Tests\Unit\Shared\Console;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MakeDomainCommandTest extends StubGenTestCase
{
    protected static string $domainName = 'StubIso';

    private bool $migrationRan = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanupArtifacts();
    }

    protected function tearDown(): void
    {
        if (isset($this->files)) {
            $this->cleanupArtifacts();
            // Surgically remove only StubIso entries instead of restoring a full snapshot.
            // A full restore would overwrite StubGen entries written by a concurrent worker.
            $this->removeFromGlobalFiles();
        }

        parent::tearDown();
    }

    private function cleanupArtifacts(): void
    {
        static::deleteDomainArtifacts($this->files);

        if ($this->migrationRan) {
            $table = Str::snake(Str::plural(static::$domainName));
            Schema::dropIfExists($table);
            DB::table('migrations')->where('migration', 'like', "%create_{$table}_table")->delete();
            $this->migrationRan = false;
        }
    }

    private function removeFromGlobalFiles(): void
    {
        $routesPath = base_path('routes/api.php');
        $content = $this->files->get($routesPath);
        $routePrefix = preg_quote('v1/'.Str::kebab(Str::plural(static::$domainName)), '/');
        $cleaned = preg_replace(
            '/\n\nRoute::prefix\(\''.$routePrefix.'\'\)(?:->middleware\([^)]*\))?->group.*?\}\);/s',
            '',
            $content,
        );
        if ($cleaned !== null && $cleaned !== $content) {
            $this->files->put($routesPath, $cleaned);
        }

        $providersPath = base_path('bootstrap/providers.php');
        $content = $this->files->get($providersPath);
        $cleaned = preg_replace('/^[ \t]+Domains\\\\'.preg_quote(static::$domainName, '/').'\\\\[^:]+::class,\n/m', '', $content);
        if ($cleaned !== null && $cleaned !== $content) {
            $this->files->put($providersPath, $cleaned);
        }
    }

    // ── ISOLATED TESTS ────────────────────────────────────────────────────────
    // Each test generates its own domain variant with its own setUp/tearDown cycle.

    public function test_capitalises_first_letter_of_domain_name(): void
    {
        $this->artisan('make:domain stubIso')->assertSuccessful();

        $this->assertFileExists("{$this->domainBase}/Infrastructure/Repositories/StubIsoRepository.php");
    }

    public function test_creates_cache_warmer_with_flag(): void
    {
        $this->artisan('make:domain StubIso --with-cache-warmer')->assertSuccessful();

        $this->assertFileExists("{$this->domainBase}/Infrastructure/Cache/StubIsoCacheWarmer.php");
    }

    public function test_repository_includes_elasticsearch_with_flag(): void
    {
        $this->artisan('make:domain StubIso --with-elasticsearch')->assertSuccessful();

        $content = $this->files->get("{$this->domainBase}/Infrastructure/Repositories/StubIsoRepository.php");

        $this->assertStringContainsString('ElasticsearchSearchable', $content);
        $this->assertStringContainsString('InteractsWithElasticsearch', $content);
    }

    public function test_skips_existing_files_on_second_run(): void
    {
        $this->artisan('make:domain StubIso')->assertSuccessful();

        $path = "{$this->domainBase}/Infrastructure/Repositories/StubIsoRepository.php";
        $this->files->put($path, '<?php // sentinel');

        $this->artisan('make:domain StubIso')->assertSuccessful();

        $this->assertStringContainsString('sentinel', $this->files->get($path));
    }

    public function test_creates_and_runs_migration(): void
    {
        $this->artisan('make:domain StubIso')->assertSuccessful();

        $table = Str::snake(Str::plural(static::$domainName));
        $migrations = $this->files->glob(database_path("migrations/*_create_{$table}_table.php"));
        $this->assertNotEmpty($migrations, 'Migration file should be created');

        // Scope to this specific file so a concurrent worker's pending migrations are not picked up.
        $relPath = ltrim(str_replace([base_path().'/', base_path().'\\'], '', $migrations[0]), '/\\');
        $this->artisan('migrate', ['--path' => $relPath])->assertSuccessful();
        $this->migrationRan = true;
        $this->assertTrue(Schema::hasTable($table), 'Table should exist after migrate');
    }

    public function test_skips_migration_if_already_exists(): void
    {
        $this->artisan('make:domain StubIso')->assertSuccessful();

        $table = Str::snake(Str::plural(static::$domainName));
        $before = $this->files->glob(database_path("migrations/*_create_{$table}_table.php"));

        $this->artisan('make:domain StubIso')->assertSuccessful();
        $after = $this->files->glob(database_path("migrations/*_create_{$table}_table.php"));

        $this->assertCount(count($before), $after, 'No extra migration should be created on second run');
    }

    public function test_fields_appear_in_model_fillable(): void
    {
        $this->artisan('make:domain StubIso title:string count:integer')->assertSuccessful();

        $content = $this->files->get("{$this->domainBase}/Domain/Models/StubIso.php");
        $this->assertStringContainsString("'title'", $content);
        $this->assertStringContainsString("'count'", $content);
    }

    public function test_fields_appear_in_migration(): void
    {
        $this->artisan('make:domain StubIso title:string count:integer')->assertSuccessful();

        $table = Str::snake(Str::plural(static::$domainName));
        $migrations = $this->files->glob(database_path("migrations/*_create_{$table}_table.php"));
        $content = $this->files->get($migrations[0]);
        $this->assertStringContainsString("'title'", $content);
        $this->assertStringContainsString("'count'", $content);
    }

    public function test_provider_registration_is_idempotent(): void
    {
        $this->artisan('make:domain StubIso')->assertSuccessful();
        $this->artisan('make:domain StubIso')->assertSuccessful();

        $content = $this->files->get(base_path('bootstrap/providers.php'));

        $this->assertSame(1, substr_count($content, 'StubIsoServiceProvider::class'));
    }

    public function test_nested_domain_uses_parent_namespace(): void
    {
        $nestedBase = base_path('app/Domains/StubGroup/StubNested');
        $files = $this->files;

        try {
            $this->artisan('make:domain StubGroup/StubNested')->assertSuccessful();

            $content = $files->get("{$nestedBase}/Infrastructure/Repositories/StubNestedRepository.php");
            $this->assertStringContainsString('namespace Domains\StubGroup\StubNested\Infrastructure\Repositories;', $content);

            $content = $files->get("{$nestedBase}/Domain/Models/StubNested.php");
            $this->assertStringContainsString('namespace Domains\StubGroup\StubNested\Domain\Models;', $content);

            $apiRoutes = $files->get(base_path('routes/api.php'));
            $this->assertStringContainsString('Domains\StubGroup\StubNested\Presentation\Http\Controllers\StubNestedController', $apiRoutes);
            $this->assertStringContainsString("prefix('v1/stub-group/stub-nesteds')", $apiRoutes);
        } finally {
            $files->deleteDirectory(base_path('app/Domains/StubGroup'));
            foreach ($files->glob(database_path('migrations/*_create_stub_nesteds_table.php')) as $f) {
                $files->delete($f);
            }
            Schema::dropIfExists('stub_nesteds');

            $routePrefix = preg_quote('v1/stub-group/stub-nesteds', '/');
            $routesPath = base_path('routes/api.php');
            $content = $files->get($routesPath);
            $cleaned = preg_replace('/\n\nRoute::prefix\(\''.$routePrefix.'\'\)(?:->middleware\([^)]*\))?->group.*?\}\);/s', '', $content);
            if ($cleaned !== null && $cleaned !== $content) {
                $files->put($routesPath, $cleaned);
            }

            $providersPath = base_path('bootstrap/providers.php');
            $content = $files->get($providersPath);
            $cleaned = preg_replace('/^[ \t]+Domains\\\\StubGroup\\\\StubNested\\\\[^:]+::class,\n/m', '', $content);
            if ($cleaned !== null && $cleaned !== $content) {
                $files->put($providersPath, $cleaned);
            }
        }
    }
}
