<?php

namespace Domains\User\Application\Data;

use Shared\Http\Data\BaseData;

class LogoutData extends BaseData
{
    public function __construct(public readonly string $message) {}
}
