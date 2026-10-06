---
name: new-domain
description: >-
  Создание нового домена в проекте: скаффолдинг полного CRUD через `php artisan make:domain {Name} [fields]`.
  Триггер: "создать домен", "новый домен", "добавить домен", "make:domain", "scaffold domain",
  "нужен домен для X", "создай структуру для X".
  НЕ триггер: изменение существующего домена, вопросы об архитектуре без намерения создавать.
---

# New Domain Skill

Создаёт полную DDD/CQRS-структуру домена одной командой и помогает заполнить плейсхолдеры.

## Шаг 1 — Выяснить параметры

Если имя домена не указано явно — спроси.

**Поля (`field:type`)** — необязательны, но без них генерируются пустые Data/Factory/тесты. Спроси какие колонки нужны.

Поддерживаемые типы: `string` (по умолчанию), `integer`/`int`, `float`/`decimal`, `boolean`/`bool`, `text`, `email`, `phone`, `date`, `timestamp`/`datetime`, `json`/`array`.  
Поля `id`, `created_at`, `updated_at` — пропускать, они добавляются автоматически.

Тип можно опустить — команда выведет его из имени поля:
- `*_id` → `integer`, `is_*`/`has_*` → `boolean`, `*_price`/`*_cost` → `float`, `*email` → `email`, `*phone` → `phone`, `*_at` → `timestamp`

Также определи флаги:
- **`--with-elasticsearch`** — нужен, если домен работает с полнотекстовым поиском или большими объёмами данных через ES
- **`--with-cache-warmer`** — нужен, если данные домена стоит прогревать при деплое

Имя домена всегда **PascalCase** (`Product`, `OemCross`, `CatalogSource`). Поддерживаются вложенные пути (`Users/User`). Если пользователь написал строчными — ucfirst.

## Шаг 2 — Запустить скаффолдинг

```bash
docker compose exec -T php sh -c "cd /var/www/app && php artisan make:domain {Name} [field:type ...] [--with-cache-warmer] [--with-elasticsearch]"
```

Примеры:
```bash
# Минимальный
php artisan make:domain Product

# С полями
php artisan make:domain Product name:string price:float is_active:boolean

# Вложенный домен
php artisan make:domain Users/User email name

# С опциями
php artisan make:domain Catalog name:string --with-elasticsearch --with-cache-warmer
```

Покажи пользователю список созданных файлов.

## Шаг 3 — Что создаёт команда

Одна команда порождает полный работающий CRUD в DDD-структуре:

### Структура `app/Domains/{Name}/`

```
{Name}/
├── Application/
│   ├── Commands/
│   │   ├── Create/
│   │   │   ├── Create{Name}Command.php
│   │   │   └── Create{Name}Handler.php
│   │   ├── Update/
│   │   │   ├── Update{Name}Command.php
│   │   │   └── Update{Name}Handler.php
│   │   └── Delete/
│   │       ├── Delete{Name}Command.php
│   │       └── Delete{Name}Handler.php
│   ├── Data/
│   │   ├── {Name}Data.php
│   │   └── {Name}FilterData.php
│   └── Queries/
│       ├── FindById/
│       │   ├── Find{Name}ByIdQuery.php
│       │   └── Find{Name}ByIdHandler.php
│       └── ListAll/
│           ├── List{Name}sQuery.php
│           └── List{Name}sHandler.php
├── Domain/
│   ├── Database/Factories/{Name}Factory.php
│   ├── Events/
│   │   ├── {Name}Created.php
│   │   ├── {Name}Updated.php
│   │   └── {Name}Deleted.php
│   ├── Exceptions/{Name}NotFoundException.php
│   └── Models/{Name}.php
├── Infrastructure/
│   └── Repositories/{Name}Repository.php
├── Presentation/
│   └── Http/
│       ├── Controllers/{Name}Controller.php
│       └── OpenApi/{Name}OpenApi.php
├── Providers/{Name}ServiceProvider.php
└── Tests/
    ├── Feature/{Name}ApiTest.php
    └── Unit/{Name}RepositoryTest.php
```

