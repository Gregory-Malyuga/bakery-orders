<?php

namespace Domains\Bakery\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClosedDateController
{
    public function index(): View
    {
        $dates = DB::table('bakery_closed_delivery_dates')->orderBy('delivery_date')->get();
        $orderCounts = DB::table('bakery_orders')->where('status', 'accepted')
            ->select('delivery_date')->selectRaw('COUNT(*) AS count')->groupBy('delivery_date')
            ->pluck('count', 'delivery_date');

        return view('bakery.closed-dates', ['dates' => $dates, 'orderCounts' => $orderCounts]);
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
        $reason = trim($data['reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Укажите причину закрытия.']);
        }

        DB::transaction(static function () use ($data, $reason): void {
            $acceptedOrders = DB::table('bakery_orders')->where('delivery_date', $data['delivery_date'])
                ->where('status', 'accepted')->count();
            if ($acceptedOrders > 0 && ! ($data['keep_existing_orders'] ?? false)) {
                throw ValidationException::withMessages([
                    'delivery_date' => "На эту дату уже принято {$acceptedOrders} заказов. Подтвердите закрытие с сохранением этих заказов.",
                ]);
            }
            DB::table('bakery_closed_delivery_dates')->updateOrInsert(
                ['delivery_date' => $data['delivery_date']],
                ['reason' => $reason, 'created_at' => now(), 'updated_at' => now()],
            );
        });

        return redirect()->route('bakery.closed-dates')->with('success', 'Дата поставки закрыта.');
    }

    public function open(string $date): RedirectResponse
    {
        DB::table('bakery_closed_delivery_dates')->where('delivery_date', $date)->delete();

        return redirect()->route('bakery.closed-dates')->with('success', 'Дата поставки открыта.');
    }
}
