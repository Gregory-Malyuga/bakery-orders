# Shared

`app/Shared/` — переиспользуемый каркас, общий для всех доменов. Бизнес-логики и сущностей здесь нет.

См. также: [Onion](onion.md), [CQRS](cqrs.md), [Persistence](persistence.md).

---

## Состав

| Папка | Что внутри |
|---|---|
| `Bus/` | `CommandBusInterface` / `QueryBusInterface` / `HandlerInterface`, `LaravelCommandBus` / `LaravelQueryBus`, `BaseCommand` / `BaseQuery` |
| `Repository/` | `BaseRepository` — CRUD + `list()` + хук `postProcessBuilder()` |
| `QueryBuilder/` | `BaseQueryBuilder` (fluent-обёртка над Eloquent), `BaseQueryBuilderPostprocessor` (orderBy + paginate) |
| `FilterBuilder/` | `BaseFilterBuilder` — convention-based фильтры (`camelCase → snake_case → WHERE`) + `$filterMap` |
| `Filters/` | `FilterInterface` — кастомные фильтры |
| `Http/Data/` | `BaseData`, `BaseDataCollection` — основа Spatie Data DTO |
| `Data/` | `PaginationData`, `SortData` (`fromRequest()`) |
| `Elasticsearch/` | `ElasticsearchSearchable` + `InteractsWithElasticsearch` — см. [Search](search.md) |
| `Cache/` | `CacheWarmerInterface`, `CacheWarmCommand` (`php artisan cache:warm`) |
| `Console/Commands/` | `MakeDomainCommand` (`make:domain`), `GenerateOpenApiDocsCommand` (`openapi:generate`) |
| `Testing/` | `BaseRepositoryTest`, `BaseApiTest` + трейты `HasRepositoryTests`, `HasApiTests`, `InteractsWith*`, `MakesApiAssertions` |
| `Providers/` | `AppServiceProvider` — биндинг шин, тег `cache-warmers` |

---

## Правила

- `Shared` зависит только от фреймворка (Laravel, Spatie, Elasticsearch) — не от `Domains\*`.
- `Shared\Testing\*` выделен в отдельный deptrac-слой `TestSupport`, чтобы тестовые базовые классы не тянули прод-`Shared`.
- Новый общий механизм добавляется в `Shared`, только если он нужен ≥ 2 доменам; иначе он остаётся в домене.
- Базовые тест-классы расширяют `Illuminate\Foundation\Testing\TestCase` напрямую (не `Tests\TestCase`), чтобы не создавать зависимость на `tests/`.
