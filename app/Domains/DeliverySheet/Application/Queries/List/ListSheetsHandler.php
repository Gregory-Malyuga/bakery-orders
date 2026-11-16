<?php

namespace Domains\DeliverySheet\Application\Queries\List;

use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Domains\DeliverySheet\Application\Data\DeliverySheetViewData;
use Domains\DeliverySheet\Application\DeliverySheetService;
use Domains\DeliverySheet\Application\Repositories\DeliverySheetRepositoryInterface;
use Shared\Bus\QueryHandlerInterface;

readonly class ListSheetsHandler implements QueryHandlerInterface
{
    public function __construct(
        private DeliverySheetService $service,
        private DeliverySheetRepositoryInterface $repository,
        private OrderingRepositoryInterface $stores,
    ) {}

    public function handle(object $message): DeliverySheetViewData
    {
        assert($message instanceof ListSheetsQuery);

        $copySheet = $message->copyId !== null ? $this->repository->sheet($message->copyId) : null;
        $copySnapshot = $copySheet !== null
            ? json_decode((string) $copySheet->snapshot, true, 512, JSON_THROW_ON_ERROR)
            : null;

        return new DeliverySheetViewData([
            'date' => $message->date ?? $this->service->printDate(),
            'stores' => $this->stores->stores()->where('is_active', true),
            'sheets' => $this->repository->sheets(),
            'prints' => $this->repository->prints(),
            'copyId' => $message->copyId,
            'copySnapshot' => $copySnapshot,
        ]);
    }
}
