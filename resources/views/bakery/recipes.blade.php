@extends('bakery.layout')
@section('title', 'Рецептуры')
@section('content')
<div class="page-head"><div><p class="eyebrow">Панель пекарни</p><h1>Рецептуры</h1><p class="muted">Сохранённые изменения сразу применяются к заготовкам и отчёту за период.</p></div></div>
<nav class="catalog-nav" aria-label="Разделы рецептур"><a href="{{ route('bakery.recipes') }}" @if($section === 'products') aria-current="page" @endif>Изделия</a><a href="{{ route('bakery.recipes', ['section' => 'dough']) }}" @if($section === 'dough') aria-current="page" @endif>Тесто — общие нормы</a></nav>
@if($section === 'products')
<div class="panel data-panel"><form method="get" action="{{ route('bakery.recipes') }}" class="toolbar-form recipe-picker"><label><span>Изделие</span><select name="product">@foreach($products as $product)<option value="{{ $product->id }}" @selected($selected?->id === $product->id)>{{ $product->name }}</option>@endforeach</select></label><button class="button secondary">Показать</button></form></div>
@if($selected && $productRecipe)
<div class="section-head"><h2>{{ $selected->name }}</h2></div>
<div class="panel data-panel">
    <p class="muted small">Выберите тесто для изделия. Основное тесто рассчитывается по весу на 1 шт., тесто для самсы — по числу изделий на замес. Чтобы убрать ингредиент, удалите строку.</p>
    <form method="post" action="{{ route('bakery.recipes-product') }}" data-recipe-form data-mode="product">@csrf<input type="hidden" name="product" value="{{ $selected->id }}">
        @php($selectedDoughType = old('product') == $selected->id ? old('dough_type', $productRecipe['dough_type']) : $productRecipe['dough_type'])
        <div class="form-grid"><label>Вид теста<select name="dough_type" data-dough-type><option value="main" @selected($selectedDoughType === 'main')>Основное тесто</option><option value="samsa" @selected($selectedDoughType === 'samsa')>Тесто для самсы</option><option value="none" @selected($selectedDoughType === 'none')>Без теста</option></select></label><label data-main-dough-weight @if($selectedDoughType !== 'main') hidden @endif>Основное тесто на 1 шт., г<input type="number" name="dough_grams" value="{{ old('product') == $selected->id ? old('dough_grams') : $productRecipe['dough_grams'] }}" min="0.001" max="10000" step="any" @if($selectedDoughType === 'main') required @endif></label></div>
        @php($productRows = old('product') == $selected->id ? old('ingredients', $productRecipe['ingredients']) : $productRecipe['ingredients'])
        <div class="table-scroll"><table><thead><tr><th>Ингредиент</th><th>На 1 шт.</th><th>Единица</th><th></th></tr></thead><tbody data-ingredients data-next="{{ count($productRows) }}">
        @foreach($productRows as $index => $ingredient)<tr><td><input name="ingredients[{{ $index }}][name]" value="{{ $ingredient['name'] }}" maxlength="100" required></td><td><input type="number" name="ingredients[{{ $index }}][amount]" value="{{ $ingredient['amount'] }}" min="0.001" max="100000" step="any" required></td><td><select name="ingredients[{{ $index }}][unit]"><option value="g" @selected($ingredient['unit'] === 'g')>г</option><option value="pcs" @selected($ingredient['unit'] === 'pcs')>шт.</option><option value="tbsp" @selected($ingredient['unit'] === 'tbsp')>ст. л.</option></select></td><td><button type="button" class="button secondary" data-remove-row>Удалить</button></td></tr>@endforeach
        </tbody></table></div>
        <div class="actions"><button type="button" class="button secondary" data-add-row>Добавить ингредиент</button><button class="button primary">Сохранить рецептуру</button></div>
    </form>
    <form method="post" action="{{ route('bakery.recipes-reset') }}" class="actions" onsubmit="return confirm('Восстановить исходные нормы для изделия?')">@csrf<input type="hidden" name="kind" value="product"><input type="hidden" name="product" value="{{ $selected->id }}"><button class="button secondary">Вернуть исходную рецептуру</button></form>
