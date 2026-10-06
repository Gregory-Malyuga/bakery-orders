# Команды разработки

Все команды выполняются **внутри контейнера** через:

```bash
docker compose exec -T php sh -c "cd /var/www/app && <команда>"
```

Сокращение ниже: везде, где написано `composer <script>`, подразумевается этот префикс.

---

## Тесты

### Запустить все тесты (включая Domain)

```bash
docker compose exec -T php sh -c "cd /var/www/app && php artisan test"
```

Или через composer-скрипт (очищает config-кеш перед запуском):

```bash
docker compose exec -T php sh -c "cd /var/www/app && composer test"
```

PHPUnit обнаруживает четыре suite из `phpunit.xml`:

| Suite | Откуда |
|---|---|
| `Unit` | `tests/Unit/` |
| `Feature` | `tests/Feature/` |
| `Architecture` | `tests/Architecture/` |
| `Domains` | `app/Domains/**/Tests/**/*Test.php` |

Suite `Domains` включается автоматически — файлы достаточно положить в `app/Domains/<Name>/Tests/`.

### Запустить конкретный suite

```bash
docker compose exec -T php sh -c "cd /var/www/app && php artisan test --testsuite=Domains"
docker compose exec -T php sh -c "cd /var/www/app && php artisan test --testsuite=Unit"
docker compose exec -T php sh -c "cd /var/www/app && php artisan test --testsuite=Feature"
```

### Запустить один тест или класс

```bash
# По имени теста
docker compose exec -T php sh -c "cd /var/www/app && php artisan test --filter=StoreRepositoryTest"

# По методу
docker compose exec -T php sh -c "cd /var/www/app && php artisan test --filter=StoreRepositoryTest::it_finds_by_id"

# Конкретный файл
docker compose exec -T php sh -c "cd /var/www/app && php artisan test app/Domains/Store/Tests/Unit/StoreRepositoryTest.php"
```

### Где размещать тесты для нового домена

```
app/Domains/<Name>/Tests/
├── Unit/           ← unit-тесты (Repository, Service, Data)
└── Feature/        ← HTTP / интеграционные тесты
```

Базовые классы из `Shared\Testing`:
- `BaseRepositoryTest` — заготовка для Repository-тестов
- `BaseApiTest` — заготовка для Feature/HTTP-тестов

---

## Кодогенерация (make:domain)

Создаёт полную DDD-структуру домена: Model, Factory, Data, FilterData, Commands, Queries, Events, NotFoundException, Repository, Controller, OpenApi-заготовку, ServiceProvider, тесты, миграцию.

Сигнатура: `make:domain {Name} [field:type ...] [--with-elasticsearch] [--with-cache-warmer]`.

```bash
# Базовый домен с полями
docker compose exec -T php sh -c "cd /var/www/app && php artisan make:domain Post title:string body:string"

# С поддержкой Elasticsearch (интерфейс + трейт + buildEsQuery)
docker compose exec -T php sh -c "cd /var/www/app && php artisan make:domain Article title:string body:string --with-elasticsearch"

# С CacheWarmer
docker compose exec -T php sh -c "cd /var/www/app && php artisan make:domain Category name:string --with-cache-warmer"
```

Через composer-обёртку (без аргументов — выдаёт help):

```bash
docker compose exec -T php sh -c "cd /var/www/app && composer codegen"
# эквивалентно php artisan make:domain
```

**Идемпотентно** — повторный запуск с тем же именем пропустит уже существующие файлы.

Что создаётся:

