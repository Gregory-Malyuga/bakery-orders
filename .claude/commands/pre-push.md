# /pre-push

Полный quality gate. Запустить перед каждым пушем.

## Команда

```bash
docker compose exec -T php sh -c "cd /var/www/app && composer pre-push"
```

`composer pre-push` должен включать (в `composer.json → scripts`):
```json
"pre-push": [
    "./vendor/bin/pint --test",
    "./vendor/bin/phpstan analyse --level=8",
    "@php artisan test"
]
```

## При провале

1. Прочитать вывод, найти корневую причину
2. Исправить (не обходить через `--no-verify`)
3. Повторить

**После 3 провалов подряд** — остановиться с отчётом `🛑 Stopped` и описанием причины.

## Артефакты

- Pint изменяет файлы — закоммить правки
- PHPStan — исправить реальные ошибки типов
- PHPUnit/Pest — исправить тесты или код (не пропускать тесты)
