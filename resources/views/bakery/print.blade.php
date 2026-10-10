<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Накладные</title><link rel="stylesheet" href="/bakery.css"></head>
<body><main class="wrap">
    <div class="no-print actions"><a class="button secondary" href="{{ $downloadUrl }}">Скачать и отредактировать в Excel</a><button class="button primary" onclick="window.print()">Печать</button></div>
    @forelse($orders as $order)
        <section class="invoice">
            <h2>Накладная №{{ $order->id }} от {{ $order->delivery_date->format('d.m.Y') }}</h2>
            <p><strong>{{ $order->store_name }}</strong> · {{ $order->store_address }}</p>
            @if($order->comment)<p class="invoice-comment"><strong>Комментарий:</strong> {{ $order->comment }}</p>@endif
            <table><thead><tr><th>Товар</th><th>Шт.</th><th>Цена, ₽</th><th>Сумма, ₽</th></tr></thead>
                <tbody>@foreach($order->items as $item)<tr><td>{{ $item->product_name }}</td><td>{{ $item->quantity }}</td><td>{{ intdiv($item->unit_kopecks,100) }},{{ str_pad($item->unit_kopecks%100,2,'0',STR_PAD_LEFT) }}</td><td>{{ intdiv($item->total_kopecks,100) }},{{ str_pad($item->total_kopecks%100,2,'0',STR_PAD_LEFT) }}</td></tr>@endforeach</tbody></table>
            <div class="invoice-footer">Итого: {{ intdiv($order->total_kopecks,100) }},{{ str_pad($order->total_kopecks%100,2,'0',STR_PAD_LEFT) }} ₽</div>
        </section>
    @empty<p>На выбранную дату принятых заказов нет.</p>@endforelse
</main></body></html>
