@extends('bakery.layout')
@section('title', $item->name)
@section('content')
<div class="page-head"><div><p class="eyebrow">Справочники → Товары</p><h1>{{ $item->name }}</h1><a href="{{ route('bakery.catalog', ['tab' => 'products']) }}">← Все товары</a></div></div>
@if($errors->any())<div class="panel"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="panel data-panel">
    <h2>Изменение цен</h2><p class="muted">Выберите точки, введите сумму и проверьте будущую цену перед подтверждением. Точки с общим прайсом выбираются вместе.</p>
    <form method="post" action="{{ route('bakery.bulk-price', $item->id) }}" id="bulk-price-form">@csrf
        <input type="hidden" name="request_key" value="{{ old('request_key', (string) Str::uuid()) }}">
        <div class="form-grid bulk-price-controls"><label>Действие<select name="direction" id="price-direction"><option value="increase" @selected(old('direction') === 'increase')>Повысить</option><option value="decrease" @selected(old('direction') === 'decrease')>Понизить</option></select></label><label>На сумму, ₽<input name="rubles" id="price-delta" inputmode="decimal" value="{{ old('rubles') }}" pattern="[0-9]+([.,][0-9]{1,2})?" required></label></div>
        <div class="table-scroll"><table><thead><tr><th><label class="price-select-all"><input type="checkbox" id="price-select-all"> Выбрать все</label></th><th>Точка</th><th>Адрес</th><th>Доступность</th><th>Текущая цена</th><th>После изменения</th></tr></thead><tbody>
        @foreach($stores as $store)<tr><td><input type="checkbox" name="stores[]" value="{{ $store->id }}" class="price-store" @checked(in_array($store->id, old('stores', [])))></td><td>{{ $store->name }}</td><td>{{ $store->address }}</td><td>{{ isset($prices[$store->id]) && $prices[$store->id] > 0 && !isset($unavailable[$store->id]) ? 'Доступен' : 'Недоступен' }}</td><td>{{ isset($prices[$store->id]) ? number_format($prices[$store->id] / 100, 2, ',', ' ') . ' ₽' : 'Не назначена' }}</td><td class="price-preview" data-kopecks="{{ $prices[$store->id] ?? '' }}">—</td></tr>@endforeach
        </tbody></table></div><p id="price-selection" aria-live="polite">Точки не выбраны.</p><button class="button primary">Подтвердить изменение</button>
    </form>
</div>
<div class="panel data-panel"><h2>История цен</h2>
@forelse($history as $entry)
    <article><h3>{{ $entry->kind === 'bulk' ? ($entry->direction === 'increase' ? '+' : '−').number_format($entry->delta_kopecks / 100, 2, ',', ' ').' ₽' : ($entry->kind === 'single' ? 'Одиночное изменение' : 'Отмена операции #'.$entry->reverses_id) }}</h3><p>{{ $entry->author }} · {{ $entry->created_at }}@if($entry->reason) · Причина: {{ $entry->reason }}@endif</p>
    <ul>@foreach($historyItems[$entry->id] ?? [] as $line)<li>{{ $line->store_name }}: {{ $line->before_kopecks === null ? 'не назначена' : number_format($line->before_kopecks / 100, 2, ',', ' ').' ₽' }} → {{ number_format($line->after_kopecks / 100, 2, ',', ' ') }} ₽</li>@endforeach</ul>
    @if($entry->kind === 'bulk' && !in_array($entry->id, $reversed))<form method="post" action="{{ route('bakery.bulk-price-reverse', [$item->id, $entry->id]) }}">@csrf<label>Причина отмены<input name="reason" required maxlength="1000"></label><button class="button secondary">Отменить всю операцию</button></form>@endif</article>
@empty<p>Истории пока нет.</p>@endforelse
</div>
<script>
const updatePricePreview = () => {
    const amount = document.getElementById('price-delta').value.replace(',', '.');
    const delta = /^\d+(?:\.\d{1,2})?$/.test(amount) ? Math.round(Number(amount) * 100) : null;
    const sign = document.getElementById('price-direction').value === 'increase' ? 1 : -1;
    const selected = [];
    let withoutPrice = 0;
    document.querySelectorAll('.price-store').forEach(box => {
        const row = box.closest('tr');
        const cell = row.querySelector('.price-preview');
        const current = cell.dataset.kopecks === '' ? null : Number(cell.dataset.kopecks);
        if (box.checked) selected.push(row.cells[1].textContent.trim());
        if (box.checked && (current === null || current <= 0)) withoutPrice++;
        const next = box.checked && current !== null && delta !== null ? current + sign * delta : null;
        cell.textContent = box.checked && (current === null || current <= 0) ? 'Нет цены' : next === null ? '—' : next > 0 ? (next / 100).toFixed(2).replace('.', ',') + ' ₽' : 'Ошибка: цена ≤ 0';
    });
    const boxes = document.querySelectorAll('.price-store');
    const selectAll = document.getElementById('price-select-all');
    selectAll.checked = boxes.length > 0 && selected.length === boxes.length;
    selectAll.indeterminate = selected.length > 0 && selected.length < boxes.length;
    selectAll.disabled = boxes.length === 0;
    document.getElementById('price-selection').textContent = selected.length ? 'Выбрано точек: ' + selected.length + ' из ' + boxes.length + (selected.length <= 5 ? ' — ' + selected.join(', ') : '') + (withoutPrice ? '. Без цены: ' + withoutPrice + '; изменение будет отклонено.' : '') : 'Точки не выбраны.';
};
document.querySelectorAll('.price-store, #price-delta, #price-direction').forEach(el => el.addEventListener('input', updatePricePreview));
document.getElementById('price-select-all').addEventListener('change', event => {
    document.querySelectorAll('.price-store').forEach(box => { box.checked = event.target.checked; });
    updatePricePreview();
});
updatePricePreview();
</script>
@endsection
