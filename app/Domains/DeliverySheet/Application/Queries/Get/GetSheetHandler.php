<?php

namespace Domains\DeliverySheet\Application\Queries\Get;

use Domains\DeliverySheet\Application\Data\DeliverySheetPrintView;
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

        /** @var array{date:string,route:string,driver:string,closed:bool,rows:list<array{name:string,address:string,has_order:bool,order:int,returns:int,due:int,cashless?:bool}>,order:int,returns:int,due:int,cash_due?:int,net_cash?:int,salary_kopecks?:int|null,bonus_kopecks?:int|null} $sheet */
        $sheet = $this->service->sheetForPrint($snapshot);

        return new DeliverySheetViewData([
            'sheet' => DeliverySheetPrintView::fromSnapshot($sheet),
            'sheetId' => $message->sheetId,
        ]);
    }
}
