@extends('bakery.layout')
@section('title', 'Изменить заказ №'.$order->id)
@section('content')
<div class="page-head"><div><p class="eyebrow">Панель пекарни</p><h1>Изменить заказ №{{ $order->id }}</h1><p class="muted">{{ $order->store_name }} · поставка {{ $order->delivery_date->format('d.m.Y') }}</p></div></div>
<div class="panel data-panel"><p class="muted small">Для уже заказанных товаров сохраняется цена на момент оформления. Новые товары добавляются по действующей цене магазина. Изменение сразу попадёт в сводку и накладную.</p>
<form method="post" action="{{ route('bakery.admin-order-update', $order->id) }}">@csrf
    <div class="table-scroll"><table><thead><tr><th>Товар</th><th>Цена, ₽</th><th>Было, шт.</th><th>Нужно, шт.</th></tr></thead><tbody>
        @foreach($products as $product)
            @php($item = $order->items->firstWhere('product_id', $product->id))
            <tr><td>{{ $item?->product_name ?? $product->name }}</td><td>{{ number_format(($item?->unit_kopecks ?? $prices[$product->id]) / 100, 2, ',', ' ') }}</td><td>{{ $item?->quantity ?? 0 }}</td><td><input type="number" name="quantities[{{ $product->id }}]" value="{{ old('quantities.'.$product->id, $item?->quantity ?? 0) }}" min="0" max="100000" data-packaging="{{ $product->packaging_mode }}" required></td></tr>
        @endforeach
    </tbody></table></div>
    <div class="form-grid"><label>Причина изменения<input name="reason" value="{{ old('reason') }}" maxlength="500" required></label><label>Примечание пекарни<textarea name="admin_note" rows="3" maxlength="1000">{{ old('admin_note', $order->admin_note) }}</textarea></label></div>
    <div class="actions"><a class="button secondary" href="{{ route('bakery.admin-order', $order->id) }}">Назад</a><button class="button primary">Сохранить изменения</button></div>
</form></div>
<script>document.querySelectorAll('[data-packaging]').forEach(input=>{const validate=()=>{const quantity=Number(input.value),mode=input.dataset.packaging;let error='';if(quantity>0&&mode==='mixed'&&!Array.from({length:Math.min(Math.floor(quantity/3),4)+1},(_,i)=>i).some(packs=>(quantity-3*packs)%5===0))error='Количество должно складываться из упаковок по 3 и 5 шт.';if(quantity>0&&mode==='pair'&&quantity%2!==0)error='Только чётное количество';input.setCustomValidity(error)};input.addEventListener('input',validate);validate()})</script>
@endsection
