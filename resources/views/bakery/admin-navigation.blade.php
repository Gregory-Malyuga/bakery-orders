@php
    $adminTab = request()->routeIs('bakery.admin') ? ($tab ?? 'orders') : 'orders';
    $dateQuery = isset($date) ? ['date' => $date] : (request()->filled('date') ? ['date' => request()->query('date')] : []);
@endphp
<nav class="admin-nav" aria-label="Разделы пекарни">
    <div class="wrap admin-nav-inner">
        <a href="{{ route('bakery.admin', $dateQuery + ['tab' => 'orders']) }}" @if(request()->routeIs('bakery.admin*') && $adminTab === 'orders') aria-current="page" @endif>Заказы</a>
        <a href="{{ route('bakery.admin', $dateQuery + ['tab' => 'production']) }}" @if(request()->routeIs('bakery.admin') && $adminTab === 'production') aria-current="page" @endif>Выпуск</a>
        <a href="{{ route('bakery.admin', $dateQuery + ['tab' => 'packaging']) }}" @if(request()->routeIs('bakery.admin') && $adminTab === 'packaging') aria-current="page" @endif>Фасовка</a>
        <a href="{{ route('bakery.admin', $dateQuery + ['tab' => 'labels']) }}" @if(request()->routeIs('bakery.admin') && $adminTab === 'labels') aria-current="page" @endif>Наклейки</a>
        <a href="{{ route('bakery.admin', $dateQuery + ['tab' => 'prep']) }}" @if(request()->routeIs('bakery.admin') && $adminTab === 'prep') aria-current="page" @endif>Заготовки</a>
        <a href="{{ route('bakery.recipes') }}" @if(request()->routeIs('bakery.recipes*')) aria-current="page" @endif>Рецептуры</a>
        <a href="{{ route('bakery.ingredients') }}" @if(request()->routeIs('bakery.ingredients*')) aria-current="page" @endif>Ингредиенты</a>
        <a href="{{ route('bakery.report') }}" @if(request()->routeIs('bakery.report')) aria-current="page" @endif>Отчёт</a>
        <a href="{{ route('bakery.catalog') }}" @if(request()->routeIs('bakery.catalog')) aria-current="page" @endif>Справочники</a>
    </div>
</nav>
