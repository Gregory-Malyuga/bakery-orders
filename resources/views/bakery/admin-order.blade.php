@extends('bakery.layout')
@section('title', 'Заказ №'.$order->id)
@section('content')
<div class="page-head">
    <div><p class="eyebrow">Панель пекарни</p><h1>Заказ №{{ $order->id }}</h1><p class="muted">{{ $order->store_name }} · {{ $order->store_address }} · поставка {{ $order->delivery_date->format('d.m.Y') }}</p></div>
    <span class="status {{ $order->status }}">{{ $order->status === 'accepted' ? 'Принят' : 'Отменён' }}</span>
</div>
<div class="panel data-panel"><div class="table-scroll"><table>
    <thead><tr><th>Товар</th><th>Шт.</th><th>Цена</th><th>Сумма</th></tr></thead>
    <tbody>@foreach($order->items as $item)<tr><td>{{ $item->product_name }}</td><td>{{ $item->quantity }}</td><td>{{ number_format($item->unit_kopecks / 100, 2, ',', ' ') }} ₽</td><td>{{ number_format($item->total_kopecks / 100, 2, ',', ' ') }} ₽</td></tr>@endforeach</tbody>
</table></div><div class="grand-total">Итого <strong>{{ number_format($order->total_kopecks / 100, 2, ',', ' ') }} ₽</strong></div></div>
<div class="actions">
    <a class="button secondary" href="{{ route('bakery.admin', ['date' => $order->delivery_date->toDateString()]) }}">К заказам</a>
    @if($order->status === 'accepted')
        <a class="button secondary" href="{{ route('bakery.admin-order-edit', $order->id) }}">Изменить заявку</a>
        <a class="button primary" href="{{ route('bakery.print-one', $order->id) }}" target="_blank">Печать накладной</a>
        <a class="button secondary" href="{{ route('bakery.xlsx-one', $order->id) }}">Скачать Excel</a>
    @endif
</div>
@if($order->comment)<div class="panel data-panel"><strong>Комментарий магазина</strong><p class="comment-text">{{ $order->comment }}</p></div>@endif
@if($order->admin_note)<div class="panel data-panel"><strong>Примечание пекарни</strong><p class="comment-text">{{ $order->admin_note }}</p></div>@endif
@if($order->status === 'accepted')
    <div class="section-head"><h2>Зафиксировать возврат</h2></div>
    <div class="panel data-panel"><form method="post" action="{{ route('bakery.admin-order-return', $order->id) }}" class="form-grid">@csrf
        <label>Товар<select name="product" required>@foreach($order->items as $item)<option value="{{ $item->product_id }}">{{ $item->product_name }}</option>@endforeach</select></label>
        <label>Количество, шт.<input type="number" name="quantity" min="1" max="100000" required></label>
        <label>Причина или комментарий<input name="reason" maxlength="500"></label>
        <button class="button primary">Сохранить возврат</button>
    </form><p class="muted small">Возврат учитывается отдельно и не уменьшает задание на производство.</p></div>
@endif
@if($returns->isNotEmpty())
    <div class="section-head"><h2>Возвраты</h2></div><div class="panel data-panel"><div class="table-scroll"><table><thead><tr><th>Когда</th><th>Товар</th><th>Шт.</th><th>Причина</th></tr></thead><tbody>
    @foreach($returns as $return)<tr><td>{{ \Illuminate\Support\Carbon::parse($return->created_at)->timezone('Europe/Moscow')->format('d.m.Y H:i') }}</td><td>{{ $order->items->firstWhere('product_id', $return->product_id)?->product_name ?? 'Товар #'.$return->product_id }}</td><td>{{ $return->quantity }}</td><td>{{ $return->reason ?: '—' }}</td></tr>@endforeach
    </tbody></table></div></div>
@endif
@if($adjustments->isNotEmpty())
    <div class="section-head"><h2>История корректировок</h2></div><div class="panel data-panel"><div class="table-scroll"><table><thead><tr><th>Когда</th><th>Причина</th><th>Сумма до</th><th>Сумма после</th></tr></thead><tbody>
    @foreach($adjustments as $change)<tr><td>{{ \Illuminate\Support\Carbon::parse($change->created_at)->timezone('Europe/Moscow')->format('d.m.Y H:i') }}</td><td>{{ $change->reason }}</td><td>{{ number_format($change->before_total_kopecks / 100, 2, ',', ' ') }} ₽</td><td>{{ number_format($change->after_total_kopecks / 100, 2, ',', ' ') }} ₽</td></tr>@endforeach
    </tbody></table></div></div>
@endif
@endsection
