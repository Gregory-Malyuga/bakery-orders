# Сайт заказов пекарни

Полная карта страниц, функций и ограничений сервиса — в [документации сервиса](docs/service-guide.md).

Текущие задачи и ошибки: [roadmap](docs/process/roadmap.md); порядок их ведения: [работа с задачами и ошибками](docs/process/work-items.md).

Локально: `http://localhost:8099/login`. Магазин оформляет один принятый заказ на завтрашнюю поставку до настраиваемого времени по Москве (по умолчанию 18:30); пекарня смотрит сводку, управляет справочниками и печатает накладные. Публичной регистрации нет.

Срок заказа проверяется по времени сервера при предварительном просмотре и при окончательном подтверждении; дата и время устройства магазина не учитываются. В разделе «Магазины, товары и цены» администратор задаёт товару минимальное количество в штуках. Ноль означает отказ от товара; если товар выбран, количество должно быть не меньше указанного минимума. Из сводки можно напечатать все принятые накладные за выбранную дату или скачать их в одном `.xlsx`; на странице заказа доступны печать и `.xlsx` одной накладной. Отменённые заказы в выгрузку не попадают.

В XLSX каждая накладная находится на отдельном листе и настроена для печати на одной странице. Лист «Заказ» содержит матрицу количеств по магазинам и итоги в штуках и рублях. Столбцы возврата в накладной появляются только при наличии возвратов. Можно вернуть доступный магазину товар, даже если его нет в текущем заказе; цена фиксируется при возврате. Заказанные позиции сохраняют цену на момент оформления.

```bash
docker compose up -d --build
docker compose exec -T php sh -c 'cd /var/www/app && composer install && php artisan migrate --force'
docker compose exec -T php sh -c 'cd /var/www/app && php artisan db:seed --class=BakeryDemoSeeder --force'
```

Демовходы: `admin@example.test`, `sadovaya@example.test`, `frunze@example.test`, `shosse@example.test`; пароль для всех `Demo123456!`. Демоданные запускайте только в локальной среде. В рабочей среде создайте аккаунт администратора отдельно и задайте собственный пароль. Пользователи магазинов создаются в панели администратора.

Для первого развёртывания после настройки `.env` выполните миграции, заполните магазины, товары, цены и магазинные аккаунты из «Лист1», затем создайте администратора. Последняя команда запускается **без `-T`**: пароль вводится скрыто дважды и не попадает в историю команд.

```bash
docker compose exec -T php php artisan migrate --force
docker compose exec -T php php artisan bakery:seed-reference
docker compose exec php php artisan bakery:create-admin admin@example.com
```

Если `APP_KEY` ещё не задан, перед миграциями выполните `docker compose exec -T php php artisan key:generate`. После входа откройте `/admin`. Не запускайте `BakeryDemoSeeder` в рабочей среде.

### Начальная загрузка из «Лист1»

Файл `resources/data/Накладные 10.09.xlsx` используется как источник 72 магазинов, 23 товаров и персональных цен. Проверка без записи и загрузка:

```bash
docker compose exec -T php php artisan bakery:seed-reference --dry-run
docker compose exec -T php php artisan bakery:seed-reference
```

Для новых магазинов создаются логины `store-001@bakery.test` … `store-072@bakery.test` в порядке колонок «Лист1», пароль `changeme`. При повторном запуске существующие пароли сохраняются. Нулевая цена означает недоступность товара для конкретного магазина: цена не показывается, заказ товара запрещён. В исходном листе название и адрес магазина объединены в одной ячейке, поэтому при загрузке это значение записывается в оба поля; адрес можно уточнить в админ-панели. Демоаккаунты и демотовары сохраняются.

### Импорт согласованного каталога

Сохраните согласованную таблицу в UTF-8 CSV с разделителем `;` и заголовком `product;store;address;price`. Каждая строка задаёт цену одного товара для одного магазина; для всех пар магазин–товар строки обязательны. Команда сначала проверяет пропуски, дубликаты, написания и цены. Соответствия названий между файлами не угадываются. Исходные Excel в `resources/data` не изменяются.

```bash
docker compose exec -T php sh -c 'cd /var/www/app && php artisan bakery:import-catalog /var/www/app/resources/data/approved.csv'
docker compose exec -T php sh -c 'cd /var/www/app && php artisan bakery:import-catalog /var/www/app/resources/data/approved.csv --apply'
```

### Проверка

Создайте отдельную БД тестов один раз: `docker compose exec -T postgres createdb -U laravel-database-user laravel-database-test`. Запускайте gate на ней, чтобы не менять локальные демоданные:

```bash
docker compose exec -T -e DB_DATABASE=laravel-database-test php sh -c 'cd /var/www/app && composer pre-push'
```

### Развёртывание и резервное копирование

Для рабочего окружения используйте `docker-compose.prod.yml` вместе с основным Compose-файлом. Он запускает только Caddy, PHP и PostgreSQL: сессии и кеш хранятся в PostgreSQL, очередь работает синхронно. Зависимости находятся внутри PHP-образа, каталог `vendor` с хоста не монтируется. По умолчанию HTTP привязан только к `127.0.0.1:8099`. Задайте уникальные `APP_KEY`, `DB_PASSWORD` и `APP_URL` в закрытом для посторонних `.env`; пароль роли PostgreSQL должен совпадать с `DB_PASSWORD`. После настройки HTTPS и домена измените публикацию Caddy. Не размещайте форму входа в открытом интернете по HTTP.

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T php php artisan migrate --force
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T php php artisan bakery:seed-reference
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec php php artisan bakery:create-admin admin@backery.com
```

Приложение использует постоянные тома PostgreSQL, Redis и Elasticsearch. После запуска проверьте `/health`, вход, оформление и печать.

Для резервной копии остановка приложения не требуется. Пример для локального Compose:

```bash
docker compose exec -T postgres pg_dump -U laravel-database-user -d laravel-database -Fc -f /tmp/bakery.dump
docker cp laravel-app-postgres-1:/tmp/bakery.dump ./bakery-$(Get-Date -Format yyyyMMdd-HHmmss).dump
```

Храните копию вне хоста и регулярно проверяйте восстановление в отдельную БД командой `pg_restore`. Перед миграциями в рабочей среде сначала сделайте и проверьте копию.

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

App URL: `http://localhost:8099`

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
