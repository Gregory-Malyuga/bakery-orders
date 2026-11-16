# State

## Current — 2026-11-01

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
