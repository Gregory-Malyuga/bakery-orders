<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Накладные</title><link rel="stylesheet" href="/bakery.css?v={{ filemtime(public_path('bakery.css')) }}"></head>
<body><main class="wrap">
    <div class="no-print actions print-actions"><a class="button secondary" href="{{ $downloadUrl }}">Скачать и отредактировать в Excel</a><button class="button primary" onclick="window.print()">Печать</button></div>
    @forelse($orders as $order)
        @php($return = $returns[$order->id] ?? ['quantities' => [], 'extras' => [], 'amount' => 0])
        @php($hasReturns = $return['amount'] > 0 || $return['quantities'] !== [] || $return['extras'] !== [])
        <section class="invoice">
            <h2>Накладная №{{ $order->id }} от {{ $order->delivery_date->format('d.m.Y') }}</h2>
            <p><strong>{{ $order->store_name }}</strong> · {{ $order->store_address }}</p>
            @if($order->comment)<p class="invoice-comment"><strong>Комментарий:</strong> {{ $order->comment }}</p>@endif
            <table><thead><tr><th>Товар</th><th>Заказано, шт.</th>@if($hasReturns)<th>Возврат, шт.</th>@endif<th>Цена, ₽</th><th>Сумма, ₽</th>@if($hasReturns)<th>После возврата, ₽</th>@endif</tr></thead>
                <tbody>
                @foreach($order->items as $item)
                    @php($returned = $return['quantities'][$item->product_id] ?? 0)
                    <tr><td>{{ $item->product_name }}</td><td>{{ $item->quantity }}</td>@if($hasReturns)<td>{{ $returned }}</td>@endif<td>{{ number_format($item->unit_kopecks / 100, 2, ',', ' ') }}</td><td>{{ number_format($item->total_kopecks / 100, 2, ',', ' ') }}</td>@if($hasReturns)<td>{{ number_format(($item->quantity - $returned) * $item->unit_kopecks / 100, 2, ',', ' ') }}</td>@endif</tr>
                @endforeach
                @foreach($return['extras'] as $extra)
                    <tr><td>{{ $extra['name'] }}</td><td>0</td><td>{{ $extra['quantity'] }}</td><td>{{ number_format($extra['unit_kopecks'] / 100, 2, ',', ' ') }}</td><td>0,00</td><td>{{ number_format(-$extra['quantity'] * $extra['unit_kopecks'] / 100, 2, ',', ' ') }}</td></tr>
                @endforeach
                </tbody></table>
            <div class="invoice-footer">Итого: {{ number_format($order->total_kopecks / 100, 2, ',', ' ') }} ₽@if($hasReturns) · Возврат: {{ number_format($return['amount'] / 100, 2, ',', ' ') }} ₽ · После возврата: {{ number_format(($order->total_kopecks - $return['amount']) / 100, 2, ',', ' ') }} ₽@endif</div>
        </section>
    @empty<p>На выбранную дату принятых заказов нет.</p>@endforelse
    @foreach($groups ?? [] as $group)
        @if($group['has_orders'])
            @include('bakery.group-invoice')
        @endif
    @endforeach
</main></body></html>
