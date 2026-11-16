<?php

namespace Domains\DeliverySchedule\Presentation\Http\Controllers;

use Domains\DeliverySchedule\Application\Commands\CloseDate\CloseDateCommand;
use Domains\DeliverySchedule\Application\Commands\CloseDate\CloseDateHandler;
use Domains\DeliverySchedule\Application\Commands\OpenDate\OpenDateCommand;
use Domains\DeliverySchedule\Application\Commands\OpenDate\OpenDateHandler;
use Domains\DeliverySchedule\Application\Queries\ListClosedDates\ListClosedDatesHandler;
use Domains\DeliverySchedule\Application\Queries\ListClosedDates\ListClosedDatesQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClosedDateController
{
    public function __construct(
        private readonly ListClosedDatesHandler $listDates,
        private readonly CloseDateHandler $closeDate,
        private readonly OpenDateHandler $openDate,
    ) {}

    public function index(): View
    {
        $result = $this->listDates->handle(new ListClosedDatesQuery);

        return view('bakery.closed-dates', ['dates' => $result->dates, 'orderCounts' => $result->orderCounts]);
    }

    public function save(Request $request): RedirectResponse
    {
        /** @var array{delivery_date:string,reason:string,keep_existing_orders?:bool} $data */
        $data = $request->validate([
            'delivery_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:500'],
            'keep_existing_orders' => ['sometimes', 'boolean'],
        ], [
            'delivery_date.required' => 'Выберите дату поставки.',
            'delivery_date.date_format' => 'Укажите корректную дату поставки.',
        ]);

        $this->closeDate->handle(new CloseDateCommand(
            $data['delivery_date'],
            $data['reason'],
            (bool) ($data['keep_existing_orders'] ?? false),
        ));

        return redirect()->route('bakery.closed-dates')->with('success', 'Дата поставки закрыта.');
    }

    public function open(string $date): RedirectResponse
    {
        $this->openDate->handle(new OpenDateCommand($date));

        return redirect()->route('bakery.closed-dates')->with('success', 'Дата поставки открыта.');
    }
}
