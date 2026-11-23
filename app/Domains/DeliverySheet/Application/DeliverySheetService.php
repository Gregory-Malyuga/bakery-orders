<?php

namespace Domains\DeliverySheet\Application;

use Domains\DeliverySchedule\Application\Repositories\ClosedDateRepositoryInterface;
use Domains\Order\Application\OrderCutoff;
use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Domains\OrderReturn\Application\OrderReturnSummary;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

readonly class DeliverySheetService
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private OrderReturnSummary $returns,
        private ClosedDateRepositoryInterface $dates,
        private OrderCutoff $cutoff,
    ) {}

    /**
     * @param  array{date:string,name:string,driver:string,salary?:string,bonus?:string,stores:list<int|string>}  $data
     * @return array{date:string,driver:string,route:string,salary_kopecks:int|null,
     *     bonus_kopecks:int|null,closed:bool,rows:list<array{store_id:int,name:string,address:string,
     *     order:int,returns:int,due:int,has_order:bool,cashless:bool}>,order:int,returns:int,due:int,
     *     cash_due:int,net_cash:int}
     */
    public function build(array $data): array
    {
        $date = $data['date'];
        /** @var list<int> $ids */
        $ids = array_map(static fn (int|string $id): int => (int) $id, $data['stores']);
        $driver = trim($data['driver']);
        if ($driver === '' || $ids === []) {
            throw ValidationException::withMessages([
                'driver' => 'Укажите водителя и выберите точки.',
            ]);
        }
        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['stores' => 'Точка повторяется в маршруте.']);
        }
        $stores = $this->orders->stores()->keyBy('id');
        $orders = $this->orders->orders($date);
        $byStore = $orders->keyBy('bakery_id');
        $returns = $this->returns->forOrders($orders);
        $rows = [];
        foreach ($ids as $id) {
            $store = $stores->get((int) $id);
            if ($store === null) {
                throw ValidationException::withMessages(['stores' => 'Одна из точек удалена.']);
            }
            $order = $byStore->get((int) $id);
            $amount = $order ? (int) $order->total_kopecks : 0;
            $returned = $order ? $returns[$order->id]['amount'] ?? 0 : 0;
            $rows[] = [
                'store_id' => (int) $id,
                'name' => (string) $store->name,
                'address' => (string) $store->address,
                'order' => $amount,
                'returns' => $returned,
                'due' => $amount - $returned,
                'has_order' => $order !== null,
                'cashless' => (bool) $store->is_cashless,
            ];
        }
        $salary =
            isset($data['salary']) && $data['salary'] !== ''
                ? (int) round((float) str_replace(',', '.', $data['salary']) * 100)
                : null;
        $bonus =
            isset($data['bonus']) && $data['bonus'] !== ''
                ? (int) round((float) str_replace(',', '.', $data['bonus']) * 100)
                : null;
        $cashDue = array_sum(
            array_map(static fn (array $row): int => $row['cashless'] ? 0 : $row['due'], $rows),
        );

        return [
            'date' => $date,
            'driver' => $driver,
            'route' => trim($data['name']),
            'salary_kopecks' => $salary,
            'bonus_kopecks' => $bonus,
            'closed' => $this->dates->isClosed($date),
            'rows' => $rows,
            'order' => array_sum(array_column($rows, 'order')),
            'returns' => array_sum(array_column($rows, 'returns')),
            'due' => array_sum(array_column($rows, 'due')),
            'cash_due' => $cashDue,
            'net_cash' => $cashDue - ($salary ?? 0) - ($bonus ?? 0),
        ];
    }

    public function cutoffTime(): string
    {
        return $this->cutoff->time();
    }

    public function printDate(): string
    {
        $now = now('Europe/Moscow');

        return $now->lt($now->copy()->setTimeFromTimeString($this->cutoffTime()))
            ? $now->toDateString()
            : $now->copy()->addDay()->toDateString();
    }

    /**
     * @param  array{date:string,route:string,driver:string,salary_kopecks?:int|null,
     *     bonus_kopecks?:int|null,rows:list<array{store_id:int,due:int,cashless?:bool}>}  $source
     * @return array{date:string,route:string,driver:string,salary_kopecks?:int|null,
     *     bonus_kopecks?:int|null,rows:list<array{store_id:int,due:int,cashless?:bool}>,cash_due?:int,
     *     net_cash?:int}
     */
    public function sheetForPrint(array $source): array
    {
        $date = $this->printDate();
        if ($date === $source['date']) {
            $stores = $this->orders->stores()->keyBy('id');
            $cashDue = 0;
            foreach ($source['rows'] as &$row) {
                $store = $stores->get($row['store_id']);
                $row['cashless'] =
                    $store !== null ? (bool) $store->is_cashless : $row['cashless'] ?? false;
                if (! $row['cashless']) {
                    $cashDue += $row['due'];
                }
            }
            unset($row);
            $source['cash_due'] = $cashDue;
            $source['net_cash'] =
                $cashDue - ($source['salary_kopecks'] ?? 0) - ($source['bonus_kopecks'] ?? 0);

            return $source;
        }

        $this->assertAfterCutoff($date);

        return $this->build([
            'date' => $date,
            'name' => $source['route'],
            'driver' => $source['driver'],
            'salary' => isset($source['salary_kopecks'])
                ? (string) ($source['salary_kopecks'] / 100)
                : '',
            'bonus' => isset($source['bonus_kopecks'])
                ? (string) ($source['bonus_kopecks'] / 100)
                : '',
            'stores' => array_column($source['rows'], 'store_id'),
        ]);
    }

    public function assertAfterCutoff(string $date): void
    {
        $now = now('Europe/Moscow');
        $cutoff = $this->cutoffTime();
        if (
            $now->lt($now->copy()->setTimeFromTimeString($cutoff)) &&
            $date !== $now->toDateString()
        ) {
            throw ValidationException::withMessages([
                'date' => 'До закрытия приёма заказов можно составить лист только на сегодня.',
            ]);
        }
        $deadline = Carbon::parse($date, 'Europe/Moscow')->subDay()->setTimeFromTimeString($cutoff);
        if ($now->lt($deadline)) {
            throw ValidationException::withMessages([
                'date' => 'Печать доступна после закрытия приёма заказов.',
            ]);
        }
    }
}
