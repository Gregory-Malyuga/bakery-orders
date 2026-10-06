## HIST-005 — HandlerInterface::handle(): mixed несовместим с void-реализациями

**Дата:** 2026-06-08
**Контекст:** Добавление PHP-типа `mixed` к `HandlerInterface::handle()` для PHPStan level 8.
**Симптом:** PHPStan ошибки на `DeleteUserHandler` и `LogoutHandler` — их `return null` несовместим с `mixed` в контексте void-семантики. Кроме того, `void` как тип полностью несовместим с `mixed` в PHP.
**Причина:** PHP-тип `mixed` требует явного `return`, тогда как `void`/`null`-хэндлеры семантически "ничего не возвращают". Единый интерфейс не может выразить оба контракта одновременно.
**Фикс:** Разделить `HandlerInterface` на `CommandHandlerInterface: ?int` и `QueryHandlerInterface: object`. Подробности: ADR-0004.
**Паттерн для checklist:** PHPStan жалуется на return-тип implements → проверить, не пытается ли единый интерфейс покрыть разные семантики возврата.
