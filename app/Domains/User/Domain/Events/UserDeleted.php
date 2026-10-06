<?php

namespace Domains\User\Domain\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

class UserDeleted
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public readonly int $id) {}
}
