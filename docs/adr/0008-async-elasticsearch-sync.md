# ADR-0008 — Elasticsearch sync is asynchronous via ShouldQueue

**Status:** accepted

---

## Context

При создании, обновлении и удалении `User` нужно синхронизировать ES-индекс.
Синхронизация — сетевой вызов к ES (latency ~5–50ms), не критичный для HTTP-ответа.

---

## Drivers

- HTTP-ответ не должен зависеть от доступности ES.
- Сбой ES не должен откатывать транзакцию БД.
- ES eventual consistency приемлема для поиска пользователей.

---

## Options

1. **Синхронно в Handler** — вызвать индексер после `repository->create/update/delete`. ES-сбой = ошибка HTTP 500.

2. **Синхронно в Listener** — слушать domain-событие, индексировать синхронно. Та же проблема с availability.

3. **Асинхронно через Queue** — `UserElasticsearchSyncListener implements ShouldQueue`. Событие попадает в очередь `imports`, обрабатывается worker'ом независимо.

---

## Decision

Принят вариант **3**.

`UserElasticsearchSyncListener implements ShouldQueue`, `public string $queue = 'imports'`.  
Listeners: `handleCreated`, `handleUpdated` → `indexByIds([$id])`, `handleDeleted` → `deleteFromIndex($id)`.  
Регистрация в `UserServiceProvider::boot()`.

---

## Consequences

- ES-сбой не влияет на HTTP-ответы и не откатывает DB-транзакции.
- Кратковременная рассинхронизация (ES отстаёт от DB) — приемлема.
- Тесты, проверяющие ES-поиск после создания, должны вызывать `indexer->indexByIds()` и `refreshIndex()` явно (см. `UserRepositoryTest`).
