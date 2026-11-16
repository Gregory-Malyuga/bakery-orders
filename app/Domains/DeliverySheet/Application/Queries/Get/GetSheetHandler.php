<?php

namespace Domains\DeliverySheet\Application\Queries\Get;

use Domains\DeliverySheet\Application\Data\DeliverySheetViewData;
use Domains\DeliverySheet\Application\DeliverySheetService;
use Domains\DeliverySheet\Application\Repositories\DeliverySheetRepositoryInterface;
use Shared\Bus\QueryHandlerInterface;

readonly class GetSheetHandler implements QueryHandlerInterface
{
    public function __construct(
        private DeliverySheetRepositoryInterface $repository,
        private DeliverySheetService $service,
    ) {}

    public function handle(object $message): DeliverySheetViewData
    {
        assert($message instanceof GetSheetQuery);

        $row = $this->repository->sheet($message->sheetId);
        abort_if($row === null, 404);

        /** @var array{date:string,route:string,driver:string,salary_kopecks?:int|null,bonus_kopecks?:int|null,rows:list<array{store_id:int,due:int,cashless?:bool}>} $snapshot */
        $snapshot = json_decode((string) $row->snapshot, true, 512, JSON_THROW_ON_ERROR);

        return new DeliverySheetViewData([
            'sheet' => $this->service->sheetForPrint($snapshot),
            'sheetId' => $message->sheetId,
        ]);
    }
}
