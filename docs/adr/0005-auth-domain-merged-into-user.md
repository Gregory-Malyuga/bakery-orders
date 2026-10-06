# ADR-0005 — Auth domain merged into User domain

**Status:** accepted

---

## Context

Изначально существовал отдельный домен `Auth` с контроллерами (`LoginController`, `RegisterController`, `LogoutController`), командами и DTOs. Домен `User` содержал модель и CRUD.

---

## Drivers

- `Auth` не имел собственной доменной сущности — все операции работали с `User`.
- Два домена общались напрямую через модель `User`, нарушая правило «только через события».
- `AuthServiceProvider` дублировал биндинги, уже частично живущие в `UserServiceProvider`.
- Разделение добавляло навигационный шум без архитектурной пользы.

---

## Options

1. **Оставить Auth отдельным доменом** — изолировать через события, ввести `AuthUser` value object. Высокая сложность ради сомнительной пользы.

2. **Влить Auth в User** — перенести контроллеры, команды, handlers и DTOs в `Domains/User`, слить `AuthServiceProvider` с `UserServiceProvider`.

---

## Decision

Принят вариант **2**.

`Domains/Auth` удалён. Контроллеры (`LoginController`, `RegisterController`, `LogoutController`), команды (`LoginCommand` → позднее переименован в `FindUserByCredentialsQuery`), handlers и DTOs перенесены в `Domains/User`. `AuthServiceProvider` слит с `UserServiceProvider`.

---

## Consequences

- Один домен отвечает за всё, связанное с пользователем, включая аутентификацию.
- Если в будущем появится отдельная сущность (например `Session`, `OAuthToken`) — выносить в новый домен по факту необходимости.
