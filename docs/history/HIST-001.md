## HIST-001 — l5-swagger сканирует тестовый базовый класс, PHPUnit не найден

**Дата:** неизвестна (до init-коммита)
**Контекст:** Генерация OpenAPI-документации через l5-swagger.
**Симптом:** l5-swagger падал с ошибкой "PHPUnit not found" при сканировании тестовых классов.
**Причина:** l5-swagger сканировал директорию `tests/`, где в базовом классе (`TestCase`) используется PHPUnit — пакет, недоступный в продакшн-окружении.
**Фикс:** Исключить `tests/` из путей сканирования в конфиге l5-swagger (`scan.exclude`).
**Паттерн для checklist:** l5-swagger падает с "class not found" → проверить `scan.exclude` в `config/l5-swagger.php`
