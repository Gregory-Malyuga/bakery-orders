@extends('bakery.layout')
@section('title', 'Аналитика')
@section('content')
<div class="page-head"><div><p class="eyebrow">Панель пекарни</p><h1>Аналитика</h1><p class="muted">Период считается по дате поставки. Отменённые заказы и отменённые возвраты исключены. Обновлено {{ $data['updated_at'] }} МСК.</p></div></div>
<div class="panel data-panel"><form method="get" action="{{ route('bakery.analytics') }}" class="form-grid filter-form analytics-filter-form">
<label>С<input type="date" name="from" value="{{ $filters['from'] }}" required></label><label>По<input type="date" name="to" value="{{ $filters['to'] }}" required></label>
<label>Точка<select name="store"><option value="">Все</option>@foreach($stores as $store)<option value="{{ $store->id }}" @selected($filters['store'] == $store->id)>{{ $store->name }}</option>@endforeach</select></label>
<label>Товар<select name="product"><option value="">Все</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected($filters['product'] == $product->id)>{{ $product->name }}</option>@endforeach</select></label>
<button class="button primary">Показать</button><a class="button secondary analytics-download" href="{{ route('bakery.analytics-csv', array_filter($filters, fn ($value) => $value !== null)) }}">Скачать CSV</a></form></div>
<div class="panel data-panel"><h2>Итоги периода</h2><div class="table-scroll"><table><thead><tr><th>Заказы</th><th>Заказано, шт.</th><th>Возвращено, шт.</th><th>Выручка до возвратов</th><th>Возвраты</th><th>Выручка после возвратов</th><th>Доля возвратов</th></tr></thead><tbody><tr>
<td>{{ $data['summary']['orders'] }}</td><td>{{ $data['summary']['pieces'] }}</td><td>{{ $data['summary']['returned_pieces'] }}</td>
<td>{{ number_format($data['summary']['gross'] / 100, 2, ',', ' ') }} ₽</td><td>{{ number_format($data['summary']['returns'] / 100, 2, ',', ' ') }} ₽</td><td>{{ number_format($data['summary']['net'] / 100, 2, ',', ' ') }} ₽</td><td>{{ number_format($data['summary']['return_share'], 1, ',', ' ') }}%</td>
</tr></tbody></table></div><p class="muted small">Заказы — число принятых заказов с выбранным товаром. Выручка до возвратов — сумма заказанных позиций. Выручка после возвратов = до возвратов − сумма действующих возвратов. Доля возвратов = сумма возвратов / выручка до возвратов × 100%. Если выручка равна нулю, доля показана как 0%.</p></div>
@foreach(['days' => 'По дням поставки', 'stores' => 'По точкам', 'products' => 'По товарам'] as $key => $title)
<div class="panel data-panel"><h2>{{ $title }}</h2><div class="table-scroll"><table><thead><tr><th>{{ $key === 'days' ? 'Дата' : ($key === 'stores' ? 'Точка' : 'Товар') }}</th><th>Заказы</th><th>Штуки</th><th>Возвраты, шт.</th><th>До возвратов</th><th>Возвраты</th><th>После возвратов</th><th>Доля</th></tr></thead><tbody>
@forelse($data[$key] as $row)<tr><td>{{ $row['label'] }}</td><td>{{ $row['orders'] }}</td><td>{{ $row['pieces'] }}</td><td>{{ $row['returned_pieces'] }}</td><td>{{ number_format($row['gross'] / 100, 2, ',', ' ') }} ₽</td><td>{{ number_format($row['returns'] / 100, 2, ',', ' ') }} ₽</td><td>{{ number_format($row['net'] / 100, 2, ',', ' ') }} ₽</td><td>{{ number_format($row['return_share'], 1, ',', ' ') }}%</td></tr>@empty<tr><td colspan="8">Заказов за период нет.</td></tr>@endforelse
</tbody></table></div></div>
@endforeach
<div class="panel data-panel"><h2>Точки без заказа</h2><p class="muted small">Активные точки без принятого заказа за выбранный период{{ $filters['product'] !== null ? ' по выбранному товару' : '' }}.</p>@if($data['missing_stores'] === [])<p>Все активные точки представлены в заказах.</p>@else<ul>@foreach($data['missing_stores'] as $name)<li>{{ $name }}</li>@endforeach</ul>@endif</div>
@endsection
