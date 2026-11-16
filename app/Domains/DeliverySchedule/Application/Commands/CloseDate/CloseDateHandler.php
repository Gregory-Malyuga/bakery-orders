<?php

namespace Domains\DeliverySchedule\Application\Commands\CloseDate;

use Domains\DeliverySchedule\Application\Repositories\ClosedDateRepositoryInterface;
use Domains\Order\Application\Repositories\OrderCalendarRepositoryInterface;
use Illuminate\Validation\ValidationException;
use Shared\Bus\CommandHandlerInterface;

readonly class CloseDateHandler implements CommandHandlerInterface
{
    public function __construct(
        private ClosedDateRepositoryInterface $repository,
        private OrderCalendarRepositoryInterface $orders,
    ) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof CloseDateCommand);

        $reason = trim($message->reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Укажите причину закрытия.']);
        }

        $this->repository->transaction(function () use ($message, $reason): void {
            $acceptedOrders = $this->orders->acceptedCountOn($message->date);

            if ($acceptedOrders > 0 && ! $message->keepExistingOrders) {
                throw ValidationException::withMessages([
                    'delivery_date' => "На эту дату уже принято {$acceptedOrders} заказов. Подтвердите закрытие с сохранением этих заказов.",
                ]);
            }

            $this->repository->close($message->date, $reason);
        });

        return null;
    }
}
