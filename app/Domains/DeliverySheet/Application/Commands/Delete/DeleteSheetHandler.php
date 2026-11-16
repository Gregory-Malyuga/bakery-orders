<?php

namespace Domains\DeliverySheet\Application\Commands\Delete;

use Domains\DeliverySheet\Application\Repositories\DeliverySheetRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class DeleteSheetHandler implements CommandHandlerInterface
{
    public function __construct(private DeliverySheetRepositoryInterface $repository) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof DeleteSheetCommand);

        abort_if($this->repository->deleteSheet($message->sheetId) === 0, 404);

        return null;
    }
}
