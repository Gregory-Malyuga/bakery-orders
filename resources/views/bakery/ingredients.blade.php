@extends('bakery.layout')
@section('title', 'Ингредиенты')
@section('content')
<div class="page-head"><div><p class="eyebrow">Панель пекарни</p><h1>Ингредиенты</h1><p class="muted">Один справочник для рецептур, заготовок, отчёта и Excel. Единица измерения закреплена за ингредиентом.</p></div></div>
<div class="panel data-panel">
    <h2>Добавить ингредиент</h2>
    <form method="post" action="{{ route('bakery.ingredients-create') }}" class="form-grid ingredient-create">@csrf
        <label>Название<input name="name" value="{{ old('name') }}" maxlength="100" required></label>
        <label>Единица<select name="unit"><option value="g" @selected(old('unit') === 'g')>г</option><option value="pcs" @selected(old('unit') === 'pcs')>шт.</option><option value="tbsp" @selected(old('unit') === 'tbsp')>ст. л.</option></select></label>
        <button class="button primary">Добавить</button>
    </form>
</div>
<div class="section-head"><h2>Список ингредиентов</h2></div>
<div class="panel data-panel">
    <p class="muted small">«Скрыть» убирает ингредиент из выбора для новых строк. В существующих рецептурах и расчётах он остаётся; чтобы исключить его из расхода, удалите строку рецептуры.</p>
    <div class="table-scroll"><table class="ingredient-catalog-table"><thead><tr><th>Название</th><th>Единица</th><th>Статус</th><th>Действия</th></tr></thead><tbody>
    @foreach($ingredients as $ingredient)
    <tr><td><form id="ingredient-rename-{{ $ingredient->id }}" method="post" action="{{ route('bakery.ingredients-rename', $ingredient->id) }}">@csrf<input name="name" value="{{ $ingredient->name }}" maxlength="100" aria-label="Название ингредиента" required></form></td><td>{{ ['g' => 'г', 'pcs' => 'шт.', 'tbsp' => 'ст. л.'][$ingredient->unit] }}</td><td>{{ $ingredient->is_active ? 'Доступен' : 'Скрыт' }}</td><td><div class="ingredient-actions"><button form="ingredient-rename-{{ $ingredient->id }}" class="button secondary">Сохранить</button><form method="post" action="{{ route('bakery.ingredients-toggle', $ingredient->id) }}">@csrf<button class="button secondary">{{ $ingredient->is_active ? 'Скрыть' : 'Вернуть' }}</button></form></div></td></tr>
    @endforeach
    </tbody></table></div>
</div>
@endsection
