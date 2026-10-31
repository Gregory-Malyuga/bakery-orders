# Roadmap

## В работе

### Рефакторинг MakeDomainCommand → Generator-классы

**Цель:** разбить монолитный `MakeDomainCommand` (1 500 строк) на отдельные Generator-классы — по одному на каждый генерируемый тип файла. Это устраняет три известных бага и делает генератор дебажабельным.

**Известные баги, которые закрывает рефакторинг:**

| # | Баг | Симптом |
|---|-----|---------|
| B-1 | `assert($id !== null)` без `;` в `stubController()` (строка 1018) | Сгенерированный контроллер не проходит `php -l` |
| B-2 | Двойной вызов `migrate` — в `generateMigration()` и в `handle()` | Миграция гоняется дважды при каждом запуске |
| B-3 | `$this->ns` — mutable state на уровне команды | Команда не переиспользуема, генераторы нельзя тестировать изолированно |

**Целевая структура:**

```
app/Shared/Console/
  Commands/
    MakeDomainCommand.php           ← тонкий оркестратор
  DomainGenerator/
    Context/
      DomainContext.php             ← readonly DTO: name, ns, table, plural, fields, options
    Contracts/
      GeneratorInterface.php        ← generate(DomainContext, Filesystem): void
    Support/
      FieldParser.php               ← parseFields / inferType / typeDefinition / fakerForField
      TestValueHelper.php           ← testValueFor()
    Generators/
      Domain/
        ModelGenerator.php
        FactoryGenerator.php
        EventGenerator.php          ← Created / Updated / Deleted (loop внутри)
        NotFoundExceptionGenerator.php
      Application/
        Data/
          CreateDataGenerator.php
          UpdateDataGenerator.php
          ResourceGenerator.php
          FilterDataGenerator.php
        Commands/
          CreateCommandGenerator.php
          CreateHandlerGenerator.php
          UpdateCommandGenerator.php
          UpdateHandlerGenerator.php
          DeleteCommandGenerator.php
          DeleteHandlerGenerator.php
        Queries/
          ListQueryGenerator.php
          ListHandlerGenerator.php
          FindByIdQueryGenerator.php
          FindByIdHandlerGenerator.php
      Infrastructure/
        RepositoryGenerator.php
        CacheWarmerGenerator.php
      Presentation/
        ControllerGenerator.php
        OpenApiGenerator.php
        ListRequestGenerator.php
        StoreRequestGenerator.php
        UpdateRequestGenerator.php
      Providers/
        ServiceProviderGenerator.php
      Database/
        MigrationGenerator.php
      Tests/
        RepositoryTestGenerator.php
        ApiTestGenerator.php
```

**Инвариант:** `MakeDomainCommand::handle()` не содержит ни одной строки, генерирующей PHP-код. Только: парсинг аргументов → создание `DomainContext` → вызов генераторов → регистрация провайдера → `migrate` (один раз).

**Что НЕ меняется:** публичный интерфейс команды (`make:domain Name fields... --flags`) остаётся идентичным, все существующие тесты должны пройти без изменений.

---

#### Шаги

**Фундамент**

- [x] 1.1 `GeneratorInterface` — `generate(DomainContext $ctx, Filesystem $files): void`
- [x] 1.2 `DomainContext` — readonly DTO (name, ns, table, plural, fields, basePath, unitTestPath, featureTestPath, withElasticsearch, withCacheWarmer)
- [x] 1.3 `FieldParser` — вынести `parseFields()`, `inferType()`, `typeDefinition()`, `fakerForField()`
- [x] 1.4 `TestValueHelper` — вынести `testValueFor()`

**Domain-генераторы**

- [x] 2.1 `ModelGenerator`
- [x] 2.2 `FactoryGenerator`
- [x] 2.3 `EventGenerator` (генерирует Created / Updated / Deleted в одном классе)
- [x] 2.4 `NotFoundExceptionGenerator`

**Application / Data**

- [x] 3.1 `CreateDataGenerator`
- [x] 3.2 `UpdateDataGenerator`
- [x] 3.3 `ResourceGenerator`
- [x] 3.4 `FilterDataGenerator`

**Application / Commands**

- [x] 4.1 `CreateCommandGenerator`
- [x] 4.2 `CreateHandlerGenerator`
- [x] 4.3 `UpdateCommandGenerator`
- [x] 4.4 `UpdateHandlerGenerator`
- [x] 4.5 `DeleteCommandGenerator`
- [x] 4.6 `DeleteHandlerGenerator`

**Application / Queries**

- [x] 5.1 `ListQueryGenerator`
- [x] 5.2 `ListHandlerGenerator`
- [x] 5.3 `FindByIdQueryGenerator`
- [x] 5.4 `FindByIdHandlerGenerator`

**Infrastructure**

