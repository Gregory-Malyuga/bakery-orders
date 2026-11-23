<?php

namespace Domains\DeliverySheet\Application\Queries\Edit;

use Domains\DeliverySheet\Application\Data\DeliverySheetViewData;
use Domains\DeliverySheet\Application\Repositories\DeliverySheetRepositoryInterface;
use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Shared\Bus\QueryHandlerInterface;

readonly class EditSheetHandler implements QueryHandlerInterface
{
    public function __construct(
        private DeliverySheetRepositoryInterface $repository,
        private OrderRepositoryInterface $stores,
    ) {}

    public function handle(object $message): DeliverySheetViewData
    {
        assert($message instanceof EditSheetQuery);

        $row = $this->repository->sheet($message->sheetId);
        abort_if($row === null, 404);

        return new DeliverySheetViewData([
            'sheetId' => $message->sheetId,
            'sheet' => json_decode((string) $row->snapshot, true, 512, JSON_THROW_ON_ERROR),
            'stores' => $this->stores->stores(),
        ]);
    }
}
