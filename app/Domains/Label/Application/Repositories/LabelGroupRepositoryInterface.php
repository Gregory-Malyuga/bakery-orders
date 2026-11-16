<?php

namespace Domains\Label\Application\Repositories;

interface LabelGroupRepositoryInterface
{
    /** @return array<int,list<string>> */
    public function excludedStores(): array;
}
