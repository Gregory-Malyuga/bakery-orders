<?php

namespace Domains\Label\Infrastructure\Repositories;

use Domains\Label\Application\Repositories\LabelGroupRepositoryInterface;
use Domains\StoreGroup\Application\Repositories\LabelGroupExclusionRepositoryInterface;

/** Compatibility adapter. StoreGroup owns the exclusion tables. */
final class LabelGroupRepository implements LabelGroupRepositoryInterface
{
    public function __construct(private readonly LabelGroupExclusionRepositoryInterface $groups) {}

    /** @return array<int,list<string>> */
    public function excludedStores(): array
    {
        return $this->groups->excludedStores();
    }
}
