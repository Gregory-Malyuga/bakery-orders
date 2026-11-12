@extends('bakery.layout')
@section('title', 'Маршрутные листы')
@section('content')
<div class="page-head"><div><p class="eyebrow">Панель пекарни</p><h1>Маршрутные листы</h1><p class="muted">Выберите точки из общего списка и перетащите их в нужный порядок. Печать доступна после времени отсечки накануне поставки.</p></div></div>

<div class="panel data-panel"><h2>Составить разовый лист</h2>
<form method="post" action="{{ route('bakery.delivery-sheets-create') }}" class="route-form">@csrf
<div class="form-grid"><label>Дата поставки<input type="date" name="date" value="{{ $date }}" required></label><label>Водитель<input name="driver" required maxlength="255"></label></div>
<div class="route-picker"><div><h3>Общий список точек</h3><div class="route-pool"></div></div><div><h3>Выбранные точки · порядок остановок</h3><ol class="route-selected"></ol></div></div>
<label><input type="checkbox" name="confirm_overlap" value="1"> Я проверил совпадение точек с уже сохранёнными листами этой даты</label><br><button class="button secondary" type="submit">Печатать без сохранения</button> <button class="button primary" name="save" value="1">Сохранить и печатать</button>
</form></div>

<div class="panel data-panel"><h2>Новый шаблон</h2>
<form method="post" action="{{ route('bakery.delivery-routes-save') }}" class="route-form">@csrf
<div class="form-grid"><label>Название<input name="name" required maxlength="255"></label><label>Водитель<input name="driver" required maxlength="255"></label></div>
<div class="route-picker"><div><h3>Общий список точек</h3><div class="route-pool"></div></div><div><h3>Выбранные точки · порядок остановок</h3><ol class="route-selected"></ol></div></div>
<button class="button primary">Сохранить шаблон</button></form></div>

@foreach($routes as $route)
<div class="panel data-panel"><h2>{{ $route->name }}</h2>
<form method="post" action="{{ route('bakery.delivery-routes-save') }}" class="route-form" data-selected='@json(json_decode($route->store_ids, true))'>@csrf<input type="hidden" name="id" value="{{ $route->id }}">
<div class="form-grid"><label>Название<input name="name" value="{{ $route->name }}" required maxlength="255"></label><label>Водитель<input name="driver" value="{{ $route->driver }}" required maxlength="255"></label></div>
<div class="route-picker"><div><h3>Общий список точек</h3><div class="route-pool"></div></div><div><h3>Выбранные точки · порядок остановок</h3><ol class="route-selected"></ol></div></div><button class="button secondary">Сохранить изменения</button></form>
<form method="post" action="{{ route('bakery.delivery-sheets-create') }}">@csrf<input type="hidden" name="route_id" value="{{ $route->id }}"><label>Дата поставки<input type="date" name="date" value="{{ $date }}" required></label><label><input type="checkbox" name="confirm_overlap" value="1"> Я проверил совпадение точек с уже сохранёнными листами этой даты</label><br><button class="button secondary">Печатать без сохранения</button> <button class="button primary" name="save" value="1">Сохранить и печатать</button></form>
</div>
@endforeach

<div class="panel data-panel"><h2>Сохранённые листы</h2><ul>@forelse($sheets as $saved)@php($snapshot = json_decode($saved->snapshot, true))<li><a href="{{ route('bakery.delivery-sheet', $saved->id) }}">{{ \Illuminate\Support\Carbon::parse($saved->delivery_date)->format('d.m.Y') }} · {{ $snapshot['route'] }} · {{ $snapshot['driver'] }}</a></li>@empty<li>Пока нет листов.</li>@endforelse</ul></div>

<script>
const routeStores = @json($stores->map(fn ($store) => ['id' => $store->id, 'name' => $store->name, 'address' => $store->address])->values());
document.querySelectorAll('.route-form').forEach(form => {
    let ids = JSON.parse(form.dataset.selected || '[]').map(Number);
    const pool = form.querySelector('.route-pool'), selected = form.querySelector('.route-selected');
    let dragged = null;
    function render() {
        pool.replaceChildren(); selected.replaceChildren();
        routeStores.filter(store => !ids.includes(store.id)).forEach(store => {
            const button = document.createElement('button'); button.type = 'button'; button.className = 'button secondary'; button.textContent = store.name + ' · ' + store.address;
            button.onclick = () => { ids.push(store.id); render(); }; button.draggable = true; button.ondragstart = () => { dragged = store.id; }; pool.append(button);
        });
        ids.forEach((id, index) => {
            const store = routeStores.find(item => item.id === id); if (!store) return;
            const item = document.createElement('li'); item.draggable = true; item.textContent = store.name + ' · ' + store.address + ' ';
            const input = document.createElement('input'); input.type = 'hidden'; input.name = 'stores[]'; input.value = id; item.append(input);
            const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'button secondary'; remove.textContent = 'Убрать'; remove.onclick = () => { ids.splice(index, 1); render(); }; item.append(remove);
            item.ondragstart = () => { dragged = id; }; item.ondragover = event => event.preventDefault(); item.ondrop = event => { event.preventDefault(); if (dragged === id) return; const from = ids.indexOf(dragged); if (from >= 0) ids.splice(from, 1); ids.splice(ids.indexOf(id), 0, dragged); render(); };
            selected.append(item);
        });
    }
    selected.ondragover = event => event.preventDefault(); selected.ondrop = event => { event.preventDefault(); if (dragged !== null && !ids.includes(dragged)) { ids.push(dragged); render(); } };
    render();
});
</script>
<style>.route-picker{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin:1rem 0}.route-pool{display:flex;flex-direction:column;align-items:flex-start;gap:.4rem;max-height:18rem;overflow:auto}.route-selected li{padding:.4rem;cursor:move}.route-selected button{margin-left:.5rem}@media(max-width:700px){.route-picker{grid-template-columns:1fr}}</style>
@endsection
