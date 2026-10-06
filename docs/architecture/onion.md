# DDD-слои домена

Каждый домен живёт в `app/Domains/{Name}/` и делится на четыре слоя плюс `Providers/` и `Tests/`.

См. также: [CQRS](cqrs.md), [Persistence](persistence.md), [Deptrac](deptrac.md), [Cross-module](cross-module.md).

---

## Структура домена

```
app/Domains/{Name}/
├── Domain/
│   ├── Models/                 Eloquent-модель ({Name}.php, пивот-модели)
│   ├── Events/                 {Name}Created / {Name}Updated / {Name}Deleted
│   ├── Exceptions/             {Name}NotFoundException
│   ├── Enums/                  доменные enum
│   └── Database/Factories/     {Name}Factory
├── Application/
│   ├── Commands/{Action}/      {Action}{Name}Command + {Action}{Name}Handler
│   ├── Queries/{Action}/       {Action}Query + {Action}Handler
│   └── Data/                   {Name}Data, {Name}FilterData (Spatie Data)
├── Infrastructure/
│   ├── Repositories/           {Name}Repository extends BaseRepository
│   └── Cache/                  {Name}CacheWarmer (опционально)
├── Presentation/
│   └── Http/
│       ├── Controllers/        {Name}Controller (тонкий, только bus)
│       └── OpenApi/            {Name}OpenApi (генерируется openapi:generate)
├── Providers/                  {Name}ServiceProvider (bind хендлеров)
└── Tests/
    ├── Unit/                   {Name}RepositoryTest
    └── Feature/                {Name}ApiTest
```

Скаффолд целиком создаётся командой `php artisan make:domain {Name} [fields]` — см. [process/commands.md](../process/commands.md).

---

## Зависимости между слоями

```
Domain          ← изолирован (зависит только от Laravel/Spatie примитивов)
Application     ← Domain + Infrastructure + Shared
Infrastructure  ← Domain + Shared + Elasticsearch
Presentation    ← Application + Domain + Shared
```

Контроль — через [Deptrac](deptrac.md), enforced в `composer pre-push` и CI.

---

## Domain

Чистая модель предметной области.

**Разрешено:** Eloquent-модели, доменные события, исключения, enum, фабрики.

**Запрещено:** импорты из `Application/`, `Infrastructure/`, `Presentation/`; HTTP; прямой доступ к Elasticsearch/Redis; бизнес-логика в моделях.

Модель — тонкая: `$fillable`/`$casts`, отношения, не более. Никаких запросов и оркестрации.

---

## Application

CQRS-хендлеры и DTO. Оркестрирует Domain и Infrastructure.

**Разрешено:** `Command`/`Handler`, `Query`/`Handler`, Spatie Data DTO, вызов `Repository`, dispatch доменных событий.

**Запрещено:** HTTP-объекты (`Request`/`Response`), прямые Eloquent-запросы (только через Repository).

`Command` и `Handler` — `readonly class`. Хендлер реализует `Shared\Bus\HandlerInterface`:

```php
readonly class CreateUserHandler implements HandlerInterface
{
    public function __construct(private UserRepository $repository) {}

    public function handle(object $message): mixed
    {
        assert($message instanceof CreateUserCommand);

        $record = $this->repository->create([...]);
        UserCreated::dispatch($record);

        return $record;
    }
}
```

---

## Infrastructure

Доступ к данным. Возвращает Eloquent-модели.

**Разрешено:** `Repository extends BaseRepository`, обращение к Elasticsearch через `InteractsWithElasticsearch`, кеш-прогрев.

Репозиторий обычно тривиален — вся базовая логика в `Shared\Repository\BaseRepository`:

```php
class UserRepository extends BaseRepository
{
    protected string $model = User::class;
}
```

---

## Presentation

HTTP-слой. Контроллеры тонкие — **ноль бизнес-логики**.

**Разрешено:** диспатч `Command`/`Query` через bus, маппинг через Spatie Data, OpenApi-атрибуты.

**Обязательно:**

```php
class UserController extends Controller
{
    public function __construct(
        private readonly CommandBusInterface $commands,
        private readonly QueryBusInterface $queries,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $dto = UserData::from($request);
        $record = $this->commands->dispatch(new CreateUserCommand(...));

        return response()->json(UserData::from($record), 201);
    }
}
```

**Запрещено:** `new Model(...)`, Eloquent-запросы, любая бизнес-логика в контроллере.
