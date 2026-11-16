@extends('bakery.layout')
@section('title', 'Редактировать маршрутный лист')
@section('content')
<div class="page-head"><div><p class="eyebrow">Панель пекарни</p><h1>Редактировать маршрутный лист</h1><p class="muted">После сохранения суммы по точкам будут пересчитаны по текущим заказам и действующим возвратам.</p></div></div>
<div class="panel data-panel">
@php($selectedStores = old('stores', array_column($sheet['rows'], 'store_id')))
<form method="post" action="{{ route('bakery.delivery-sheet-update', $sheetId) }}" class="route-form" data-sheet-id="{{ $sheetId }}" data-selected='@json($selectedStores)'>@csrf
<div class="form-grid"><label>Дата поставки<input type="date" name="date" value="{{ old('date', $sheet['date']) }}" required></label><label>Название маршрута<input name="name" value="{{ old('name', $sheet['route']) }}" maxlength="255" required></label><label>Водитель<input name="driver" value="{{ old('driver', $sheet['driver']) }}" maxlength="255" required></label><label>Зарплата водителя, ₽<input name="salary" value="{{ old('salary', isset($sheet['salary_kopecks']) ? number_format($sheet['salary_kopecks'] / 100, 2, '.', '') : '') }}" inputmode="decimal"></label><label>Бонус водителя, ₽<input name="bonus" value="{{ old('bonus', isset($sheet['bonus_kopecks']) ? number_format($sheet['bonus_kopecks'] / 100, 2, '.', '') : '') }}" inputmode="decimal"></label></div>
@include('bakery.route-picker')
<div class="route-form-actions"><a class="button secondary" href="{{ route('bakery.delivery-sheet', $sheetId) }}">Отмена</a><button class="button primary">Сохранить изменения</button></div>
</form></div>
@include('bakery.route-picker-script')
@endsection
