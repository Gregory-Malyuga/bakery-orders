<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class DeptracArchitectureTest extends TestCase
{
    public function test_deptrac_architecture_rules_pass(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $process = new Process([
            $projectRoot.'/vendor/bin/deptrac', 'analyse',
            '--config-file='.$projectRoot.'/deptrac.yaml',
            '--fail-on-uncovered', '--no-progress', '--no-interaction',
        ], $projectRoot);
        $process->setTimeout(60);
        $process->run();
        self::assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
    }
}
