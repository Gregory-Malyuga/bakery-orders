## HIST-002 — ImportRunCommandTest: SQLSTATE[25P02] в полном прогоне, проходит изолированно

**Дата:** неизвестна (до init-коммита)
**Контекст:** Запуск полного test suite (`php artisan test`).
**Симптом:** `ImportRunCommandTest` падал с `SQLSTATE[25P02]: in failed sql transaction` в полном прогоне, но проходил при запуске изолированно (`--filter`).
**Причина:** Предыдущий тест оставлял транзакцию в broken-состоянии. `RefreshDatabase` не откатывал её корректно при использовании `DatabaseTransactions`, если предыдущий тест завершился исключением внутри транзакции.
**Фикс:** Заменить `DatabaseTransactions` на `RefreshDatabase` в тестах, которые могут бросать исключения внутри транзакций.
**Паттерн для checklist:** SQLSTATE[25P02] только в полном прогоне → тест получает broken-транзакцию от соседнего теста; проверить порядок tearDown и тип DB trait
