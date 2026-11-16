<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Накладные</title><link rel="stylesheet" href="/bakery.css?v={{ filemtime(public_path('bakery.css')) }}"></head>
<body><main class="wrap">
    <div class="no-print actions print-actions"><a class="button secondary" href="{{ $downloadUrl }}">Скачать и отредактировать в Excel</a><button class="button primary" onclick="window.print()">Печать</button></div>
    @forelse($orders as $order)
        <section class="invoice">
            <h2>Накладная №{{ $order->id }} от {{ $order->delivery_date->format('d.m.Y') }}</h2>
            <p><strong>{{ $order->store_name }}</strong> · {{ $order->store_address }}</p>
            @if($order->comment)<p class="invoice-comment"><strong>Комментарий:</strong> {{ $order->comment }}</p>@endif
            <table><thead><tr><th>Товар</th><th>Заказано, шт.</th><th>Возврат, шт.</th><th>Цена, ₽</th><th>Сумма, ₽</th><th>После возврата, ₽</th></tr></thead>
                <tbody>@foreach($order->items as $item)@php($returned = $returns[$order->id][$item->product_id] ?? 0)<tr><td>{{ $item->product_name }}</td><td>{{ $item->quantity }}</td><td>{{ $returned }}</td><td>{{ number_format($item->unit_kopecks / 100, 2, ',', ' ') }}</td><td>{{ number_format($item->total_kopecks / 100, 2, ',', ' ') }}</td><td>{{ number_format(($item->quantity - $returned) * $item->unit_kopecks / 100, 2, ',', ' ') }}</td></tr>@endforeach</tbody></table>
            @php($returnKopecks = $order->items->sum(fn($item) => ($returns[$order->id][$item->product_id] ?? 0) * $item->unit_kopecks))
            <div class="invoice-footer">Итого: {{ number_format($order->total_kopecks / 100, 2, ',', ' ') }} ₽ · Возврат: {{ number_format($returnKopecks / 100, 2, ',', ' ') }} ₽ · После возврата: {{ number_format(($order->total_kopecks - $returnKopecks) / 100, 2, ',', ' ') }} ₽</div>
        </section>
    @empty<p>На выбранную дату принятых заказов нет.</p>@endforelse
</main></body></html>
