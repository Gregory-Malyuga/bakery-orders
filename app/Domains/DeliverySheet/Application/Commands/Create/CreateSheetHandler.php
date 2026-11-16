<?php

namespace Domains\DeliverySheet\Application\Commands\Create;

use Domains\DeliverySheet\Application\DeliverySheetService;
use Domains\DeliverySheet\Application\Repositories\DeliverySheetRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class CreateSheetHandler implements CommandHandlerInterface
{
    public function __construct(
        private DeliverySheetService $service,
        private DeliverySheetRepositoryInterface $repository,
    ) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof CreateSheetCommand);

        $this->service->assertAfterCutoff($message->data['date']);
        $snapshot = $this->service->build($message->data);

        return $this->repository->createSheet($message->data['date'], $snapshot);
    }
}
