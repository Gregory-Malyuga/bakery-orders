<?php

namespace Shared\Console\DomainGenerator\Generators\Domain;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class EventGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        foreach (['Created', 'Updated', 'Deleted'] as $event) {
            $this->writeFile(
                $files,
                "{$ctx->basePath}/Domain/Events/{$ctx->name}{$event}.php",
                $this->buildContent($ctx, $event),
            );
        }
    }

    private function buildContent(DomainContext $ctx, string $event): string
    {
        return <<<PHP
        <?php

        namespace {$ctx->ns}\\Domain\\Events;

        use {$ctx->ns}\\Domain\\Models\\{$ctx->name};
        use Illuminate\\Broadcasting\\InteractsWithSockets;
        use Illuminate\\Foundation\\Events\\Dispatchable;
        use Illuminate\\Queue\\SerializesModels;

        class {$ctx->name}{$event}
        {
            use Dispatchable;
            use InteractsWithSockets;
            use SerializesModels;

            public function __construct(public readonly {$ctx->name} \$record) {}
        }
        PHP;
    }
}
