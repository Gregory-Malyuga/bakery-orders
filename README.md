# Laravel Microservice Boilerplate

Стартовый шаблон Laravel-микросервиса: PHP 8.5 / Laravel 13, Elasticsearch, Redis, Docker Swarm.

## Стек

- PHP 8.5 / Laravel 13
- Elasticsearch 8.x (хранение, поиск)
- Redis 7 (кеш, очереди)
- Caddy (reverse proxy)
- Docker / docker-compose

## Быстрый старт

```bash
# 1. Форкнуть и инициализировать под конкретный сервис
#    (переименовать namespace, env, docker-метки и т.д.)
# Запустить /setup-service в Claude Code — он проведёт по шагам.

# 2. Поднять окружение
docker compose up -d

# 3. Миграции
docker compose exec -T php sh -c "cd /var/www/app && php artisan migrate"
```

App URL: `http://localhost:8088`

## Разработка

Все PHP/Artisan/Composer команды — внутри контейнера `php`:

```bash
# Тесты
docker compose exec -T php sh -c "cd /var/www/app && php artisan test"

# Quality gate (pint + phpstan level 8 + tests)
docker compose exec -T php sh -c "cd /var/www/app && composer pre-push"

# Pint (авто-форматирование)
docker compose exec -T php sh -c "cd /var/www/app && ./vendor/bin/pint"

# PHPStan
docker compose exec -T php sh -c "cd /var/www/app && ./vendor/bin/phpstan analyse --level=8"
```

## Кодогенератор доменов

Одна команда создаёт полный CQRS-домен:

```bash
docker compose exec -T php sh -c "cd /var/www/app && php artisan make:domain Post title:string body:string is_published:boolean"
```

Генерирует:
- **Model** + **Factory** с faker-данными под типы полей
- **Data DTO** + **FilterData** (Spatie Laravel Data)
- **Commands**: Create/Update/Delete + Handlers
- **Queries**: ListAll/FindById + Handlers
- **Events**: Created/Updated/Deleted
- **REST Controller** (index/show/store/update/destroy)
- **OpenAPI-аннотации** для всех 5 эндпоинтов
- **ServiceProvider** — авторегистрация в `bootstrap/providers.php`
- **Маршруты** — автодобавление в `routes/api.php` (`/api/v1/{plural}`)
- **Миграция** — создаётся и применяется сразу
- **Unit + Feature тесты** с покрытием CRUD, пагинации, сортировки, фильтров

Опции:
- `--with-elasticsearch` — добавляет `InteractsWithElasticsearch` в модель и репозиторий
- `--with-cache-warmer` — генерирует `{Name}CacheWarmer`

Все операции идемпотентны — существующие файлы пропускаются.

## Эндпоинты из коробки

| Method | Path | Описание |
|--------|------|----------|
| GET | `/api/health` | Health check |

## Структура

```
app/
  Shared/             — базовые классы (Bus, Data, Repository, Testing …)
  Domains/            — доменные модули (CQRS, сгенерированные make:domain)
  Http/Controllers/   — тонкие контроллеры legacy-слоя
```

Архитектурные ограничения и рабочий процесс — в [CLAUDE.md](CLAUDE.md).  
Текущий статус задач — в [docs/state.md](docs/state.md).
