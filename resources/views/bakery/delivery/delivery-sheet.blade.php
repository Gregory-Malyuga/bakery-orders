<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Маршрутный лист</title><link rel="stylesheet" href="/bakery.css?v={{ filemtime(public_path('bakery.css')) }}"></head>
<body><main class="wrap">
<div class="no-print actions print-actions"><a class="button secondary" href="{{ route('bakery.delivery-routes', ['date' => $sheet->date]) }}">Маршруты</a>
@if(isset($archiveRecord))
<span>Архивная запись от {{ $printedAtLabel }}. Только просмотр.</span>
@elseif(isset($autoPrint))
<span>Состав листа сохранён в истории печати #{{ $printId }}.</span><button class="button primary" type="button" onclick="window.print()">Открыть печать</button>
@else
<a class="button secondary" href="{{ route('bakery.delivery-sheet-edit', $sheetId) }}">Редактировать</a>
<form method="post" action="{{ route('bakery.delivery-sheet-print') }}">@csrf
<input type="hidden" name="sheet_id" value="{{ $sheetId }}"><button class="button primary">Печать</button>
</form>
@endif
</div>
<section class="invoice"><h1>Маршрутный лист · {{ $sheet->formattedDate }}</h1><p><strong>{{ $sheet->route }}</strong> · водитель {{ $sheet->driver }}</p>
@if($sheet->salary !== null)<p><strong>Зарплата водителя:</strong> {{ $sheet->salary }} ₽</p>@endif
@if($sheet->bonus !== null)<p><strong>Бонус водителя:</strong> {{ $sheet->bonus }} ₽</p>@endif
@if($sheet->closed)<p><strong>Дата поставки закрыта</strong></p>@endif
<table><thead><tr><th>№</th><th>Точка</th><th>Адрес</th><th>Заказ, ₽</th><th>Возврат, ₽</th><th>К получению, ₽</th></tr></thead><tbody>
@foreach($sheet->rows as $row)<tr><td>{{ $loop->iteration }}</td><td>{{ $row->name }}</td><td>{{ $row->address }}</td>@if($row->hasOrder)<td>{{ $row->order }}</td><td>{{ $row->returns }}</td><td>{{ $row->due }}</td>@else<td colspan="3">Нет заказа</td>@endif</tr>@endforeach
</tbody><tfoot><tr><th colspan="3">Итого заказ / возврат / наличные</th><th>{{ $sheet->order }}</th><th>{{ $sheet->returns }}</th><th>{{ $sheet->cashDue }}</th></tr><tr><th colspan="5">Итого наличными за вычетом зарплаты и бонуса</th><th>{{ $sheet->netCash }}</th></tr></tfoot></table></section>
@if(isset($autoPrint))<script>window.addEventListener('load', () => window.print());</script>@endif
</main></body></html>
