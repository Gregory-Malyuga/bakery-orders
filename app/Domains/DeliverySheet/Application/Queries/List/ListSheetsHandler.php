<?php

namespace Domains\DeliverySheet\Application\Queries\List;

use Domains\DeliverySheet\Application\Data\DeliverySheetViewData;
use Domains\DeliverySheet\Application\Data\PrintedSheetHistoryItem;
use Domains\DeliverySheet\Application\Data\SavedSheetOption;
use Domains\DeliverySheet\Application\DeliverySheetService;
use Domains\DeliverySheet\Application\Repositories\DeliverySheetRepositoryInterface;
use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Illuminate\Support\Carbon;
use Shared\Bus\QueryHandlerInterface;

readonly class ListSheetsHandler implements QueryHandlerInterface
{
    public function __construct(
        private DeliverySheetService $service,
        private DeliverySheetRepositoryInterface $repository,
        private OrderRepositoryInterface $stores,
    ) {}

    public function handle(object $message): DeliverySheetViewData
    {
        assert($message instanceof ListSheetsQuery);

        $copySheet = $message->copyId !== null ? $this->repository->sheet($message->copyId) : null;
        $copySnapshot = $copySheet !== null
            ? json_decode((string) $copySheet->snapshot, true, 512, JSON_THROW_ON_ERROR)
            : null;

        $sheets = $this->repository->sheets();
        $prints = $this->repository->prints();

        $savedSheets = $sheets->map(function ($sheet): SavedSheetOption {
            /** @var object{id:int,delivery_date:string,snapshot:string} $sheet */
            /** @var array{route:string,driver:string} $snapshot */
            $snapshot = json_decode((string) $sheet->snapshot, true, 512, JSON_THROW_ON_ERROR);

            return new SavedSheetOption($sheet->id, Carbon::parse($sheet->delivery_date)->format('d.m.Y').' · '.$snapshot['route'].' · '.$snapshot['driver']);
        });
        $printedSheets = $prints->map(function ($print): PrintedSheetHistoryItem {
            /** @var object{id:int,printed_at:string,snapshot:string} $print */
            /** @var array{route:string,driver:string,date:string,rows:list<array{has_order?:bool}>,net_cash?:int,cash_due?:int,due:int,salary_kopecks?:int,bonus_kopecks?:int} $snapshot */
            $snapshot = json_decode((string) $print->snapshot, true, 512, JSON_THROW_ON_ERROR);
            $filled = count(array_filter($snapshot['rows'], fn (array $row): bool => $row['has_order'] ?? false));
            $netCash = $snapshot['net_cash'] ?? (($snapshot['cash_due'] ?? $snapshot['due']) - ($snapshot['salary_kopecks'] ?? 0) - ($snapshot['bonus_kopecks'] ?? 0));

            return new PrintedSheetHistoryItem(
                $print->id,
                Carbon::parse($print->printed_at)->timezone('Europe/Moscow')->format('d.m.Y H:i').' · '.Carbon::parse($snapshot['date'])->format('d.m.Y').' · '.$snapshot['route'].' · '.$snapshot['driver'],
                $filled.' из '.count($snapshot['rows']).' точек с заказом · наличными после оплаты водителя '.number_format($netCash / 100, 2, ',', ' ').' ₽',
            );
        });

        return new DeliverySheetViewData([
            'date' => $message->date ?? $this->service->printDate(),
            'stores' => $this->stores->stores()->where('is_active', true),
            'savedSheets' => $savedSheets,
            'printedSheets' => $printedSheets,
            'copyId' => $message->copyId,
            'copySnapshot' => $copySnapshot,
        ]);
    }
}
