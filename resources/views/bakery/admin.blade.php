@extends('bakery.layout')
@section('title', 'Панель пекарни')
@section('content')
<div class="page-head"><div><p class="eyebrow">Панель пекарни</p><h1>Заказы на производство</h1><p class="muted">Все принятые заказы по дате поставки.</p></div></div>
<div class="panel data-panel"><form method="get" action="{{ route('bakery.admin') }}" class="toolbar-form"><input type="hidden" name="tab" value="{{ $tab }}"><label class="toolbar-date"><span>Дата поставки</span><input type="date" name="date" value="{{ $date }}" required></label><button class="button primary">Показать</button><a class="button secondary" href="{{ route('bakery.print-batch',['date'=>$date]) }}" target="_blank">Печать всех накладных</a><a class="button secondary" href="{{ route('bakery.xlsx-batch',['date'=>$date]) }}">Скачать все в Excel</a></form><p class="muted small">При печати каждая накладная начинается с новой страницы. Файл Excel можно отредактировать перед печатью.</p></div>
@if($tab === 'orders')
<div class="section-head"><h2>Принятые заказы <span class="muted">{{ $orders->count() }}</span></h2></div><div class="panel data-panel"><div class="table-scroll"><table><thead><tr><th>Магазин</th><th>Адрес</th><th>Позиций</th><th>Сумма</th><th></th></tr></thead><tbody>@forelse($visibleOrders as $order)<tr><td>{{ $order->store_name }}</td><td>{{ $order->store_address }}</td><td>{{ $order->items->count() }}</td><td>{{ intdiv($order->total_kopecks,100) }},{{ str_pad($order->total_kopecks%100,2,'0',STR_PAD_LEFT) }} ₽</td><td><a href="{{ route('bakery.admin-order',$order->id) }}">Открыть</a></td></tr>@empty<tr><td colspan="5" class="muted">На эту дату принятых заказов нет.</td></tr>@endforelse</tbody></table></div></div>
@include('bakery.pagination', ['url' => route('bakery.admin'), 'query' => ['date' => $date, 'tab' => 'orders']])
<div class="section-head"><h2>Ещё не заказали</h2></div><div class="panel data-panel"><p>@forelse($activeStores->whereNotIn('id',$orderedStoreIds) as $store)<span class="date-pill" style="display:inline-block;margin:4px">{{ $store->name }}</span>@empty<span class="muted">Все активные магазины заказали.</span>@endforelse</p></div>
@elseif($tab === 'production')
<div class="section-head"><h2>Производственная сводка</h2><span class="muted">Количество, шт.</span></div><div class="panel data-panel summary-scroll"><table class="summary-table"><thead><tr><th>Товар</th>@foreach($matrixStores as $store)<th title="{{ $store->address }}">{{ $store->name }}</th>@endforeach<th>Всего</th></tr></thead><tbody>@foreach($products as $product)@if(isset($totals[$product->id]))<tr><th>{{ $product->name }}</th>@foreach($matrixStores as $store)<td>{{ $quantities[$product->id][$store->id] ?? '—' }}</td>@endforeach<td>{{ $totals[$product->id] }}</td></tr>@endif @endforeach</tbody></table></div>
<div class="section-head"><h2>К выпуску</h2><span class="muted">Всего изделий: {{ $productionTotal }} шт.</span></div><div class="panel data-panel summary-list">@forelse($products->filter(fn($p)=>isset($totals[$p->id])) as $product)<p>{{ $product->name }} <strong>→ {{ $totals[$product->id] }} шт.</strong></p>@empty<p class="muted">Нет продукции к выпуску.</p>@endforelse</div>
@elseif($tab === 'packaging')
<div class="section-head"><h2>Фасовка</h2></div><div class="panel data-panel"><p class="muted small">Пакеты рассчитаны отдельно для каждого магазина. Остаток означает штуки, которые нельзя разложить по выбранным размерам упаковки.</p><div class="table-scroll"><table><thead><tr><th>Товар</th><th>По 1</th><th>По 2</th><th>По 3</th><th>По 5</th><th>Остаток, шт.</th></tr></thead><tbody>@forelse($products->filter(fn($p)=>isset($packaging[$p->id])) as $product)<tr><th>{{ $product->name }}</th><td>{{ $packaging[$product->id]['one'] ?: '—' }}</td><td>{{ $packaging[$product->id]['two'] ?: '—' }}</td><td>{{ $packaging[$product->id]['three'] ?: '—' }}</td><td>{{ $packaging[$product->id]['five'] ?: '—' }}</td><td>{{ $packaging[$product->id]['loose'] ?: '—' }}</td></tr>@empty<tr><td colspan="6" class="muted">Нет продукции к фасовке.</td></tr>@endforelse</tbody></table></div></div>
@elseif($tab === 'labels')
<div class="section-head"><h2>Наклейки</h2><span class="muted">Всего {{ $labelTotal }} шт.</span></div>
<div class="panel data-panel"><p class="muted small">Одна наклейка на каждый пакет. Фасовка рассчитана отдельно для каждого магазина; размеры пакетов нельзя объединять между точками.</p><div class="table-scroll"><table><thead><tr><th>Товар</th><th>По 1</th><th>По 2</th><th>По 3</th><th>По 5</th><th>Всего наклеек</th></tr></thead><tbody>@forelse($products->filter(fn($p)=>isset($labels[$p->id])) as $product)<tr><td>{{ $product->name }}</td><td>{{ $packaging[$product->id]['one'] ?: '—' }}</td><td>{{ $packaging[$product->id]['two'] ?: '—' }}</td><td>{{ $packaging[$product->id]['three'] ?: '—' }}</td><td>{{ $packaging[$product->id]['five'] ?: '—' }}</td><td>{{ $labels[$product->id] }}</td></tr>@empty<tr><td colspan="6" class="muted">Нет продукции.</td></tr>@endforelse</tbody></table></div></div>
@else
<div class="section-head"><h2>Заготовки по рецептурам</h2></div>
<div class="panel data-panel">
    <p>Основное тесто: <strong>{{ \Domains\Bakery\Application\WeightFormatter::label($production['dough_grams']) }} кг</strong>. Самса: <strong>{{ $production['samsa_count'] }} шт.</strong> ({{ number_format($production['samsa_count'] / $production['samsa_batch_size'], 2, ',', ' ') }} замеса по {{ number_format($production['samsa_batch_size'], 0, ',', ' ') }} шт.).</p>
    <p class="muted small">Базовые нормы взяты из листа 12.09 присланного файла; изменения на странице «Рецептуры» учитываются сразу. Остатки не включены.</p>
    @foreach(['main_dough' => 'ТЕСТО Пирожки', 'samsa_dough' => 'ТЕСТО на САМСУ'] as $section => $heading)
        <h3>{{ $heading }}</h3>
        <div class="table-scroll"><table><thead><tr><th>Сырьё</th><th>Количество, кг</th></tr></thead><tbody>
            @foreach($production[$section] as $ingredient => $grams)<tr><th>{{ $ingredient }}</th><td>{{ \Domains\Bakery\Application\WeightFormatter::label($grams) }}</td></tr>@endforeach
        </tbody></table></div>
    @endforeach
    @foreach($production['recipes'] as $name => $recipe)
        <h3>{{ $name }} — {{ $recipe['quantity'] }} шт.</h3>
        <div class="table-scroll"><table><thead><tr><th>Сырьё</th><th>Количество</th></tr></thead><tbody>
            @foreach($recipe['ingredients'] as $ingredient => $grams)<tr><th>{{ $ingredient }}</th><td>{{ \Domains\Bakery\Application\WeightFormatter::label($grams) }} кг</td></tr>@endforeach
            @foreach($recipe['other_ingredients'] ?? [] as $ingredient => $amount)<tr><th>{{ $ingredient }}</th><td>{{ number_format($amount['quantity'], 3, ',', ' ') }} {{ $amount['unit'] }}</td></tr>@endforeach
        </tbody></table></div>
    @endforeach
    @if($production['without_recipe'])<p class="muted small">В файле нет однозначной нормы начинки для: {{ implode(', ', $production['without_recipe']) }}.</p>@endif
</div>
@endif
@endsection