</div>
@endif
@else
<div class="panel data-panel"><p class="muted">Эти нормы используются сразу для нескольких изделий и меняются отдельно от рецептуры выбранного товара.</p></div>
@foreach(['main_dough' => ['Основное тесто', $mainDough, 'г готового теста'], 'samsa_dough' => ['Тесто для самсы', $samsaDough, 'шт. самсы']] as $kind => [$heading, $recipe, $batchUnit])
<div class="section-head"><h2>{{ $heading }}</h2></div>
<div class="panel data-panel">
    <p class="muted small">Ингредиенты указаны на один замес. Размер замеса задаёт, на сколько {{ $batchUnit }} рассчитаны нормы.</p>
    <form method="post" action="{{ route('bakery.recipes-dough') }}" data-recipe-form data-mode="dough">@csrf<input type="hidden" name="kind" value="{{ $kind }}">
        <div class="form-grid"><label>Размер замеса, {{ $batchUnit }}<input type="number" name="batch_size" value="{{ old('kind') === $kind ? old('batch_size') : $recipe['batch_size'] }}" min="{{ $kind === 'samsa_dough' ? '1' : '0.001' }}" max="100000" step="{{ $kind === 'samsa_dough' ? '1' : 'any' }}" required></label></div>
        @php($doughRows = old('kind') === $kind ? old('ingredients', $recipe['ingredients']) : $recipe['ingredients'])
        <div class="table-scroll"><table><thead><tr><th>Ингредиент</th><th>На замес, г</th><th></th></tr></thead><tbody data-ingredients data-next="{{ count($doughRows) }}">
        @foreach($doughRows as $index => $ingredient)<tr><td><input name="ingredients[{{ $index }}][name]" value="{{ $ingredient['name'] }}" maxlength="100" required></td><td><input type="number" name="ingredients[{{ $index }}][amount]" value="{{ $ingredient['amount'] }}" min="0.001" max="100000" step="any" required></td><td><button type="button" class="button secondary" data-remove-row>Удалить</button></td></tr>@endforeach
        </tbody></table></div>
        <div class="actions"><button type="button" class="button secondary" data-add-row>Добавить ингредиент</button><button class="button primary">Сохранить тесто</button></div>
    </form>
    <form method="post" action="{{ route('bakery.recipes-reset') }}" class="actions" onsubmit="return confirm('Восстановить исходные нормы теста?')">@csrf<input type="hidden" name="kind" value="{{ $kind }}"><button class="button secondary">Вернуть исходные нормы</button></form>
</div>
@endforeach
@endif
<script>
document.querySelectorAll('[data-recipe-form]').forEach(form => {
    const doughType = form.querySelector('[data-dough-type]');
    if (doughType) {
        const weight = form.querySelector('[data-main-dough-weight]');
        const input = weight.querySelector('input');
        doughType.addEventListener('change', () => {
            weight.hidden = doughType.value !== 'main';
            input.required = doughType.value === 'main';
        });
    }
    const body = form.querySelector('[data-ingredients]');
    form.querySelector('[data-add-row]').addEventListener('click', () => {
        const index = Number(body.dataset.next);
        body.dataset.next = String(index + 1);
        const row = document.createElement('tr');
        const name = `<td><input name="ingredients[${index}][name]" maxlength="100" required></td>`;
        const amount = `<td><input type="number" name="ingredients[${index}][amount]" min="0.001" max="100000" step="any" required></td>`;
        const unit = form.dataset.mode === 'product' ? `<td><select name="ingredients[${index}][unit]"><option value="g">г</option><option value="pcs">шт.</option><option value="tbsp">ст. л.</option></select></td>` : '';
        row.innerHTML = name + amount + unit + '<td><button type="button" class="button secondary" data-remove-row>Удалить</button></td>';
        body.appendChild(row);
    });
    body.addEventListener('click', event => {
        if (event.target.closest('[data-remove-row]')) event.target.closest('tr').remove();
    });
});
</script>
@endsection
