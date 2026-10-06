<?php

namespace Tests\Unit\Shared\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class StubGenTestCase extends TestCase
{
    protected static string $domainName = 'StubGen';

    protected Filesystem $files;

    protected string $domainBase;

    protected string $unitTestBase;

    protected string $featureTestBase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $name = static::$domainName;
        $this->domainBase = base_path("app/Domains/{$name}");
        $this->unitTestBase = "{$this->domainBase}/Tests/Unit";
        $this->featureTestBase = "{$this->domainBase}/Tests/Feature";
    }

    protected static function deleteDomainArtifacts(Filesystem $files): void
    {
        $name = static::$domainName;
        $files->deleteDirectory(base_path("app/Domains/{$name}"));

        $table = Str::snake(Str::plural($name));
        foreach ($files->glob(database_path("migrations/*_create_{$table}_table.php")) as $f) {
            $files->delete($f);
        }
    }

    protected static function stripTestArtifacts(Filesystem $files): void
    {
        $providersPath = base_path('bootstrap/providers.php');
        $content = $files->get($providersPath);
        $cleaned = preg_replace('/^[ \t]+Domains\\\\Stub[^:]+::class,\n/m', '', $content);
        if ($cleaned !== null && $cleaned !== $content) {
            $files->put($providersPath, $cleaned);
        }

        $routesPath = base_path('routes/api.php');
        $content = $files->get($routesPath);
        $cleaned = preg_replace('/\n\nRoute::prefix\(\'v1\/stub[^\']*\'\)(?:->middleware\([^)]*\))?->group.*?\}\);/s', '', $content);
        if ($cleaned !== null && $cleaned !== $content) {
            $files->put($routesPath, $cleaned);
        }
    }
}
