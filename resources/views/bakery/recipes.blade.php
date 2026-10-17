@extends('bakery.layout')
@section('title', 'Рецептуры')
@section('content')
<div class="page-head"><div><p class="eyebrow">Панель пекарни</p><h1>Рецептуры</h1><p class="muted">Сохранённые изменения сразу применяются к заготовкам и отчёту за период.</p></div></div>
<nav class="catalog-nav" aria-label="Разделы рецептур"><a href="{{ route('bakery.recipes') }}" @if($section === 'products') aria-current="page" @endif>Изделия</a><a href="{{ route('bakery.recipes', ['section' => 'dough']) }}" @if($section === 'dough') aria-current="page" @endif>Тесто — общие нормы</a><a href="{{ route('bakery.ingredients') }}">Справочник ингредиентов</a></nav>
@php($ingredientUnits = ['g' => 'г', 'pcs' => 'шт.', 'tbsp' => 'ст. л.'])
@if($section === 'products')
<div class="panel data-panel"><form method="get" action="{{ route('bakery.recipes') }}" class="toolbar-form recipe-picker"><label><span>Изделие</span><select name="product">@foreach($products as $product)<option value="{{ $product->id }}" @selected($selected?->id === $product->id)>{{ $product->name }}</option>@endforeach</select></label><button class="button secondary">Показать</button></form></div>
@if($selected && $productRecipe)
<div class="section-head"><h2>{{ $selected->name }}</h2></div>
<div class="panel data-panel">
    <p class="muted small">Тесто — ингредиент изделия. Основное тесто укажите в граммах на 1 шт.; тесто для самсы считается как 1 порция на изделие. Состав и размер замеса задаются в разделе «Тесто — общие нормы».</p>
    <form method="post" action="{{ route('bakery.recipes-product') }}" data-recipe-form data-mode="product">@csrf<input type="hidden" name="product" value="{{ $selected->id }}">
        @php($selectedDoughType = old('product') == $selected->id ? old('dough_type', $productRecipe['dough_type']) : $productRecipe['dough_type'])
        @php($productRows = old('product') == $selected->id ? old('ingredients', $productRecipe['ingredients']) : $productRecipe['ingredients'])
        <div class="table-scroll"><table class="recipe-ingredients"><thead><tr><th>Ингредиент</th><th>На 1 шт.</th><th>Единица</th><th></th></tr></thead>
        <tbody><tr class="recipe-dough-row"><td><select name="dough_type" data-dough-type aria-label="Тесто для изделия"><option value="main" @selected($selectedDoughType === 'main')>Основное тесто</option><option value="samsa" @selected($selectedDoughType === 'samsa')>Тесто для самсы</option><option value="none" @selected($selectedDoughType === 'none')>Без теста</option></select></td><td><input type="number" name="dough_grams" data-main-dough-weight value="{{ old('product') == $selected->id ? old('dough_grams') : $productRecipe['dough_grams'] }}" min="0.001" max="10000" step="any" aria-label="Вес основного теста на одно изделие" @if($selectedDoughType !== 'main') hidden @endif @if($selectedDoughType === 'main') required @endif><span data-samsa-dough-amount @if($selectedDoughType !== 'samsa') hidden @endif>1</span><span data-no-dough-amount @if($selectedDoughType !== 'none') hidden @endif>—</span></td><td><span data-main-dough-unit @if($selectedDoughType !== 'main') hidden @endif>г</span><span data-samsa-dough-unit @if($selectedDoughType !== 'samsa') hidden @endif>шт.</span><span data-no-dough-unit @if($selectedDoughType !== 'none') hidden @endif>—</span></td><td></td></tr></tbody>
        <tbody data-ingredients data-next="{{ count($productRows) }}">
        @foreach($productRows as $index => $ingredient)<tr><td><select name="ingredients[{{ $index }}][ingredient_id]" data-ingredient-select aria-label="Ингредиент" required><option value="">Выберите ингредиент</option>@foreach($ingredientOptions as $option)@if($option->is_active || $option->id == ($ingredient['ingredient_id'] ?? null))<option value="{{ $option->id }}" data-unit="{{ $option->unit }}" @selected($option->id == ($ingredient['ingredient_id'] ?? null))>{{ $option->name }} ({{ $ingredientUnits[$option->unit] }}){{ $option->is_active ? '' : ' — скрыт' }}</option>@endif @endforeach</select></td><td><input type="number" name="ingredients[{{ $index }}][amount]" value="{{ $ingredient['amount'] }}" min="0.001" max="100000" step="any" required></td><td data-ingredient-unit>{{ $ingredientUnits[$ingredient['unit']] }}</td><td><button type="button" class="button secondary" data-remove-row>Удалить</button></td></tr>@endforeach
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
        @foreach($doughRows as $index => $ingredient)<tr><td><select name="ingredients[{{ $index }}][ingredient_id]" data-ingredient-select aria-label="Ингредиент" required><option value="">Выберите ингредиент</option>@foreach($ingredientOptions as $option)@if(($option->is_active && $option->unit === 'g') || $option->id == ($ingredient['ingredient_id'] ?? null))<option value="{{ $option->id }}" data-unit="{{ $option->unit }}" @selected($option->id == ($ingredient['ingredient_id'] ?? null))>{{ $option->name }} ({{ $ingredientUnits[$option->unit] }}){{ $option->is_active ? '' : ' — скрыт' }}</option>@endif @endforeach</select></td><td><input type="number" name="ingredients[{{ $index }}][amount]" value="{{ $ingredient['amount'] }}" min="0.001" max="100000" step="any" required></td><td><button type="button" class="button secondary" data-remove-row>Удалить</button></td></tr>@endforeach
        </tbody></table></div>
        <div class="actions"><button type="button" class="button secondary" data-add-row>Добавить ингредиент</button><button class="button primary">Сохранить тесто</button></div>
    </form>
    <form method="post" action="{{ route('bakery.recipes-reset') }}" class="actions" onsubmit="return confirm('Восстановить исходные нормы теста?')">@csrf<input type="hidden" name="kind" value="{{ $kind }}"><button class="button secondary">Вернуть исходные нормы</button></form>
