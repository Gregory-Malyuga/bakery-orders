@extends('bakery.layout')
@section('title', 'Вход')
@section('content')
<div class="login-shell"><div class="login-intro"><p class="eyebrow">Ежедневные заказы</p><h1>Свежая выпечка<br>к завтрашнему дню.</h1><p>Войдите, чтобы оформить заказ для своей торговой точки.</p></div><form method="post" action="/login" class="panel login-panel">@csrf<h2>Вход в кабинет</h2><label>Электронная почта<input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></label><label>Пароль<input type="password" name="password" autocomplete="current-password" required></label><button class="button primary wide">Войти</button><p class="muted small">Доступ выдаёт администратор пекарни.</p></form></div>
@endsection
