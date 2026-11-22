<?php

namespace Domains\DeliverySheet\Application\Queries\Printed;

use Domains\DeliverySheet\Application\Data\DeliverySheetPrintView;
use Domains\DeliverySheet\Application\Data\DeliverySheetViewData;
use Domains\DeliverySheet\Application\Repositories\DeliverySheetRepositoryInterface;
use Illuminate\Support\Carbon;
use Shared\Bus\QueryHandlerInterface;

readonly class PrintedSheetHandler implements QueryHandlerInterface
{
    public function __construct(private DeliverySheetRepositoryInterface $repository) {}

    public function handle(object $message): DeliverySheetViewData
    {
        assert($message instanceof PrintedSheetQuery);

        $row = $this->repository->printRecord($message->printId);
        abort_if($row === null, 404);

        /** @var array{date:string,route:string,driver:string,closed:bool,rows:list<array{name:string,address:string,has_order:bool,order:int,returns:int,due:int,cashless?:bool}>,order:int,returns:int,due:int,cash_due?:int,net_cash?:int,salary_kopecks?:int|null,bonus_kopecks?:int|null} $snapshot */
        $snapshot = json_decode((string) $row->snapshot, true, 512, JSON_THROW_ON_ERROR);

        return new DeliverySheetViewData([
            'sheet' => DeliverySheetPrintView::fromSnapshot($snapshot),
            'archiveRecord' => true,
            'printedAtLabel' => Carbon::parse($row->printed_at)->timezone('Europe/Moscow')->format('d.m.Y H:i'),
        ]);
    }
}
