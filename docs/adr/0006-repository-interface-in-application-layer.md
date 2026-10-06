# ADR-0006 — Repository interface in Application layer (ports & adapters)

**Status:** accepted

---

## Context

`UserRepository` (Eloquent + Elasticsearch) живёт в Infrastructure. Handlers в Application слое
изначально зависели напрямую от конкретного класса `UserRepository`.

---

## Drivers

- Deptrac запрещает Application → Infrastructure импорты.
- Unit-тесты хендлеров требовали поднимать Eloquent/ES или мокать конкретный класс.
- Принцип инверсии зависимостей: Application диктует контракт, Infrastructure выполняет.

---

## Options

1. **Исключение в Deptrac** — разрешить Application импортировать Infrastructure точечно. Нарушает архитектурный инвариант.

2. **Интерфейс в Application** — `UserRepositoryInterface` в `Application/Repositories/`, реализация в `Infrastructure/Repositories/`. ServiceProvider биндит реализацию.

---

## Decision

Принят вариант **2**.

`UserRepositoryInterface` живёт в `app/Domains/User/Application/Repositories/`.  
`UserRepository` реализует интерфейс и живёт в `app/Domains/User/Infrastructure/Repositories/`.  
`UserServiceProvider::register()` выполняет `bind(UserRepositoryInterface::class, UserRepository::class)`.  
Deptrac: `ApplicationLayer` не может импортировать `InfrastructureLayer` напрямую.

---

## Consequences

- Handlers тестируются через интерфейс без поднятия БД/ES.
- Каждый новый домен с репозиторием следует тому же паттерну.
- `make:domain` генерирует интерфейс в Application и реализацию в Infrastructure автоматически.
