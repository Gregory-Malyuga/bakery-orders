# ADR-0010 — Domain exceptions mapped to HTTP codes in bootstrap/app.php

**Status:** accepted

---

## Context

Domain-исключения (`UserNotFoundException`, `InvalidCredentialsException`, и др.) — чистые PHP-классы
без зависимости от HTTP. Нужен механизм трансляции их в HTTP-ответы без загрязнения доменного слоя.

---

## Drivers

- Domain-исключения не должны знать про HTTP (нарушение слоёв).
- Laravel предоставляет `$exceptions->render()` в `bootstrap/app.php` — централизованное место.
- Альтернатива `renderable()` в самих классах исключений — тянет HTTP в Domain.

---

## Options

1. **`renderable()` в классе исключения** — удобно, но добавляет `Illuminate\Http` импорт в Domain-слой.

2. **`$exceptions->render()` в `bootstrap/app.php`** — Presentation/Infrastructure concern вынесен в bootstrap, Domain остаётся чистым.

---

## Decision

Принят вариант **2**.

`bootstrap/app.php` содержит маппинг:
- `UserNotFoundException` → 404
- `InvalidCredentialsException` → 401
- `UserInsufficientRoleException` → 403

Каждое доменное исключение реализует только `\Throwable`, без HTTP-зависимостей.

---

## Consequences

- Domain-слой не импортирует `Illuminate\Http`.
- Все HTTP-коды видны в одном месте — легко аудировать.
- При добавлении нового домена — регистрировать маппинг в `bootstrap/app.php`.
