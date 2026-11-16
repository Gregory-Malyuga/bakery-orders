<?php

namespace Domains\DeliverySchedule\Application\Commands\OpenDate;

use Domains\DeliverySchedule\Application\Repositories\ClosedDateRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class OpenDateHandler implements CommandHandlerInterface
{
    public function __construct(private ClosedDateRepositoryInterface $repository) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof OpenDateCommand);

        $this->repository->open($message->date);

        return null;
    }
}
