@extends('bakery.layout')
@section('title', 'Справочники')
@section('content')
<div class="page-head"><div><p class="eyebrow">Панель пекарни</p><h1>Справочники и цены</h1><p class="muted">Магазины, аккаунты и персональный прайс.</p></div></div><nav class="admin-nav" aria-label="Разделы справочников"><a href="{{ route('bakery.admin') }}">К заказам</a><a href="{{ route('bakery.catalog', ['tab' => 'stores']) }}" @if($tab === 'stores') aria-current="page" @endif>Магазины</a><a href="{{ route('bakery.catalog', ['tab' => 'products']) }}" @if($tab === 'products') aria-current="page" @endif>Товары</a><a href="{{ route('bakery.catalog', ['tab' => 'prices', 'store' => $selectedStore]) }}" @if($tab === 'prices') aria-current="page" @endif>Цены</a><a href="{{ route('bakery.catalog', ['tab' => 'accounts']) }}" @if($tab === 'accounts') aria-current="page" @endif>Аккаунты</a></nav>
@if($tab === 'stores')
<div class="section-head"><h2>Магазины</h2></div>
<div class="panel data-panel">
    <h3>Новый магазин</h3>
    <form method="post" action="{{ route('bakery.save-store') }}" class="form-grid">@csrf<label>Название<input name="name" required></label><label>Адрес<input name="address" required></label><input type="hidden" name="is_active" value="1"><button class="button primary">Добавить</button></form>
    <div class="table-scroll"><table><thead><tr><th>Название</th><th>Адрес</th><th>Активен</th><th></th></tr></thead><tbody>
        @foreach($visibleStores as $store)
            <tr>
                <td><form id="store-{{ $store->id }}" method="post" action="{{ route('bakery.save-store') }}">@csrf<input type="hidden" name="id" value="{{ $store->id }}"></form><input form="store-{{ $store->id }}" name="name" value="{{ $store->name }}" required></td>
                <td><input form="store-{{ $store->id }}" name="address" value="{{ $store->address }}" required></td>
                <td><select form="store-{{ $store->id }}" name="is_active"><option value="1" @selected($store->is_active)>Да</option><option value="0" @selected(!$store->is_active)>Нет</option></select></td>
                <td><button form="store-{{ $store->id }}" class="button secondary">Сохранить</button></td>
            </tr>
        @endforeach
    </tbody></table></div>
</div>
@include('bakery.pagination', ['url' => route('bakery.catalog'), 'query' => ['tab' => 'stores']])
@elseif($tab === 'products')
<div class="section-head"><h2>Товары</h2></div>
<div class="panel data-panel"><h3>Новый товар</h3>
    <form method="post" action="{{ route('bakery.save-product') }}" class="form-grid">@csrf<label>Название<input name="name" required></label><label>Порядок<input type="number" name="sort_order" min="0" value="0" required></label><label>Минимум, шт.<input type="number" name="minimum_quantity" min="1" max="100000" value="1" required></label><label>Фасовка<select name="packaging_mode"><option value="mixed">Пакеты по 3 и 5</option><option value="single">По одной штуке</option><option value="pair">По две штуки</option></select></label><input type="hidden" name="is_active" value="1"><button class="button primary">Добавить</button></form>
    <div class="table-scroll"><table><thead><tr><th>Название</th><th>Порядок</th><th>Минимум, шт.</th><th>Фасовка</th><th>Активен</th><th></th></tr></thead><tbody>
        @foreach($products as $product)
            <tr>
                <td><form id="product-{{ $product->id }}" method="post" action="{{ route('bakery.save-product') }}">@csrf<input type="hidden" name="id" value="{{ $product->id }}"></form><input form="product-{{ $product->id }}" name="name" value="{{ $product->name }}" required></td>
                <td><input form="product-{{ $product->id }}" type="number" name="sort_order" value="{{ $product->sort_order }}" min="0" required></td>
                <td><input form="product-{{ $product->id }}" type="number" name="minimum_quantity" value="{{ $product->minimum_quantity }}" min="1" max="100000" required></td>
                <td><select form="product-{{ $product->id }}" name="packaging_mode"><option value="mixed" @selected($product->packaging_mode === 'mixed')>Пакеты по 3 и 5</option><option value="single" @selected($product->packaging_mode === 'single')>По одной штуке</option><option value="pair" @selected($product->packaging_mode === 'pair')>По две штуки</option></select></td>
                <td><select form="product-{{ $product->id }}" name="is_active"><option value="1" @selected($product->is_active)>Да</option><option value="0" @selected(!$product->is_active)>Нет</option></select></td>
                <td><button form="product-{{ $product->id }}" class="button secondary">Сохранить</button></td>
            </tr>
        @endforeach
    </tbody></table></div>