| Файл | Назначение |
|---|---|
| `Application/Commands/Create/Create{Name}Command.php` | `readonly class` с полями; implements `Shared\Bus\BaseCommand` |
| `Application/Commands/Create/Create{Name}Handler.php` | `readonly class`; implements `Shared\Bus\HandlerInterface`; вызывает `repository->create()` + dispatch Created event |
| `Application/Commands/Update/Update{Name}Command.php` | Команда обновления (id + поля) |
| `Application/Commands/Update/Update{Name}Handler.php` | Хендлер: update + dispatch Updated event |
| `Application/Commands/Delete/Delete{Name}Command.php` | Команда удаления (id) |
| `Application/Commands/Delete/Delete{Name}Handler.php` | Хендлер: delete + dispatch Deleted event |
| `Application/Data/{Name}Data.php` | extends `Shared\Http\Data\BaseData`; required + optional поля с `rules()` |
| `Application/Data/{Name}FilterData.php` | DTO фильтров (все поля nullable) |
| `Application/Queries/FindById/Find{Name}ByIdQuery.php` | implements `Shared\Bus\BaseQuery`; поле `id` |
| `Application/Queries/FindById/Find{Name}ByIdHandler.php` | implements `Shared\Bus\HandlerInterface`; `repository->findOrFail()` |
| `Application/Queries/ListAll/List{Name}sQuery.php` | implements `Shared\Bus\BaseQuery`; `filters + sort + pagination`; `static fromRequest()` |
| `Application/Queries/ListAll/List{Name}sHandler.php` | implements `Shared\Bus\HandlerInterface`; `repository->list()` |
| `Domain/Database/Factories/{Name}Factory.php` | `extends Factory<{Name}>`; namespace `Domains\{Name}\Domain\Database\Factories` |
| `Domain/Events/{Name}Created.php` | uses `Dispatchable`, `InteractsWithSockets`, `SerializesModels`; constructor `public readonly {Name} $record` |
| `Domain/Events/{Name}Updated.php` | Аналогично |
| `Domain/Events/{Name}Deleted.php` | Аналогично |
| `Domain/Exceptions/{Name}NotFoundException.php` | extends `RuntimeException`; статика `forId(int\|string $id)` |
| `Domain/Models/{Name}.php` | Eloquent-модель; namespace `Domains\{Name}\Domain\Models`; `newFactory()` ссылается на `Domain\Database\Factories\{Name}Factory` |
| `Infrastructure/Repositories/{Name}Repository.php` | `extends Shared\Repository\BaseRepository`; `protected string $model = {Name}::class` |
| `Presentation/Http/Controllers/{Name}Controller.php` | Инжектирует `CommandBusInterface` + `QueryBusInterface`; методы index/show/store/update/destroy |
| `Presentation/Http/OpenApi/{Name}OpenApi.php` | @OA-аннотации для 5 эндпоинтов |
| `Providers/{Name}ServiceProvider.php` | `register()` уже заполнен: bind всех хендлеров |
| `Tests/Feature/{Name}ApiTest.php` | Feature-тест API (CRUD + pagination + sort + filters) |
| `Tests/Unit/{Name}RepositoryTest.php` | Unit-тест репозитория |

Дополнительно:
- `Infrastructure/Cache/{Name}CacheWarmer.php` — если `--with-cache-warmer`

### Пространства имён

Все классы домена используют корневой неймспейс `Domains\{Name}\...` (не `App\Domains\...`):

| Слой | Неймспейс |
|---|---|
| Application (Commands/Queries/Data) | `Domains\{Name}\Application\...` |
| Domain (Models/Events/Exceptions) | `Domains\{Name}\Domain\...` |
| Domain (Factories) | `Domains\{Name}\Domain\Database\Factories\...` |
| Infrastructure | `Domains\{Name}\Infrastructure\...` |
| Presentation | `Domains\{Name}\Presentation\Http\...` |
| Providers | `Domains\{Name}\Providers\...` |

Shared-зависимости (из пакета `Shared`):

| Что | Где |
|---|---|
| `BaseCommand` | `Shared\Bus\BaseCommand` |
| `BaseQuery` | `Shared\Bus\BaseQuery` |
| `HandlerInterface` | `Shared\Bus\HandlerInterface` |
| `CommandBusInterface` | `Shared\Bus\CommandBusInterface` |
| `QueryBusInterface` | `Shared\Bus\QueryBusInterface` |
| `BaseData` | `Shared\Http\Data\BaseData` |
| `BaseRepository` | `Shared\Repository\BaseRepository` |
| `PaginationData` | `Shared\Data\PaginationData` |
| `SortData` | `Shared\Data\SortData` |

### Автоматически

- Создаётся и применяется миграция (`database/migrations/*_create_{table}_table.php`)
- Провайдер регистрируется в `bootstrap/providers.php`
- Маршруты добавляются в `routes/api.php` (5 маршрутов: index/store/show/update/destroy под `/api/v1/{plural}`)
- Сгенерированные файлы форматируются Pint

Все операции **идемпотентны** — существующие файлы пропускаются с предупреждением.

## Шаг 4 — Что нужно заполнить вручную

| Файл | Что нужно |
|---|---|
| `Infrastructure/Repositories/{Name}Repository.php` | Добавить фильтрацию в `applyFilters()` если нужна |

`Providers/{Name}ServiceProvider.php` генерируется уже заполненным — `register()` содержит `bind()` для всех 5 хендлеров.

## Шаг 5 — Проверка и запуск тестов

```bash
# Запустить тесты домена
docker compose exec -T php sh -c "cd /var/www/app && php artisan test --filter={Name}"

# Проверить маршруты
docker compose exec -T php sh -c "cd /var/www/app && php artisan route:list --path=api/v1/{plural}"
```

## Шаг 6 — Следующие шаги (предложи, не делай автоматически)

1. Добавить фильтрацию в репозиторий если нужна (`applyFilters()` + `FilterData`)
2. Запустить тесты: `php artisan test --filter={Name}`
3. Если `--with-cache-warmer` — реализовать `warm()` и зарегистрировать через `$app->tag()`
