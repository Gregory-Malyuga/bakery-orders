<?php

namespace Domains\Bakery\Application;

use Domains\Label\Application\Repositories\LabelGroupRepositoryInterface;

/** @deprecated Use Domains\Label\Application\LabelPlanService. */
class LabelPlanService extends \Domains\Label\Application\LabelPlanService
{
    public function __construct(
        PackagingPlanner $packaging,
        ?LabelGroupRepositoryInterface $groups = null,
    ) {
        parent::__construct($packaging, $groups ?? app(LabelGroupRepositoryInterface::class));
    }
}
