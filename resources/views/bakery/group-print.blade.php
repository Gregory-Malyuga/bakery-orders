<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Сводная накладная группы</title><link rel="stylesheet" href="/bakery.css?v={{ filemtime(public_path('bakery.css')) }}"></head>
<body><main class="wrap">
    <div class="no-print actions print-actions"><a class="button secondary" href="{{ $downloadUrl }}">Скачать и отредактировать в Excel</a><button class="button primary" onclick="window.print()">Печать</button></div>
    @include('bakery.group-invoice')
</main></body></html>
