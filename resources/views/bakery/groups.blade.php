@extends('bakery.layout')
@section('title', 'Группы точек')
@section('content')
<div class="page-head"><div><p class="eyebrow">Панель пекарни</p><h1>Группы точек</h1><p class="muted">Одна точка может входить в несколько групп. Группы не создают новые заказы.</p></div></div>

<div class="panel data-panel">
    <h2>Новая группа</h2>
    <form class="group-editor" method="post" action="{{ route('bakery.groups-save') }}">
        @csrf
        <label>Название<input name="name" value="{{ old('name') }}" maxlength="255" required></label>
        <label class="group-option"><input type="checkbox" name="show_in_invoice" value="1"> Отображать группу в накладной</label>
        <label class="group-option"><input type="checkbox" name="exclude_from_labels" value="1"> Исключить точки из расчёта этикеток</label>
        <details><summary>Состав группы — выбрать точки</summary>
            <div class="group-store-grid">
                @foreach($stores as $store)
                    <label class="group-store-option"><input type="checkbox" name="stores[]" value="{{ $store->id }}"> {{ $store->name }} · {{ $store->address }}</label>
                @endforeach
            </div>
        </details>
        <button class="button primary">Создать группу</button>
    </form>
</div>

@foreach($groups as $group)
    <div class="panel data-panel">
        <h2>{{ $group->name }}</h2>
        <form class="group-editor" method="post" action="{{ route('bakery.groups-save') }}">
            @csrf
            <input type="hidden" name="id" value="{{ $group->id }}">
            <label>Название<input name="name" value="{{ $group->name }}" maxlength="255" required></label>
            <label class="group-option"><input type="checkbox" name="show_in_invoice" value="1" @checked($group->show_in_invoice)> Отображать группу в накладной</label>
            <label class="group-option"><input type="checkbox" name="exclude_from_labels" value="1" @checked($group->exclude_from_labels)> Исключить точки из расчёта этикеток</label>
            <details><summary>Состав группы · {{ ($members[$group->id] ?? collect())->count() }} точек</summary>
                <div class="group-store-grid">
                    @foreach($stores as $store)
                        <label class="group-store-option"><input type="checkbox" name="stores[]" value="{{ $store->id }}" @checked(($members[$group->id] ?? collect())->contains('bakery_id', $store->id))> {{ $store->name }} · {{ $store->address }}</label>
                    @endforeach
                </div>
            </details>
            <button class="button secondary">Сохранить изменения</button>
        </form>
    </div>
@endforeach
@endsection
