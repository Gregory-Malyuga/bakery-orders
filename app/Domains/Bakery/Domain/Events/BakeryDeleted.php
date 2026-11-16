<?php

namespace Domains\Bakery\Domain\Events;

use Domains\Bakery\Domain\Models\Bakery;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BakeryDeleted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly Bakery $record) {}
}
