@extends('bakery.layout')
@section('title', 'Нерабочие даты поставки')
@section('content')
<div class="page-head"><div><p class="eyebrow">Панель пекарни</p><h1>Нерабочие даты поставки</h1><p class="muted">Заказ на закрытую дату не принимается накануне. Уже принятые заказы сохраняются до решения администратора.</p></div></div>
<div class="panel data-panel">
    <h2>Закрыть дату поставки</h2>
    <form method="post" action="{{ route('bakery.closed-dates-save') }}" class="closed-date-form">
        @csrf
        <label>Дата поставки<input type="date" name="delivery_date" value="{{ old('delivery_date') }}" required></label>
        <label>Причина<input name="reason" value="{{ old('reason') }}" maxlength="500" required></label>
        <label class="closed-date-confirm"><input type="checkbox" name="keep_existing_orders" value="1" @checked(old('keep_existing_orders'))> <span>Если заказы уже приняты, закрыть дату с их сохранением</span></label>
        <button class="button primary">Закрыть дату</button>
    </form>
</div>
<div class="panel data-panel"><h2>Закрытые даты</h2><div class="table-scroll"><table><thead><tr><th>Дата поставки</th><th>Причина</th><th>Принятых заказов</th><th></th></tr></thead><tbody>
@forelse($dates as $date)
<tr><td>{{ \Illuminate\Support\Carbon::parse($date->delivery_date)->format('d.m.Y') }}</td><td>{{ $date->reason }}</td><td><a href="{{ route('bakery.admin', ['date' => $date->delivery_date, 'tab' => 'orders']) }}">{{ $orderCounts[$date->delivery_date] ?? 0 }}</a></td><td><form method="post" action="{{ route('bakery.closed-dates-open', $date->delivery_date) }}">@csrf<button class="button secondary">Открыть дату</button></form></td></tr>
@empty<tr><td colspan="4" class="muted">Закрытых дат нет.</td></tr>
@endforelse
</tbody></table></div></div>
@endsection
