<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Пекарня') · Заказы</title>
    <link rel="stylesheet" href="/bakery.css">
</head>
<body>
<header class="topbar"><div class="wrap topbar-inner"><a class="brand" href="{{ auth()->check() ? (auth()->user()->role->value === 'admin' ? route('bakery.admin') : route('bakery.store')) : route('bakery.login') }}">✳ Пекарня <span>заказы</span></a>@auth<form method="post" action="{{ route('bakery.logout') }}">@csrf<button class="link-button">Выйти</button></form>@endauth</div></header>
<main class="wrap">
    @if(session()->has('bakery.impersonating_admin_id'))
        <div class="notice impersonation-notice"><strong>Режим пользователя: {{ auth()->user()->name }} ({{ auth()->user()->email }})</strong><form method="post" action="{{ route('bakery.impersonation-stop') }}">@csrf<button class="button secondary">Вернуться в админку</button></form></div>
    @endif
    @if(session('success'))<div class="notice success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="notice error"><strong>Проверьте данные:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
</body></html>
