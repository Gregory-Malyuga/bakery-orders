<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Маршрутный лист</title><link rel="stylesheet" href="/bakery.css?v={{ filemtime(public_path('bakery.css')) }}"></head>
<body><main class="wrap">
<div class="no-print actions print-actions"><a class="button secondary" href="{{ route('bakery.delivery-routes', ['date' => $sheet['date']]) }}">Маршруты</a><button class="button primary" onclick="window.print()">Печать</button></div>
<section class="invoice"><h1>Маршрутный лист · {{ \Illuminate\Support\Carbon::parse($sheet['date'])->format('d.m.Y') }}</h1><p><strong>{{ $sheet['route'] }}</strong> · водитель {{ $sheet['driver'] }}</p>@if($sheet['closed'])<p><strong>Дата поставки закрыта</strong></p>@endif
<table><thead><tr><th>№</th><th>Точка</th><th>Адрес</th><th>Заказ, ₽</th><th>Возврат, ₽</th><th>К получению, ₽</th></tr></thead><tbody>
@foreach($sheet['rows'] as $row)<tr><td>{{ $loop->iteration }}</td><td>{{ $row['name'] }}</td><td>{{ $row['address'] }}</td>@if($row['has_order'])<td>{{ number_format($row['order']/100, 2, ',', ' ') }}</td><td>{{ number_format($row['returns']/100, 2, ',', ' ') }}</td><td>{{ number_format($row['due']/100, 2, ',', ' ') }}</td>@else<td colspan="3">Нет заказа</td>@endif</tr>@endforeach
</tbody><tfoot><tr><th colspan="3">Итого</th><th>{{ number_format($sheet['order']/100, 2, ',', ' ') }}</th><th>{{ number_format($sheet['returns']/100, 2, ',', ' ') }}</th><th>{{ number_format($sheet['due']/100, 2, ',', ' ') }}</th></tr></tfoot></table></section>
</main></body></html>
