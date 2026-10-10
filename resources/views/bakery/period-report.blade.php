@extends('bakery.layout')
@section('title', 'Отчёт за период')
@section('content')
<div class="page-head"><div><p class="eyebrow">Панель пекарни</p><h1>Отчёт за период</h1><p class="muted">Заказы, возвраты и расчёт расхода сырья по выбранным датам поставки.</p></div></div>
<nav class="admin-nav" aria-label="Разделы пекарни"><a href="{{ route('bakery.admin') }}">К заказам</a><a href="{{ route('bakery.report', ['from' => $from, 'to' => $to]) }}" aria-current="page">Отчёт</a></nav>
<div class="panel data-panel"><form method="get" action="{{ route('bakery.report') }}" class="form-grid"><label>С<input type="date" name="from" value="{{ $from }}" required></label><label>По<input type="date" name="to" value="{{ $to }}" required></label><button class="button primary">Показать</button><a class="button secondary" href="{{ route('bakery.report-xlsx', ['from' => $from, 'to' => $to]) }}">Скачать Excel</a></form></div>
<div class="section-head"><h2>Итоги</h2></div><div class="panel data-panel"><p>Строк заказов: <strong>{{ count($report['orders']) }}</strong>. Заказано на <strong>{{ number_format($report['total_kopecks'] / 100, 2, ',', ' ') }} ₽</strong>. Возвращено на <strong>{{ number_format($report['return_kopecks'] / 100, 2, ',', ' ') }} ₽</strong>. После возвратов: <strong>{{ number_format($report['net_kopecks'] / 100, 2, ',', ' ') }} ₽</strong>.</p><p class="muted small">Выгрузка содержит отдельные листы «Заказы», «Изделия» и «Сырьё». Возвраты показаны рядом с заказанным количеством и не вычитаются из производственного расхода. Расход рассчитан только по нормам, однозначно указанным в присланном файле; складские остатки не учитываются.</p></div>
<div class="section-head"><h2>Изделия</h2></div><div class="panel data-panel"><div class="table-scroll"><table><thead><tr><th>Изделие</th><th>Заказано, шт.</th></tr></thead><tbody>@forelse($report['products'] as $name => $quantity)<tr><td>{{ $name }}</td><td>{{ $quantity }}</td></tr>@empty<tr><td colspan="2" class="muted">За выбранный период принятых заказов нет.</td></tr>@endforelse</tbody></table></div></div>
<div class="section-head"><h2>Расход сырья</h2></div><div class="panel data-panel"><div class="table-scroll"><table><thead><tr><th>Раздел</th><th>Сырьё</th><th>Количество, кг</th></tr></thead><tbody>
@foreach(['main_dough' => 'Основное тесто', 'samsa_dough' => 'Тесто для самсы', 'ingredients' => 'Начинки и заготовки'] as $key => $section)
    @foreach($report['production'][$key] as $name => $grams)<tr><td>{{ $section }}</td><td>{{ $name }}</td><td>{{ number_format($grams / 1000, 3, ',', ' ') }}</td></tr>@endforeach
@endforeach
</tbody></table></div></div>
@if($report['production']['without_recipe'])<p class="muted small">Без полной нормы начинки: {{ implode(', ', $report['production']['without_recipe']) }}.</p>@endif
@endsection