```
app/Domains/<Name>/
├── Domain/
│   ├── Models/<Name>.php
│   ├── Database/Factories/<Name>Factory.php
│   ├── Events/<Name>{Created,Updated,Deleted}.php
│   └── Exceptions/<Name>NotFoundException.php
├── Application/
│   ├── Data/<Name>Data.php, <Name>FilterData.php
│   ├── Commands/{Create,Update,Delete}/*Command.php + *Handler.php
│   └── Queries/{ListAll,FindById}/*Query.php + *Handler.php
├── Infrastructure/
│   ├── Repositories/<Name>Repository.php
│   └── Cache/<Name>CacheWarmer.php        ← только с --with-cache-warmer
├── Presentation/Http/
│   ├── Controllers/<Name>Controller.php
│   └── OpenApi/<Name>OpenApi.php           ← обновляется openapi:generate
├── Providers/<Name>ServiceProvider.php
└── Tests/{Unit,Feature}/
database/migrations/
└── xxxx_xx_xx_create_<name>s_table.php
```

> После генерации зарегистрируй `<Name>ServiceProvider` в `bootstrap/providers.php` и добавь роуты.

---

## OpenAPI-документация (openapi:generate)

Генерирует `*OpenApi.php`-файлы для каждого домена на основе Spatie Data DTOs через рефлексию.

```bash
docker compose exec -T php sh -c "cd /var/www/app && php artisan openapi:generate"
```

Или через composer:

```bash
docker compose exec -T php sh -c "cd /var/www/app && composer codegen:openapi"
```

**Автозапуск:** хук `.githooks/pre-commit` запускает генерацию перед каждым коммитом и автоматически стейджит изменённые `*/Http/OpenApi/*.php`.

Что читается:
- `*Data.php` → schema + request body
- `*FilterData.php` → query-параметры
- `routes/api.php` → route-prefix

Swagger UI доступен по адресу `http://localhost:8088/api/documentation` (только в `APP_ENV=local`).

---

## IDE Helper

Генерирует файлы автодополнения для PhpStorm/VS Code.

```bash
docker compose exec -T php sh -c "cd /var/www/app && composer ide-helper"
```

Что выполняется внутри:
1. `php artisan ide-helper:generate` → `_ide_helper.php` (фасады)
2. `php artisan ide-helper:models --nowrite` → `_ide_helper_models.php` (модели, без правки исходников)

**Автозапуск:** хук `.githooks/post-checkout` запускает `composer ide-helper` при переключении веток.

Сгенерированные файлы добавлены в `.gitignore` — не коммитить.

---

## Quality gate (pre-push)

Полная проверка перед пушем:

```bash
docker compose exec -T php sh -c "cd /var/www/app && composer pre-push"
```

Шаги по порядку:

| Шаг | Команда |
|---|---|
| Синтаксис PHP | `find app routes tests database -name '*.php' \| xargs php -l` |
| Code style | `./vendor/bin/pint --test` |
| PHPStan level 8 | `./vendor/bin/phpstan analyse` |
| Архитектура (deptrac) | `./vendor/bin/deptrac analyse` |
| Doc links | `php bin/check-doc-links.php` |
| Тесты (все suite) | `php artisan test` |

Запустить только один шаг:

```bash
# Code style — проверка
docker compose exec -T php sh -c "cd /var/www/app && composer cs:check"

# Code style — автоисправление
docker compose exec -T php sh -c "cd /var/www/app && ./vendor/bin/pint"

# PHPStan
docker compose exec -T php sh -c "cd /var/www/app && composer phpstan"

# Архитектура
docker compose exec -T php sh -c "cd /var/www/app && composer architecture"
```

---

## Прочие команды

```bash
# Сгенерировать ключ приложения (первый запуск)
docker compose exec -T php sh -c "cd /var/www/app && php artisan key:generate"

# Запустить миграции
docker compose exec -T php sh -c "cd /var/www/app && php artisan migrate"

# Откат последней миграции
docker compose exec -T php sh -c "cd /var/www/app && php artisan migrate:rollback"

# Прогреть кеш
docker compose exec -T php sh -c "cd /var/www/app && php artisan cache:warm"

# Список маршрутов
docker compose exec -T php sh -c "cd /var/www/app && php artisan route:list"

# Tinker (REPL)
docker compose exec php sh -c "cd /var/www/app && php artisan tinker"
```