</div>
@elseif($tab === 'prices')
<p class="muted small">Если магазин входит в точку с общим прайсом, цены берутся из первого магазина этой точки. Изменение цены обновляет общий прайс.</p>
<div class="section-head"><h2>Персональные цены и минимумы</h2></div><div class="panel data-panel"><form method="get" action="{{ route('bakery.catalog') }}" class="form-grid"><input type="hidden" name="tab" value="prices"><label>Магазин<select name="store">@foreach($stores as $store)<option value="{{ $store->id }}" @selected($selectedStore===$store->id)>{{ $store->name }} — {{ $store->address }}</option>@endforeach</select></label><button class="button secondary">Выбрать</button></form><p class="muted small">Пустой минимум для магазина означает общий минимум товара из вкладки «Товары».</p><div class="table-scroll"><table><thead><tr><th>Товар</th><th>Цена, ₽</th><th></th><th>Минимум для магазина, шт.</th><th></th></tr></thead><tbody>@foreach($products as $product)<tr><td>{{ $product->name }}</td><td><form id="price-{{ $product->id }}" method="post" action="{{ route('bakery.save-price') }}">@csrf<input type="hidden" name="store" value="{{ $selectedStore }}"><input type="hidden" name="product" value="{{ $product->id }}"><input name="rubles" inputmode="decimal" value="{{ isset($prices[$product->id]) ? intdiv($prices[$product->id],100).','.str_pad($prices[$product->id]%100,2,'0',STR_PAD_LEFT) : '' }}" placeholder="Не назначена" required></form></td><td><button form="price-{{ $product->id }}" class="button secondary">Сохранить цену</button></td><td><form id="minimum-{{ $product->id }}" method="post" action="{{ route('bakery.save-minimum') }}">@csrf<input type="hidden" name="store" value="{{ $selectedStore }}"><input type="hidden" name="product" value="{{ $product->id }}"><input type="number" name="minimum_quantity" min="1" max="100000" value="{{ $minimums[$product->id] ?? '' }}" placeholder="Общий: {{ $product->minimum_quantity }}"></form></td><td><button form="minimum-{{ $product->id }}" class="button secondary">Сохранить минимум</button></td></tr>@endforeach</tbody></table></div></div>
@else
<p class="muted small">Логин — адрес электронной почты; его и пароль можно изменить здесь. Для нескольких магазинов общий прайс берётся из верхнего выбранного магазина в списке.</p>
<div class="section-head"><h2>Аккаунты магазинов</h2></div>
<div class="panel data-panel">
    <p class="muted small">Отметьте все магазины, доступные аккаунту. Каждый магазин оформляет свой заказ отдельно.</p>
    <h3>Новый аккаунт</h3>
    <form method="post" action="{{ route('bakery.save-account') }}" class="form-grid">@csrf
        <fieldset class="store-choices"><legend>Магазины</legend>@foreach($stores as $store)<label><input type="checkbox" name="stores[]" value="{{ $store->id }}">{{ $store->name }}@if($store->address !== $store->name) — {{ $store->address }}@endif</label>@endforeach</fieldset>
        <label>Имя<input name="name" required></label><label>Почта<input type="email" name="email" required></label><label>Пароль<input type="password" name="password" minlength="10" required></label>
        <input type="hidden" name="is_active" value="1"><button class="button primary">Создать</button>
    </form>
    <div class="table-scroll"><table><thead><tr><th>Почта</th><th>Назначенные магазины</th><th>Активен</th><th>Новый пароль</th><th></th></tr></thead><tbody>
        @foreach($visibleAccounts as $account)
            <tr>
                <td><form id="account-{{ $account['id'] }}" method="post" action="{{ route('bakery.save-account') }}">@csrf<input type="hidden" name="id" value="{{ $account['id'] }}"><input type="hidden" name="name" value="{{ $account['name'] }}"></form><input form="account-{{ $account['id'] }}" type="email" name="email" value="{{ $account['email'] }}" required></td>
                <td><strong>{{ $stores->whereIn('id', $account['store_ids'])->pluck('name')->implode(', ') }}</strong><details><summary>Изменить привязку</summary><fieldset class="store-choices">@foreach($stores as $store)<label><input form="account-{{ $account['id'] }}" type="checkbox" name="stores[]" value="{{ $store->id }}" @checked(in_array($store->id, $account['store_ids'], true))>{{ $store->name }}@if($store->address !== $store->name) — {{ $store->address }}@endif</label>@endforeach</fieldset></details></td>
                <td><select form="account-{{ $account['id'] }}" name="is_active"><option value="1" @selected($account['status']==='verify')>Да</option><option value="0" @selected($account['status']!=='verify')>Нет</option></select></td>
                <td><input form="account-{{ $account['id'] }}" type="password" name="password" placeholder="Без изменения" minlength="10"></td>
                <td><button form="account-{{ $account['id'] }}" class="button secondary">Сохранить</button></td>
            </tr>
        @endforeach
    </tbody></table></div>
</div>
@include('bakery.pagination', ['url' => route('bakery.catalog'), 'query' => ['tab' => 'accounts']])
@endif
@endsection
