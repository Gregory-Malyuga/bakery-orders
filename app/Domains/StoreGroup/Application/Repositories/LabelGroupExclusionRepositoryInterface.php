<?php

namespace Domains\StoreGroup\Application\Repositories;

interface LabelGroupExclusionRepositoryInterface
{
    /** @return array<int,list<string>> */
    public function excludedStores(): array;
}
