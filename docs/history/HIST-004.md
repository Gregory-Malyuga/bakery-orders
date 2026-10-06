## HIST-004 — class_exists() бросает ErrorException в Laravel при отсутствии файла

**Дата:** 2026-06-08
**Контекст:** Рефакторинг `GenerateOpenApiDocsCommand` для работы с тремя DTO (Resource, CreateData, UpdateData) вместо одного `UserData`. Команда вызывала `class_exists()` для проверки наличия класса домена.
**Симптом:** `php artisan openapi:generate` падал с `ErrorException: include(...): Failed to open stream` вместо возврата `false`.
**Причина:** Laravel регистрирует error handler, который конвертирует PHP-предупреждения (`E_WARNING`) в `ErrorException`. Когда autoloader пытается подключить несуществующий файл, PHP генерирует warning → Laravel превращает его в исключение ещё до того, как `class_exists()` вернёт `false`.
**Фикс:** Обернуть `class_exists()` в `try/catch ErrorException` через приватный метод `safeClassExists(string $class): bool`. Возвращает `false` при любом исключении при загрузке.
**Паттерн для checklist:** `class_exists()` бросает исключение вместо `false` → Laravel конвертирует include-warning; использовать `safeClassExists()` из `GenerateOpenApiDocsCommand` или аналогичный try/catch.

