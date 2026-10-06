# ADR-0007 — Domain events carry scalar IDs, not Eloquent models

**Status:** accepted

---

## Context

`UserDeleted` изначально передавал `User $record` (Eloquent-модель) и использовал `SerializesModels`.
При обработке события в очереди Laravel пытается десериализовать модель через её primary key —
но к этому моменту запись уже удалена, десериализация падает с `ModelNotFoundException`.

---

## Drivers

- События `*Deleted` по природе описывают факт удаления — модель недоступна после удаления.
- `SerializesModels` несовместим с deleted-событиями при async-обработке.
- ES-listener должен вызвать `deleteFromIndex(int $id)` — ему нужен только ID.

---

## Options

1. **Soft delete** — не удалять физически, хранить `deleted_at`. Избыточно для текущих требований.

2. **Передавать `int $id`** — сохранить ID до `delete()`, передать в событие без `SerializesModels`.

---

## Decision

Принят вариант **2**.

```php
$id = $record->id;
$this->repository->delete($record);
UserDeleted::dispatch($id);
```

`UserDeleted` хранит `public int $id`, без `SerializesModels`.  
`UserElasticsearchSyncListener::handleDeleted()` принимает `UserDeleted $event` и вызывает `deleteFromIndex($event->id)`.

Паттерн распространяется на все `*Deleted`-события проекта.

---

## Consequences

- Deleted-события безопасны в очереди.
- Listener не может восстановить полный объект удалённой записи — только ID. Достаточно для ES и аудита.