</div>
@endforeach
@endif
<script>
const ingredientOptions = @json($activeIngredientOptions);
const ingredientUnits = {g: 'г', pcs: 'шт.', tbsp: 'ст. л.'};
document.querySelectorAll('[data-recipe-form]').forEach(form => {
    const doughType = form.querySelector('[data-dough-type]');
    if (doughType) {
        const weight = form.querySelector('[data-main-dough-weight]');
        const toggleDough = () => {
            weight.hidden = doughType.value !== 'main';
            weight.required = doughType.value === 'main';
            form.querySelectorAll('[data-samsa-dough-amount], [data-samsa-dough-unit]').forEach(element => element.hidden = doughType.value !== 'samsa');
            form.querySelectorAll('[data-no-dough-amount], [data-no-dough-unit]').forEach(element => element.hidden = doughType.value !== 'none');
            form.querySelector('[data-main-dough-unit]').hidden = doughType.value !== 'main';
        };
        doughType.addEventListener('change', toggleDough);
    }
    const body = form.querySelector('[data-ingredients]');
    form.querySelector('[data-add-row]').addEventListener('click', () => {
        const index = Number(body.dataset.next);
        body.dataset.next = String(index + 1);
        const row = document.createElement('tr');
        const selectionCell = document.createElement('td');
        const select = document.createElement('select');
        select.name = `ingredients[${index}][ingredient_id]`;
        select.dataset.ingredientSelect = '';
        select.required = true;
        select.add(new Option('Выберите ингредиент', ''));
        ingredientOptions.filter(item => form.dataset.mode === 'product' || item.unit === 'g').forEach(item => {
            const option = new Option(`${item.name} (${ingredientUnits[item.unit]})`, item.id);
            option.dataset.unit = item.unit;
            select.add(option);
        });
        selectionCell.appendChild(select);
        row.appendChild(selectionCell);
        const amountCell = document.createElement('td');
        amountCell.innerHTML = `<input type="number" name="ingredients[${index}][amount]" min="0.001" max="100000" step="any" required>`;
        row.appendChild(amountCell);
        if (form.dataset.mode === 'product') {
            const unitCell = document.createElement('td');
            unitCell.dataset.ingredientUnit = '';
            row.appendChild(unitCell);
        }
        const removeCell = document.createElement('td');
        removeCell.innerHTML = '<button type="button" class="button secondary" data-remove-row>Удалить</button>';
        row.appendChild(removeCell);
        body.appendChild(row);
    });
    body.addEventListener('change', event => {
        if (!event.target.matches('[data-ingredient-select]')) return;
        const unitCell = event.target.closest('tr').querySelector('[data-ingredient-unit]');
        if (unitCell) unitCell.textContent = ingredientUnits[event.target.selectedOptions[0]?.dataset.unit] || '';
    });
    body.addEventListener('click', event => {
        if (event.target.closest('[data-remove-row]')) event.target.closest('tr').remove();
    });
});
</script>
@endsection
