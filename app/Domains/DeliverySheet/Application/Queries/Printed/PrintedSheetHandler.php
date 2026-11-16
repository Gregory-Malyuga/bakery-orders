<?php

namespace Domains\DeliverySheet\Application\Queries\Printed;

use Domains\DeliverySheet\Application\Data\DeliverySheetViewData;
use Domains\DeliverySheet\Application\Repositories\DeliverySheetRepositoryInterface;
use Shared\Bus\QueryHandlerInterface;

readonly class PrintedSheetHandler implements QueryHandlerInterface
{
    public function __construct(private DeliverySheetRepositoryInterface $repository) {}

    public function handle(object $message): DeliverySheetViewData
    {
        assert($message instanceof PrintedSheetQuery);

        $row = $this->repository->printRecord($message->printId);
        abort_if($row === null, 404);

        return new DeliverySheetViewData([
            'sheet' => json_decode((string) $row->snapshot, true, 512, JSON_THROW_ON_ERROR),
            'archiveRecord' => true,
            'printedAt' => $row->printed_at,
        ]);
    }
}
