# TDD — канонический порядок

Тест пишется раньше реализации слоя. Каждый шаг завершён до перехода к следующему. Все команды — внутри контейнера `php` (см. [commands.md](commands.md)).

---

## 0. Скаффолд домена

```bash
php artisan make:domain {Name} field:type ...
```

Создаёт полную DDD-структуру (Model, Data, FilterData, Commands, Queries, Events, Repository, Controller, тесты, миграцию) — см. [commands.md](commands.md). Дальше шаги наполняют сгенерированные заготовки.

---

## 1. Repository-тест (Unit)

`app/Domains/{Name}/Tests/Unit/{Name}RepositoryTest.php` расширяет `Shared\Testing\BaseRepositoryTest` (трейт `HasRepositoryTests` даёт find / findOrFail / create / update / delete / list / pagination / filters).

Заполнить `makeModelData()` / `updateModelData()` реальными полями домена. Запустить — тест **падает** (поля/миграция не готовы):

```bash
php artisan test --filter={Name}RepositoryTest
```

---

## 2. Domain + Infrastructure

В порядке:
1. Миграция `database/migrations/...create_{name}s_table.php` — поля и FK.
2. `Domain/Models/{Name}.php` — `$fillable` / `$casts`, отношения.
3. `Domain/Database/Factories/{Name}Factory.php` — данные для тестов.
4. `Infrastructure/Repositories/{Name}Repository.php` — обычно только `protected string $model`.

Гонять `--filter={Name}RepositoryTest` после каждого шага до зелёного.

---

## 3. Feature-тест API

`app/Domains/{Name}/Tests/Feature/{Name}ApiTest.php` расширяет `Shared\Testing\BaseApiTest` (трейт `HasApiTests`: index / show / show-404 / store / update / destroy / meta).

Задать route-prefix и payload. Запустить — **падает** (нет DTO/команд/контроллера/роута).

---

## 4. Application + Presentation

В порядке:
1. `Application/Data/{Name}Data.php` + `{Name}FilterData.php` — поля и `rules()`.
2. `Application/Commands/{Create,Update,Delete}/*` — `Command` + `Handler` (`readonly`, диспатч событий).
3. `Application/Queries/{ListAll,FindById}/*` — `Query` + `Handler`.
4. `Domain/Events/{Name}{Created,Updated,Deleted}.php`.
5. `Presentation/Http/Controllers/{Name}Controller.php` — тонкий, только bus.
6. `Providers/{Name}ServiceProvider::register()` — bind всех хендлеров; регистрация в `bootstrap/providers.php`.
7. Роуты в `routes/api.php` (или `cp.php` / `crm.php`).

После каждого шага — `php artisan test --filter={Name}ApiTest`.

---

## 5. Quality gate

```bash
composer pre-push
```

Все шаги (lint, pint, phpstan, deptrac, docs:check, test) зелёные перед `/wrap-up`.
