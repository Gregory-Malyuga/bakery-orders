# State

## Current — 2026-11-01

- **BUG-09:** на production `backery` авторизованный `/admin` отвечал 500: новый код обращался к `bakery_returns.status`, а добавляющая его миграция имела статус `Pending` (SQLSTATE `42703`). После релиза `ca21442` миграция отмечена `Ran`, `/health` отвечает 200; авторизованный `/admin` ещё не проверен. Ручная замена серверного deploy-скрипта отменена. SSH-ключ CI переведён на `restrict,command="/usr/bin/python3 -"`, пользователь `bakery-deploy` добавлен в группу Docker с разрешения пользователя. Подготовлена доставка deploy-скрипта, Compose и Caddyfile из GitHub Actions через SSH stdin в каталог релиза по SHA; до проверки нового процесса и страницы дефект остаётся открытым. Локальный Docker недоступен; пользователь разрешил разовое исключение из проверки перед коммитом и push. [Постановка](process/bugs/BUG-09.md).

- **BAK-01:** коммит `009c2d8` отправлен в `origin/main`: добавлена отмена отдельного возврата с причиной, автором, временем и сохранением записи в истории. Общий расчёт печати, XLSX и отчёта исключает отменённые возвраты; список и карточка заказа показывают итог после возвратов. Первый CI выявил ошибку сравнения XLSX-ячейки, тест исправлен. PHPStan на следующих двух коммитах указал, что ID из `pluck()` имеет тип `mixed`; перед приведением типа добавлена проверка `int|string`. Повторный CI и production-деплой ещё не проверены. Локальные PHP/Composer/Docker недоступны. [Постановка](backlog-2026-11-01.md#bak-01--отмена-ошибочного-возврата--p0).

- **BAK-15:** очищенная история отправлена в `origin/main` (`acb636c`): два исходных XLSX удалены из новой истории `main`, исторический фиксированный локальный `APP_KEY` заменён. `tests`, `phpunit.xml` и CI workflow не изменены. GitHub Actions run `41281724330` завершился успешно: тесты, Pint, PHPStan, Deptrac, Trivy, сборка, smoke test и production deploy прошли. Проверка по Git SSH и GitHub API показала, что закрытые PR №1/№2 продолжают ссылаться на старую историю, а старый SHA `9abe7b3` доступен и содержит оба XLSX. Репозиторий остаётся private; до удаления этих ссылок и кэша публиковать его нельзя. Старые локальные worktree также нельзя сливать в новую `main` без пересоздания истории. [Постановка](process/BAK-15-public-repo.md).
- **BAK-14 выполнен после восстановления:** пользователь удалил старый checkout, `.env`, PHP и PostgreSQL-контейнеры; прежний релиз зависел от них, HTTPS `/health` отдавал 502. Коммит `a550ba6` отправлен в `main`, CI успешно восстановил все три сервиса из `/opt/bakery/compose.yml` без исходников, `build` и `.env` на сервере. На хосте запущен образ ревизии `a550ba6`, PHP и PostgreSQL healthy, HTTPS `/health` и `/login` — 200; CSS совпадает с файлом образа. Сохранены прежние тома `laravel-app_postgres_data` и `laravel-app_caddy_data`; в БД доступны 75 пользователей, 220 заказов и 17 сессий. Пользователь подтвердил, что вход работает. Проверенный `pg_dump -Fc` от 2026-11-01 05:20:48 UTC (109 296 байт, SHA-256 `309699af5cfa74f58affe41c14b8f1daee3610af716811f7482a4f2482872283`) находится на хосте и на этом ПК; `pg_restore -l` прошёл. [Постановка](process/BAK-14-release.md), [инцидент](history/HIST-006.md).

- **BUG-08:** на рабочем хосте `backery` (код `565a885`) web-сессии уже лежат в PostgreSQL, `APP_KEY` файла и контейнера совпадает, но фактический срок бездействия — 120 минут (3 из 8 сессий активны за это время). В `origin/main` отправлен `c8e2845`: срок Laravel по умолчанию увеличен до 30 дней; Compose и `.env.example` не менялись. CI на этом коммите обнаружил ошибку нового теста (302 после повторного шифрования cookie) и предупреждение PHPStan о nullable-значении cookie. Тест исправлен; повторный CI ещё не проверен. Production не менялся. [Диагностика](process/bugs/BUG-08.md).

- **BUG-07 / BAK-13:** пользовательский журнал Pint на `93cd359` показал одну ошибку стиля в `AbstractGenerator.php`: после удаления Elasticsearch-хелперов остался неиспользуемый импорт `DomainContext`. Импорт удалён. Аудит нашёл сохранявшиеся вызовы Elasticsearch/Redis в `/health`, Octane, middleware, сервис-провайдере и конфигурации; они удалены вместе с прежними тестами и прямой Composer-зависимостью. Mailpit в коде и Compose не найден. Проверка `rg` по `app/`, `bootstrap/`, `config/`, `routes/`, `database/`, Compose и CI не нашла этих интеграций; JSON и content-hash `composer.lock` сверены. Локальный Docker/PHP недоступен; Pint, PHPStan, тесты, CI и production-деплой ещё не проверены для этой правки. Исправление `d19c1c7` отправлено в `origin/main` вместе с документационным коммитом `620f8ae`.
- **BUG-06 / BAK-13:** пользовательский журнал CI на `0655758` подтвердил две ошибки PHPStan `trait.unused` после отключения Elasticsearch. Оба трейта больше не использовались рабочим кодом; `InteractsWithElasticsearch` оставался только в необязательном генераторе и тесте. Трейты и устаревший флаг `--with-elasticsearch` удалены, связанные тесты и документация приведены к текущему стеку. Локально выполнен `git diff --check`; Docker/PHP недоступны, PHPStan и полный CI ещё не повторялись. Исправление `93cd359` отправлено в `origin/main`; CI на нём и production-деплой ещё не проверены.
- **BAK-13:** подготовлен единый `docker-compose.yml` для локального и рабочего окружений: Caddy, PHP, PostgreSQL; `docker-compose.prod.yml` удалён. Redis, Elasticsearch, Mailpit, worker и scheduler убраны из Compose, CI test job использует только PostgreSQL. Сессии и кеш переведены на БД, очередь синхронная, поиск пользователей выполняется SQL без Elasticsearch. CI дополнен проверкой состава Compose. `git diff --check` прошёл; Docker/PHP на этом ПК недоступны, поэтому Compose, тесты и `composer pre-push` локально не запускались. Кодовый коммит `40a5b2a` отправлен в `origin/main`; CI на нём и production-деплой ещё не проверены. Критерии приёмки и оставшиеся проверки: [BAK-13](backlog-2026-11-01.md#bak-13--единый-compose-для-локального-и-рабочего-окружений--p2).
- **CI / BUG-03:** после push `ee9eedb` PHPStan в CI сообщает 2 ошибки: `dough_grams` в `ProductionCalculator::withDoughType()` и приведение `id` в `RecipeWebController`. Вычисление типа теста вынесено из `??=` в явный результат; тип ID ингредиента уточнён. Локальный PHP по указанию пользователя не запускался, браузер не использовался. Повторный CI ещё не проверен; production-деплой не подтверждён.
- **CI / BUG-05, BUG-03:** по журналам пользователя ParaTest прошёл 191 из 192 тестов; `test_flag_off_pending_user_passes` получил 403 из-за случайной роли `user` в фабрике при отдельном ограничении `legacy-api`. Роль в четырёх сценариях теста закреплена как `manager`. Приложенный журнал PHPStan содержит 17 ошибок типизации рецептур и выбора продукта; уточнены PHPDoc и проверки типов в рамках открытого BUG-03. Локально выполнен `git diff --check`; PHP по указанию пользователя не запускался, браузер не использовался. Повторные ParaTest, PHPStan и полный CI ещё не проверены; состояние push и production-деплоя указать по факту.
- **CI / BUG-01:** по сообщению о падении Deptrac на `670aea7` установлен прямой импорт `Infrastructure\\Exports\\PeriodReportWorkbook` из `Presentation\\Http\\Controllers\\AdminWebController`. В отдельном worktree контроллер переведён на интерфейс Application, реализация привязана в `BakeryServiceProvider`; локальный кодовый коммит `fe6b5eb`. Проверка `git diff --check` прошла; на этом ПК недоступны PHP и Docker, поэтому Deptrac, `composer pre-push` и тесты пока не запускались. Push, CI и production-деплой на момент записи не выполнялись.
- **Now:** коммит `37126e2` отправлен в `origin/main`. CI снова упал на Pint: из 277 файлов остался один с проблемой PHPDoc в `IngredientCatalog.php` (BUG-04). Добавлены разделитель после `@template` и требуемый пробел у `@param`; повторный CI ещё не проверен. Отчёты Trivy FS/Image по BUG-02 сохраняются как артефакты workflow; результат security jobs ещё не подтверждён. Реализация BAK-01…BAK-12 не начата.
- **BUG-03:** исправления PHPStan из коммита `5d74858` теперь в `main`; повторный PHPStan и CI ещё не проверены. Локальный PHP/Composer/Docker недоступен.
- **Next:** BAK-01 — отмена ошибочного возврата; затем BAK-02 — итоги и комментарии в списке принятых заказов. Очередь: [roadmap](process/roadmap.md), подробности: [backlog](backlog-2026-11-01.md).
- **Blocked:** подтверждённых блокеров для записи плана нет. Вопросы, требующие решения перед отдельными задачами, перечислены в бэклоге.
- **Verification:** `git diff --check` прошёл для `37126e2`, push в `origin/main` успешен; CI на этом коммите подтвердил один оставшийся style issue в `IngredientCatalog.php`. PHP, Composer и Docker на этом ПК недоступны; локальный Pint, `composer pre-push`, PHPStan, Deptrac и тесты не запускались. Новая PHPDoc-правка проверяется отдельно; production-деплой не подтверждён. Проверка приложения, печати и Excel для BAK-задач ещё не выполнялась.

Новые краткие статусы добавлять сюда с датой и ссылкой на ID. Исторические записи ниже не описывают текущее состояние рабочего дерева или production.

## Архивные записи

## 2026-10-06 — сайт заказов пекарни

В домене `Bakery`, созданном через `make:domain`, добавлены магазин, товар, персональная цена, заказ и снимки позиций в PostgreSQL. Есть вход без регистрации, мобильная форма магазина с двухшаговым подтверждением, отсечка 18:30 Europe/Moscow, идемпотентность запроса и SQL-ограничение одного принятого заказа на дату. Отмена сохраняет историю и исключает заказ из сводки и печати. Панель пекарни содержит заказы по дате, недозаказавшие магазины, матрицу количеств, справочники, аккаунты и цены. Накладные печатаются из Blade. Добавлены демоданные и строгий CSV-импорт согласованного каталога.

Дополнено 2026-10-06: срок оформления проверяется сервером и не зависит от переданного клиентом времени (проверено запросом с подменёнными заголовками и полями). У товара есть редактируемый минимум в штуках, ноль по-прежнему означает отсутствие позиции; минимум проверяется при предварительном просмотре и в финальной транзакции. Сохранены кнопки печати, рядом добавлена выгрузка одной или всех принятых накладных в `.xlsx` со снимками цен и сумм. Файл создаётся встроенным PHP PharData и проверен независимым чтением через openpyxl.

Образцы `resources/data/дубль.xlsx` и `resources/data/Накладные 10.09.xlsx` просмотрены. Заказчик подтвердил «Лист1» второго файла как источник начального каталога и цен. Команда `bakery:seed-reference` загружает 72 магазина, 23 товара, 1649 положительных цен и создаёт 72 логина `store-001@bakery.test` … `store-072@bakery.test` с начальным паролем `changeme`; 7 нулевых цен означают недоступный товар. Повторный запуск сохраняет изменённые пароли. XLSX выгрузка содержит горизонтальные накладные и матрицу «Заказ» с итогами. Приняты рабочие допущения: один заказ на дату, повтор после отмены допустим, отмена только до 18:30 дня оформления, поставки ежедневно, отгрузка равна заказу.

Для первого развёртывания: `migrate --force`, `bakery:seed-reference`, интерактивная `bakery:create-admin email` со скрытым вводом пароля. Демо-сидер в рабочей среде не нужен.

Локальный URL: `http://localhost:8099/login`. Порт PostgreSQL не публикуется на хосте из-за конфликта `5432`. Перед загрузкой «Лист1» сохранён дамп `storage/app/bakery-before-reference-20261007.dump` (47 420 байт). Справочник загружен в локальную БД; повторный запуск не создал аккаунтов. Отдельная тестовая БД `laravel-database-test` создана. `composer pre-push` на ней прошёл: 162 теста, 385 утверждений, PHPStan и Deptrac без ошибок. XLSX открыт через openpyxl: два листа и совпадающие итоги. После перезапуска PHP `/login` возвращает 200, `/admin` для гостя перенаправляет на `/login`. В браузере ранее проверены вход, мобильная форма 390 px, подтверждение, локальный заказ №5, сводка и HTML-накладная. Системный предпросмотр печати не удалось проверить из-за зависания диалога браузера; CSS `@media print` подготовлен для A4 и неделимых накладных.

> Этот файл ведёт агент. Человек читает, но не редактирует вручную.

## Исторический Now (каркас Laravel, 2026-06)

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
