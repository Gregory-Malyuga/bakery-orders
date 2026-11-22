# HIST-008 · HTTP 419 в тестах локального Docker

## Симптом и воспроизведение

`composer test` внутри локального PHP-контейнера: 13 тестов падали, в основном POST-запросы возвращали HTTP 419. В начале вывода Git сообщал `dubious ownership` для `/var/www/app`. При явном `APP_ENV=testing` падавший `ImpersonationTest` проходил.

## Причина

Compose передавал `APP_ENV=local` в окружение контейнера. `phpunit.xml` задавал `APP_ENV=testing` через `<env>`, но Laravel видел прежнее значение в `$_SERVER`, и CSRF-проверка оставалась включённой. Bind mount каталога с Windows также приводил к несовпадению владельца Git-репозитория с пользователем контейнера.

## Исправление и проверка

В PHPUnit тестовые `APP_ENV` и `DB_DATABASE` закреплены также через `<server force="true">`. Из `composer test` убрана миграция, которая до запуска PHPUnit обращалась к БД локального приложения; тесты используют отдельную БД и сами подготавливают схему. В dev-образе Git добавлен `safe.directory=/var/www/app` для подключённого с хоста репозитория.

Повторный `docker compose exec -T php composer test` прошёл: 271 тест, 1801 утверждение. Локального коммита, push, CI и production-деплоя нет.
