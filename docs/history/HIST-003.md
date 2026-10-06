## HIST-003 — generate_always + PHP-FPM OPcache: docs регенерируются из устаревшего кеша

**Дата:** неизвестна (до init-коммита)
**Контекст:** Обновление OpenAPI-аннотаций при включённом OPcache в PHP-FPM.
**Симптом:** После изменения аннотаций в коде, `generate_always = true` не подхватывал обновления — документация генерировалась из устаревших классов.
**Причина:** PHP-FPM кешировал старые версии файлов в OPcache. `generate_always` перечитывал классы через PHP, но OPcache отдавал байткод без изменений до рестарта воркеров.
**Фикс:** В dev-окружении добавить `opcache.validate_timestamps=1` и `opcache.revalidate_freq=0`, либо использовать `php artisan octane:reload` после правки аннотаций.
**Паттерн для checklist:** OpenAPI-docs не обновляются после правки аннотаций → сбросить OPcache (`opcache_reset()` или рестарт FPM)