- [x] 6.1 `RepositoryGenerator`
- [x] 6.2 `CacheWarmerGenerator`

**Presentation**

- [x] 7.1 `ControllerGenerator` (исправить B-1: добавить `;` к `assert`)
- [x] 7.2 `ListRequestGenerator`
- [x] 7.3 `StoreRequestGenerator`
- [x] 7.4 `UpdateRequestGenerator`
- [x] 7.5 `OpenApiGenerator`

**Providers**

- [x] 8.1 `ServiceProviderGenerator`

**Database**

- [x] 9.1 `MigrationGenerator` (убрать `$this->call('migrate')` внутри — исправить B-2)

**Tests**

- [x] 10.1 `RepositoryTestGenerator`
- [x] 10.2 `ApiTestGenerator`

**Оркестрация**

- [x] 11.1 Переписать `MakeDomainCommand::handle()` — только оркестрация, удалить все `stub*`-методы и `$this->ns` (исправить B-3)
- [x] 11.2 `MakeDomainCommandTest` — 27/27 passed ✓

---

### Стабилизация тест-инфраструктуры MakeDomainCommand (2026-06-12)

Аудит пяти экспертов (DevOps, System Architect, Senior PHP, PHPUnit Expert, System Designer).  
Блок 27 тестов сейчас ~5 с на ParaTest 2-CPU; constraint ≤ 15 с выполнен.  
Задачи ниже устраняют fragility и silent failures.

#### TODO

- [x] **T-1** `MakeDomainCommandTest.php` — small · **приоритет 1** ✓  
  Заменить хардкод `'v1\/stub-isos'` в `removeFromGlobalFiles()` на  
  `Str::kebab(Str::plural(static::$domainName))`.

- [x] **T-2** `phpunit.xml` — small · **приоритет 2** ✓  
  Добавить `<testsuite name="DomainGen"><directory>tests/Unit/Shared/Console</directory></testsuite>`.

- [ ] **T-3** `MakeDomainCommandSharedTest.php` — medium · **приоритет 3** ⚠️ отложено  
  Перевод на `setUpBeforeClass()` нереализуем без рефактора: `Artisan::call()` требует  
  загруженного контейнера, который создаётся в `setUp()` инстанса — в статическом хуке недоступен.  
  Текущий `static $domainReady` + guard в `setUp()` работает корректно; race-window закрыт  
  хирургическим удалением в MakeDomainCommandTest.

- [x] **T-4** `MakeDomainCommandTest.php` — small · **приоритет 4** ✓  
  Убрать второй artisan-вызов в `test_provider_registration_is_idempotent`.

- [x] **T-5** `MakeDomainCommandTest.php` — small · **приоритет 5** ✓  
  Переименовать вложенный тестовый домен `StubGroup/StubGen` → `StubGroup/StubNested`.

#### Ревью (2026-06-12) — выявленные доработки

После ревью группой из 5 экспертов применены:

- [x] **AI-1** `phpunit.xml` — добавить `<exclude>tests/Unit/Shared/Console</exclude>` в Unit suite; DomainGen остаётся единственным контейнером для Console/-тестов — устранён двойной прогон и race на routes/providers ✓
- [x] **AI-2** `MakeDomainCommandTest.php` — завершить T-1: providers-regex динамизирован через `preg_quote(static::$domainName)` (был хардкод `StubIso`) ✓
- [x] **AI-3** `MakeDomainCommandTest.php` — восстановить второй artisan-вызов в `test_provider_registration_is_idempotent`; один вызов не тестирует idempotency ✓
- [x] **AI-4** `MakeDomainCommandTest.php:116` — кросс-платформенный path separator в `str_replace` для `--path` migrate ✓
- [x] **AI-6** `phpunit.xml` — документирующий комментарий к DomainGen suite ✓
- [x] **AI-7** `MakeDomainCommandTest.php` — `preg_quote` для `$routePrefix` в routes-regex ✓
- [x] **AI-8** `StubGenTestCase.php` — убрать мёртвое `(?:Stub|Bench)` → `(?:Stub)` в stripTestArtifacts ✓
- [x] **AI-9** `MakeDomainCommandTest.php` — `->middleware(...)` сделан опциональным (`(?:->middleware\([^)]*\))?`) в routes-regex removeFromGlobalFiles ✓
- [x] **AI-5** `MakeDomainCommandTest.php` — `test_nested_domain_uses_parent_namespace` переведён с snapshot-restore на хирургическое удаление StubNested-записей из routes/providers ✓

#### Ожидаемое время после всех правок

| Режим | До | После |
|---|---|---|
| Sequential | ~22 с | ~5.2 с |
| ParaTest 2-CPU | ~5.2 с | ~4.0 с |

---

### Post-refactor: баги и улучшения по ревью (2026-06-12)

Полное ревью рефактора `MakeDomainCommand` выявило 15 находок. Сгруппированы по приоритету.

