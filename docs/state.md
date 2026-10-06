# State

> Этот файл ведёт агент. Человек читает, но не редактирует вручную.

## Now

Реализованы R-1..R-15 из post-refactor ревью. Изменения не закоммичены.

**Незакоммиченные изменения (R-1..R-15):**
- `AbstractGenerator` — `withOutput(Closure)`, `writeFile()` с `ensureDirectoryExists`, ES-хелперы
- `TestValueHelper` — ветка `array` + синхронизация с fakerForField (address/city/country/url/title/description)
- `FieldParser` — `timestamp/datetime` nullable=true + правило 'nullable'
- `ControllerGenerator` — assert→RuntimeException, объединены createArgs/updateArgs
- `ApiTestGenerator` — явный assertGreaterThan перед sort-assertions
- `ModelGenerator`, `RepositoryGenerator` — ES через AbstractGenerator хелперы
- `MigrationGenerator` — writeFile вместо put, nullable для колонок
- `MakeDomainCommand` — удалён createDirectories, appendRoutes guard, FQCN в registerProvider, strrpos вместо str_replace, output closure для генераторов, комментарий runningUnderPhpUnit
- Роадмап R-1..R-15 отмечены выполненными

## Аудит 2026-06-08 — итог

Полный аудит проведён после CQRS-рефактора. Проект в хорошем состоянии.

### Закрыто / подтверждено ✓

- **ES маппинг** — `database/elasticsearch/users.json` корректный (`search_as_you_type` + `_2gram`/`_3gram`). `elasticsearch:setup` добавлен в `composer setup`. ✓
- **ES truncation header** — `AddEsSearchTruncatedHeader` подключён в api-группе (`bootstrap/app.php:25`). `EsTruncationBag` — singleton. `InteractsWithElasticsearch::fetchIdsFromElasticsearch()` вызывает `markTruncated()` при переполнении. ✓
- **Docker local dev** — health checks на `php`/`caddy`, redis без пароля, порт 6379 открыт — намеренно. Бой будет на Docker Swarm / Kubernetes с отдельным compose. ✓
- **PHP `^8.5` + `:latest` образ** — принято осознанно. ✓
- **CI pgsql vs SQLite** — проверено вручную, паритет есть. ✓
- **`.env.example`** — обновлён, отражает реальный стек. ✓

### Остаётся открытым (см. roadmap)

- `LogoutHandler` — прямой `PersonalAccessToken` в Application-слое
- ~10 решений из этого файла без ADR

## Recent decisions

- **`CommandHandlerInterface` / `QueryHandlerInterface`** — `HandlerInterface` удалён. Commands возвращают `?int`, Queries возвращают `object`. Подробности: ADR-0004.
- **`safeClassExists()`** — обёртка вокруг `class_exists()` для защиты от Laravel ErrorException при отсутствии файла. Используется в `GenerateOpenApiDocsCommand`. Подробности: HIST-004.
- **OpenAPI-генератор различает input/output DTO** — `{Name}Resource` для response schema, `Create{Name}Data` для POST requestBody, `Update{Name}Data` для PUT requestBody.
- **`elasticsearch:setup` добавлен в `composer setup`** — ES-маппинг (`search_as_you_type` с `_2gram`/`_3gram`) создаётся автоматически при инициализации проекта.
- **`.env.example` обновлён** — отражает реальный Docker-стек: pgsql, redis, elasticsearch, APP_URL=http://localhost:8088.
- **`BaseFilterBuilder` синтаксис** — `new X()->method()` → `(new X())->method()` (PHP 8.4 / PHPStan).
- **`assert($id !== null)`** — сужение `?int` до `int` в контроллерах после `dispatch()`.
- **`UserRepositoryInterface` в Application слое** — ports & adapters: интерфейс в `Application/Repositories/`, реализация в `Infrastructure/Repositories/`. Deptrac: `ApplicationLayer` не импортирует Infrastructure.
- **Auth домен упразднён, влит в User** — `Auth` не имел собственной сущности; контроллеры, команды, handlers и DTOs перенесены в `Domains/User`.
- **`ListEntityQuery` базовый класс** — константа `SORTABLE` в каждом домене, `fromRequest()` объединяет валидацию sort + pagination + filters. Подробности: ADR-0003.
- **`UserDeleted` хранит `int $id`, не модель** — убран `SerializesModels`; предотвращает падение десериализации в очереди после удаления записи.
- **`UserNotFoundException` → HTTP 404** — зарегистрирован в `bootstrap/app.php` через `$exceptions->render()`.

## Last updated

2026-06-12 (реализованы R-1..R-15: все 15 находок post-refactor ревью закрыты)

## Last commit

fix: restore useAppPath to fix getNamespace() detection
