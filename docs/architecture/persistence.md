# Persistence

Доступ к данным — через Eloquent и `Shared\Repository\BaseRepository`. DTO и валидация — через Spatie Laravel Data.

См. также: [Onion](onion.md), [Search](search.md).

---

## Eloquent-модели

Живут в `{Domain}/Domain/Models/`. Тонкие: `$fillable`/`$casts`, отношения, фабрика — без бизнес-логики и без запросов.

```php
namespace Domains\User\Domain\Models;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'role'];
}
```

---

## Repository

Eloquent-запросы разрешены **только** в `Infrastructure/Repositories/`. Базовая логика — в `BaseRepository`:

```php
find($id)        findOrFail($id)        list($filterData)
create($data)    update($id, $data)     delete($id)
newQuery()       postProcessBuilder()   // хук для расширения пайплайна
```

Доменный репозиторий обычно только указывает модель:

```php
class UserRepository extends BaseRepository
{
    protected string $model = User::class;
}
```

`list()` строит запрос через `BaseQueryBuilder` + `BaseFilterBuilder`, прогоняет `BaseQueryBuilderPostprocessor` (orderBy + paginate) и возвращает `LengthAwarePaginator`. Для Elasticsearch хук `postProcessBuilder()` переопределяется трейтом — см. [Search](search.md).

---

## DTO — Spatie Laravel Data

DTO в `Application/Data/` наследуют `Shared\Http\Data\BaseData` (тонкая обёртка над `Spatie\LaravelData\Data`). Один класс закрывает и вход (валидация запроса), и выход (ответ) — заменяет `FormRequest` + `JsonResource`.

```php
class UserData extends BaseData
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $role,
        public readonly ?int $id = null,
        public readonly ?string $created_at = null,
        public readonly ?string $updated_at = null,
    ) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'name' => ['string', 'max:255'],
            'email' => ['string', 'email', 'max:255'],
            // ...
        ];
    }
}
```

- `UserData::from($request)` — валидирует и собирает DTO из запроса.
- `UserData::from($model)` — собирает DTO из модели для ответа.
- `UserData::collect($paginator, PaginatedDataCollection::class)` — коллекция с `data / meta / links`.

`{Name}FilterData` описывает допустимые query-параметры фильтрации (передаётся в `Repository::list()`).

Постраничные ответы и сортировку формируют `Shared\Data\PaginationData` и `Shared\Data\SortData` (`fromRequest()`).

---

## Миграции

`database/migrations/` — append-only. Применённую миграцию не редактируем, создаём новую. `make:domain` генерирует миграцию `create_{name}s_table` автоматически (идемпотентно).

В тестах БД — SQLite, схема поднимается через `RefreshDatabase`.