#### Высокий приоритет — ломают генерируемый код

- [x] **R-1** `TestValueHelper::valueFor()` — нет ветки для `phpType='array'` → возвращает `'Test Meta'` вместо `[]` → тест с json-полем падает ✓
- [x] **R-2** `ControllerGenerator` — заменить `assert($id !== null)` на `throw new \RuntimeException(...)` ✓
- [x] **R-3** `MakeDomainCommand::appendRoutes()` — добавить проверку `if (! $this->files->exists($file)) return;` ✓

#### Средний приоритет — тихие неверные результаты

- [x] **R-4** `MakeDomainCommand::formatGenerated()` — заменить regex на `$ctx->table` ✓
- [x] **R-5** `ApiTestGenerator` — явная проверка `assertGreaterThan(1, count($data))` перед sort-assertions ✓
- [x] **R-6** `MakeDomainCommand::registerProvider()` — использовать полный FQCN в `str_contains` ✓
- [x] **R-7** `TestValueHelper::valueFor()` и `FieldParser::fakerForField()` — синхронизированы: добавлены ветки для `address/city/country/url/title/description` ✓

#### Низкий приоритет — технический долг

- [x] **R-8** ES-блок вынесен в `AbstractGenerator::esImports/esInterface/esTrait()` ✓
- [x] **R-9** `MigrationGenerator::generate()` — использует `$this->writeFile()` ✓
- [x] **R-10** `MakeDomainCommand::runningUnderPhpUnit()` — задокументировано семантическое расхождение с `app()->environment('testing')` ✓
- [x] **R-11** Вывод команды восстановлен: `writeFile()` логирует CREATE/SKIP через Closure, `appendRoutes()`/`registerProvider()` используют `line()`/`warn()` ✓
- [x] **R-12** `MakeDomainCommand::registerProvider()` — `str_replace('];')` заменён на `strrpos()`-замену последнего вхождения ✓
- [x] **R-13** `FieldParser::typeDefinition()` для `timestamp/datetime` — `nullable=true`, правило `'nullable'` добавлено; `MigrationGenerator` генерирует `->nullable()` для всех nullable-полей ✓
- [x] **R-14** `ControllerGenerator` — `$createArgs`/`$updateArgs` объединены в `$args` ✓
- [x] **R-15** `MakeDomainCommand::createDirectories()` удалён; `AbstractGenerator::writeFile()` сам вызывает `ensureDirectoryExists(dirname($path))` ✓

---

### Мелкие технические долги (после аудита 2026-06-08)

- [x] `QueryHandlerInterface::handle(): object|null` — убрать `|null`, привести к `object` ✓
- [x] `MakeDomainCommand::stubController()` — добавить `assert($id !== null)` после `dispatch()` в `store()` ✓
- [x] `LogoutHandler` — перенести `PersonalAccessToken::whereKey()->delete()` в `UserRepositoryInterface::deleteToken(int $id): void` (Application не должен знать про Sanctum)
- [x] `UserRepositoryInterface::list()` — интерфейс уже использует `Data`, override из `UserRepository` удалён, `@phpstan-ignore` убран ✓
- [x] ADR — зафиксировать ~10 решений из `docs/state.md` без ADR (Auth→User слияние, `UserDeleted` без `SerializesModels`, async ES-sync, `UserNotFoundException`→404 и др.)
- [x] `composer.json` — убрать `@no_additional_args` из scripts (нестандартный токен, передаётся артизану буквально)

## Сделано

### CQRS: команды не возвращают сущности ✓

Завершено 2026-06-08.

- `CreateUserHandler`, `RegisterHandler` → `return int $id`
- `UpdateUserHandler`, `DeleteUserHandler`, `LogoutHandler` → `return null`
- Контроллеры делают `ask(FindUserByIdQuery($id))` после dispatch
- `MakeDomainCommand` стабы обновлены
- `RegisterHandlerTest` проверяет `assertIsInt`

### HandlerInterface → CommandHandlerInterface / QueryHandlerInterface ✓

Завершено 2026-06-08. Подробности: ADR-0004.

- `HandlerInterface` удалён
- `CommandHandlerInterface`: `handle(): ?int`
- `QueryHandlerInterface`: `handle(): object`
- Все 8 хэндлеров доменов обновлены
- Bus-тесты обновлены

### OpenAPI-генератор: input/output DTO ✓

Завершено 2026-06-08.

- `{Name}Resource` → response schema
- `Create{Name}Data` → POST requestBody
- `Update{Name}Data` → PUT requestBody
- `safeClassExists()` для защиты от Laravel ErrorException (HIST-004)

### Инфраструктура: ES, .env.example, composer setup ✓

Завершено 2026-06-08.

- `elasticsearch:setup` добавлен в `composer setup`
- `.env.example` отражает реальный Docker-стек
