<?php

namespace Shared\Bus;

interface QueryBusInterface
{
    public function ask(BaseQuery $query): ?object;
}
