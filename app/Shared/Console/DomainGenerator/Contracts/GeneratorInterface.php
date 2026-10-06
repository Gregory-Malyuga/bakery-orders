<?php

namespace Shared\Console\DomainGenerator\Contracts;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;

interface GeneratorInterface
{
    public function generate(DomainContext $ctx, Filesystem $files): void;
}
