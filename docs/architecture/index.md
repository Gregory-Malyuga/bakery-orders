# Architecture — Index

Архитектурные решения и паттерны проекта.

Текущий стек пекарни: **Laravel 13 / PHP 8.5**, PostgreSQL, Caddy, Sanctum, Spatie Laravel Data. В Compose запускаются только Caddy, PHP и PostgreSQL. Сайт пекарни использует Blade; общие API-компоненты каркаса сохранены.

| Файл | Тема |
|---|---|
| [onion.md](onion.md) | DDD-слои домена — Domain / Application / Infrastructure / Presentation |
| [cqrs.md](cqrs.md) | CQRS — `Shared\Bus` CommandBus / QueryBus, конвенция Handler |
| [persistence.md](persistence.md) | Хранилище — Eloquent, `BaseRepository`, Spatie Data, миграции |
| [search.md](search.md) | Поиск пользователей через PostgreSQL; Elasticsearch-компоненты каркаса |
| [events.md](events.md) | Доменные события — Laravel events, `{Name}Created/Updated/Deleted` |
| [cross-module.md](cross-module.md) | Связи между доменами — FK по id, события |
| [deptrac.md](deptrac.md) | Deptrac — контроль зависимостей между слоями |
| [shared-module.md](shared-module.md) | `app/Shared/` — Bus, Repository, Data, Cache, Testing, кодген |

**Ключевые правила (кратко):**
- Каждый домен — `app/Domains/{Name}/`, делится на 4 слоя + `Providers/` + `Tests/`.
- Domain-слой изолирован: не импортирует Application/Infrastructure/Presentation.
- Presentation вызывает только `$this->commands->dispatch()` / `$this->queries->ask()`.
- Eloquent-запросы — только в `Infrastructure/Repositories/`.
- `Command` и `Handler` — `readonly class`. Конвенция: `{Name}Command → {Name}Handler`.
- DTO — Spatie Data (`extends Shared\Http\Data\BaseData`), заменяет FormRequest + Resource.
- Слои enforced через deptrac (`composer architecture`).
- Полный список запретов: [`../process/forbidden.md`](../process/forbidden.md)
