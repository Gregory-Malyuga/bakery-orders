<?php

namespace Domains\DeliverySheet\Application\Data;

use Illuminate\Support\Carbon;

readonly class DeliverySheetPrintView
{
    /** @param list<DeliverySheetPrintRow> $rows */
    public function __construct(
        public string $date,
        public string $formattedDate,
        public string $route,
        public string $driver,
        public ?string $salary,
        public ?string $bonus,
        public bool $closed,
        public array $rows,
        public string $order,
        public string $returns,
        public string $cashDue,
        public string $netCash,
    ) {}

    /**
     * @param  array{date:string,route:string,driver:string,closed:bool,
     *     rows:list<array{name:string,address:string,has_order:bool,order:int,returns:int,due:int,
     *     cashless?:bool}>,order:int,returns:int,due:int,cash_due?:int,net_cash?:int,
     *     salary_kopecks?:int|null,bonus_kopecks?:int|null}  $sheet
     */
    public static function fromSnapshot(array $sheet): self
    {
        $cashDue = $sheet['cash_due'] ?? $sheet['due'];
        $netCash =
            $sheet['net_cash'] ??
            $cashDue - ($sheet['salary_kopecks'] ?? 0) - ($sheet['bonus_kopecks'] ?? 0);

        return new self(
            $sheet['date'],
            Carbon::parse($sheet['date'])->format('d.m.Y'),
            $sheet['route'],
            $sheet['driver'],
            isset($sheet['salary_kopecks']) ? self::money($sheet['salary_kopecks']) : null,
            isset($sheet['bonus_kopecks']) ? self::money($sheet['bonus_kopecks']) : null,
            $sheet['closed'],
            array_map(
                static fn (array $row): DeliverySheetPrintRow => new DeliverySheetPrintRow(
                    $row['name'],
                    $row['address'],
                    $row['has_order'],
                    self::money($row['order']),
                    self::money($row['returns']),
                    $row['cashless'] ?? false ? 'Безналичный расчёт' : self::money($row['due']),
                ),
                $sheet['rows'],
            ),
            self::money($sheet['order']),
            self::money($sheet['returns']),
            self::money($cashDue),
            self::money($netCash),
        );
    }

    private static function money(int $kopecks): string
    {
        return number_format($kopecks / 100, 2, ',', ' ');
    }
}
