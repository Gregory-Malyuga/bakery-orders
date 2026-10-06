# ADR-0004 — Split HandlerInterface into CommandHandlerInterface and QueryHandlerInterface

**Status:** accepted

---

## Context

В CQRS-слое шины (`CommandBusInterface`, `QueryBusInterface`) требовали единый
`HandlerInterface` с методом `handle(object $message): mixed`.

Тип `mixed` снимает все гарантии PHPStan: реализации могут возвращать что угодно,
а bus-методы (`dispatch`, `ask`) теряют типизированный контракт.

Дополнительная проблема: PHP не допускает `void` как подтип `mixed` — хэндлеры,
которые «ничего не возвращают» (`DeleteUserHandler`, `LogoutHandler`), были вынуждены
объявлять `return null` вместо семантически верного `void`.

---

## Drivers

- PHPStan level 8 должен видеть реальный тип возврата `dispatch()` и `ask()`.
- Command-хэндлеры возвращают либо `int $id` (Create), либо ничего (Update, Delete, Logout).
- Query-хэндлеры всегда возвращают объект: `User`, `LengthAwarePaginator`, и т.п.
- Единый интерфейс — избыточное обобщение без пользы.

---

## Options

1. **PHPDoc `@return mixed`** — убрать PHP-тип из `HandlerInterface::handle()`, оставить `@return mixed` в аннотации. Позволяет void/null-реализациям сосуществовать. PHPStan принимает, но `dispatch()` возвращает `mixed` — потребители теряют тип.

2. **Два интерфейса** — `CommandHandlerInterface: handle(): ?int` и `QueryHandlerInterface: handle(): object`.
   - `?int` охватывает Create (возвращает id) и Update/Delete (возвращают null).
   - `object` охватывает любой query-результат (`User`, `LengthAwarePaginator<int, User>`, etc.)
     и запрещает возврат `null` — query, не нашедший результат, обязан бросить исключение.
   - Bus-методы типизируются: `dispatch(): ?int`, `ask(): object`.

---

## Decision

Принят вариант **2**.

`HandlerInterface` удалён. Созданы:
- `app/Shared/Bus/CommandHandlerInterface.php` — `handle(object $message): ?int`
- `app/Shared/Bus/QueryHandlerInterface.php` — `handle(object $message): object`

`LaravelCommandBus::dispatch()` возвращает `?int`, проверяет `instanceof CommandHandlerInterface`.  
`LaravelQueryBus::ask()` возвращает `object`, проверяет `instanceof QueryHandlerInterface`.

Все реализации обновлены:
- Command: `CreateUserHandler`, `RegisterHandler` → `int`; `UpdateUserHandler`, `DeleteUserHandler`, `LogoutHandler` → `null`
- Query: `FindUserByIdHandler`, `FindUserByCredentialsHandler` → `User`; `ListUsersHandler` → `LengthAwarePaginator<int, User>`

---

## Consequences

**Плюсы:**
- PHPStan видит `?int` / `object` на выходе bus-методов — не нужны аннотации в контроллерах.
- `assert($id !== null)` сужает `?int` до `int` там, где id гарантирован.
- Query-контракт явно запрещает null-результат: "не найдено" = исключение, не `null`.

**Ограничения:**
- Query, который по семантике возвращает `T|null` (например "найди если существует"),
  не вписывается в `object`. Варианты: бросить исключение, вернуть NullObject, или
  завести отдельный `NullableQueryHandlerInterface` по необходимости.
- Command не может вернуть составной результат — только `?int`. Если понадобится
  возвращать несколько скаляров, потребуется новое решение (value object / event).
