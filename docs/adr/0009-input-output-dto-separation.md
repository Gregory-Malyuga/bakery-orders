# ADR-0009 — Separate input and output DTOs

**Status:** accepted

---

## Context

Изначально `UserData` использовался в обоих направлениях: `UserData::from($request)` для
десериализации входящего запроса и `UserData::from($record)` для формирования ответа.
Класс содержал поле `password` (nullable), которое могло попасть в ответ при неверной конфигурации `$hidden`.

---

## Drivers

- Один DTO для input и output — разные наборы полей, разные правила валидации.
- `password` в response-DTO — потенциальная утечка при рефакторинге модели.
- OpenAPI-генератор не может различить `requestBody` schema и `response` schema если они один класс.

---

## Options

1. **Оставить единый DTO**, полагаться на `$hidden` модели. Хрупко — одна правка в модели ломает безопасность.

2. **Разделить на три класса**:
   - `CreateUserData` — input для POST (password required)
   - `UpdateUserData` — input для PUT (password optional)
   - `UserResource` — output (без password, только читаемые поля)

---

## Decision

Принят вариант **2**.

`CreateUserData`, `UpdateUserData` — Spatie Data с `rules()`, используются в FormRequests.  
`UserResource` — Spatie Data без `password`, используется в контроллерах для сериализации ответа.  
`make:domain` генерирует все три класса автоматически.

---

## Consequences

- `password` физически отсутствует в `UserResource` — утечка невозможна на уровне типов.
- OpenAPI-аннотации различают `requestBody` (`Create{Name}Data`) и `response` (`{Name}Resource`).
- Больше файлов на домен, но каждый с чёткой ответственностью.
