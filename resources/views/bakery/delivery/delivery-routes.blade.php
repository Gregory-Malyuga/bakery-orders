@extends('bakery.layout')
@section('title', 'Маршрутные листы')
@section('content')
<div class="page-head"><div><p class="eyebrow">Панель пекарни</p><h1>Маршрутные листы</h1><p class="muted">Выберите точки из общего списка и перетащите их в нужный порядок. До отсечки сохранённый лист печатается на сегодня, после отсечки — на завтра.</p></div></div>

<div class="panel data-panel"><h2>Новый маршрутный лист</h2>
@php($selectedStores = old('stores', $copySnapshot ? array_column($copySnapshot['rows'], 'store_id') : []))
<form method="post" action="{{ route('bakery.delivery-sheets-create') }}" class="route-form" data-selected='@json($selectedStores)'>@csrf
<label class="route-copy-field">Создать на основе сохранённого листа<select id="route-copy-select" data-url="{{ route('bakery.delivery-routes') }}"><option value="">Без основы</option>@foreach($savedSheets as $saved)<option value="{{ $saved->id }}" @selected($copyId === (int) $saved->id)>{{ $saved->label }}</option>@endforeach</select></label>
<div class="form-grid"><label>Дата поставки<input type="date" name="date" value="{{ old('date', $date) }}" required></label><label>Название маршрута<input name="name" value="{{ old('name', $copySnapshot['route'] ?? 'Новый маршрут') }}" required maxlength="255"></label><label>Водитель<input name="driver" value="{{ old('driver', $copySnapshot['driver'] ?? '') }}" required maxlength="255"></label><label>Зарплата водителя, ₽<input name="salary" value="{{ old('salary', isset($copySnapshot['salary_kopecks']) ? number_format($copySnapshot['salary_kopecks'] / 100, 2, '.', '') : '') }}" inputmode="decimal"></label><label>Бонус водителя, ₽<input name="bonus" value="{{ old('bonus', isset($copySnapshot['bonus_kopecks']) ? number_format($copySnapshot['bonus_kopecks'] / 100, 2, '.', '') : '') }}" inputmode="decimal"></label></div>
@include('bakery.delivery.route-picker')
<div class="route-form-actions"><button class="button primary">Сохранить лист</button></div>
</form></div>

<div class="panel data-panel"><h2>Сохранённые листы</h2><ul class="saved-sheet-list">@forelse($savedSheets as $saved)<li><a href="{{ route('bakery.delivery-sheet', $saved->id) }}">{{ $saved->label }}</a><span class="saved-sheet-actions"><a class="button secondary" href="{{ route('bakery.delivery-sheet-edit', $saved->id) }}">Редактировать</a><a class="button secondary" href="{{ route('bakery.delivery-routes', ['copy' => $saved->id]) }}">Дублировать</a><form method="post" action="{{ route('bakery.delivery-sheet-delete', $saved->id) }}" onsubmit="return confirm('Удалить сохранённый лист? История печати останется.')">@csrf<button class="button danger">Удалить</button></form></span></li>@empty<li>Пока нет листов.</li>@endforelse</ul></div>
<div class="panel data-panel"><h2>История печати</h2><p class="muted small">Здесь хранится состав каждого отправленного на печать листа. Записи доступны только для просмотра и не используются как шаблоны.</p><ul class="saved-sheet-list">@forelse($printedSheets as $printed)<li><a href="{{ route('bakery.delivery-sheet-print-record', $printed->id) }}">{{ $printed->label }}</a><span class="muted small">{{ $printed->summary }}</span></li>@empty<li>Печати пока не было.</li>@endforelse</ul></div>

@include('bakery.delivery.route-picker-script')
@endsection
