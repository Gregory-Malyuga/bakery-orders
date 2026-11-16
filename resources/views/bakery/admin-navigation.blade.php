@php
    $adminTab = request()->routeIs('bakery.admin') ? ($tab ?? 'orders') : 'orders';
    $candidateDate = isset($date) ? $date : request()->query('date');
    $dateQuery = is_string($candidateDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidateDate) ? ['date' => $candidateDate] : [];
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
        <a href="{{ route('bakery.groups') }}" @if(request()->routeIs('bakery.groups*')) aria-current="page" @endif>Группы точек</a>
        <a href="{{ route('bakery.delivery-routes') }}" @if(request()->routeIs('bakery.delivery-*')) aria-current="page" @endif>Маршрутные листы</a>
        <a href="{{ route('bakery.closed-dates') }}" @if(request()->routeIs('bakery.closed-dates*')) aria-current="page" @endif>Нерабочие даты</a>
        <a href="{{ route('bakery.stock') }}" @if(request()->routeIs('bakery.stock*')) aria-current="page" @endif>Склад</a>
        <a href="{{ route('bakery.analytics') }}" @if(request()->routeIs('bakery.analytics*')) aria-current="page" @endif>Аналитика</a>
        <a href="{{ route('bakery.catalog') }}" @if(request()->routeIs('bakery.catalog')) aria-current="page" @endif>Справочники</a>
    </div>
</nav>
