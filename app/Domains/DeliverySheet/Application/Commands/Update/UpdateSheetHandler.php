<?php

namespace Domains\DeliverySheet\Application\Commands\Update;

use Domains\DeliverySheet\Application\DeliverySheetService;
use Domains\DeliverySheet\Application\Repositories\DeliverySheetRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class UpdateSheetHandler implements CommandHandlerInterface
{
    public function __construct(
        private DeliverySheetService $service,
        private DeliverySheetRepositoryInterface $repository,
    ) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof UpdateSheetCommand);

        abort_if($this->repository->sheet($message->sheetId) === null, 404);
        $this->service->assertAfterCutoff($message->data['date']);
        $snapshot = $this->service->build($message->data);
        $this->repository->updateSheet($message->sheetId, $message->data['date'], $snapshot);

        return null;
    }
}
