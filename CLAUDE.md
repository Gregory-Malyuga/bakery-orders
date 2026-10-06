# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Running commands

**All PHP/Artisan/Composer commands must run inside the `php` container:**

```bash
docker compose exec -T php sh -c "cd /var/www/app && <command>"
```

| Task | Command (inside container) |
|------|---------------------------|
| Initial setup | `composer setup` |
| Run tests | `php artisan test` or `composer test` |
| Run single test | `php artisan test --filter=TestClassName` |
| Quality gate (pre-push) | `composer pre-push` |
| Code style fix | `./vendor/bin/pint` |
| PHPStan | `./vendor/bin/phpstan analyse --level=8` |
| Architecture check | `./vendor/bin/deptrac analyse` |
| Doc links check | `composer docs:check` |
| OpenAPI regen | `composer codegen:openapi` |
| Generate domain | `php artisan make:domain <Name> field:type ...` |

Start infrastructure: `docker compose up -d` (postgres, redis, elasticsearch, caddy, workers).

## Architecture

The app is a **DDD/CQRS API-only** Laravel service with strict layer enforcement via Deptrac.

### Layer structure (per domain)

```
app/Domains/{Name}/
  Domain/          — Eloquent Model, Enums, Events, Exceptions, Migrations, Factories
  Application/     — Commands, Queries, Handlers, Data DTOs (Spatie LaravelData)
  Infrastructure/  — Repositories, Elasticsearch integration
  Presentation/    — Http/Controllers (thin, invokable, dispatch Commands/Queries)
  Providers/       — ServiceProvider (binds handlers, repositories)
```

**Dependency rules (enforced by `deptrac.yaml`):**
- Presentation → Application → Domain ✓
- Infrastructure → Domain ✓
- No cross-domain calls — domains communicate via events only
- Application layer must NOT import from Presentation or Infrastructure

### Shared layer

`app/Shared/` contains cross-cutting concerns: `Bus/` (CommandBusInterface, QueryBusInterface), `Http/` (BaseData, HealthController), `Repository/`, `Cache/`, `Elasticsearch/`, `Testing/`.

### CQRS flow

Controllers dispatch to `CommandBusInterface` or `QueryBusInterface`. Each Command/Query has exactly one Handler registered via the domain's ServiceProvider.

### Domain code generation

`php artisan make:domain Product name:string price:float is_active:boolean` generates the full CQRS stack (Model, DTO, Commands, Queries, Events, Controller, OpenAPI annotations, ServiceProvider, routes, migration, tests). Options: `--with-elasticsearch`, `--with-cache-warmer`. Existing files are skipped.

## Testing

- Tests run via PHPUnit/Pest — suites: Unit, Feature, Architecture, Domains (embedded per domain)
- Architecture tests validate Deptrac rules (`tests/Architecture/`)
- Domain tests live inside `app/Domains/{Name}/` alongside the code
- Test environment uses SQLite in-memory (via `phpunit.xml`)

## Quality gate sequence (`composer pre-push`)

1. `lint:php` — PHP syntax
2. `cs:check` — Pint style
3. `phpstan` — static analysis (level 8)
4. `architecture` — Deptrac layer rules
5. `docs:check` — relative link validation in markdown
6. `test` — full test suite

## Key config

- **DB**: PostgreSQL in Docker (`DB_CONNECTION=pgsql`), SQLite in local dev
- **Queue**: Redis (`queue: imports`, 2 worker replicas, 600s timeout, 3 tries)
- **Cache/Session**: Redis
- **Search**: Elasticsearch 8.x (`ELASTICSEARCH_URL`)
- **Auth**: Laravel Sanctum (token-based, no sessions for API)
- **Server**: Octane/FrankenPHP in Docker, `artisan serve` locally
- **App URL**: `http://localhost:8088` (via Caddy)

## Routes

All routes are in `routes/api.php`. Auth routes: `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/logout`. Resource routes under `/api/v1/` require `auth:sanctum`. Public endpoints: `GET /health`.
