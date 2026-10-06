# CQRS

Команды и запросы разделены и проходят через собственные шины в `app/Shared/Bus/`. Реализация — поверх Laravel Service Container (без Symfony Messenger).

См. также: [Onion](onion.md), [Shared](shared-module.md), [Events](events.md).

---

## Интерфейсы

```
Shared/Bus/CommandBusInterface.php   dispatch(object $command): mixed
Shared/Bus/QueryBusInterface.php     ask(object $query): mixed
Shared/Bus/HandlerInterface.php      handle(object $message): mixed
Shared/Bus/BaseCommand.php           маркер команды
Shared/Bus/BaseQuery.php             маркер запроса
```

## Реализации

```
Shared/Bus/LaravelCommandBus.php
Shared/Bus/LaravelQueryBus.php
```

Резолвинг хендлера — **по конвенции имени** через контейнер:

```
{Name}Command  → {Name}Handler
{Name}Query    → {Name}Handler
```

`CreateUserCommand` → `CreateUserHandler`, `FindUserByIdQuery` → `FindUserByIdHandler`. Шина подменяет суффикс `Command`/`Query` на `Handler`, резолвит класс из контейнера и вызывает `handle()`.

---

## Регистрация хендлеров

Каждый домен биндит свои хендлеры в `Providers/{Name}ServiceProvider::register()`:

```php
public function register(): void
{
    $this->app->bind(CreateUserHandler::class);
    $this->app->bind(UpdateUserHandler::class);
    $this->app->bind(DeleteUserHandler::class);
    $this->app->bind(ListUsersHandler::class);
    $this->app->bind(FindUserByIdHandler::class);
}
```

Провайдер регистрируется в `bootstrap/providers.php`.

---

## Правила

- Команды изменяют состояние; возвращают созданную/обновлённую модель (или `null` для delete).
- Запросы не изменяют состояние; возвращают модель или `LengthAwarePaginator`.
- `Command` и `Handler` — `readonly class`.
- Хендлер реализует `HandlerInterface`, первым делом делает `assert($message instanceof ...)`.
- Команда несёт только скалярные/DTO-поля, не `Request`.

---

## Стандартный набор на домен

| Тип | Команда / Запрос | Хендлер |
|---|---|---|
| Create | `Create{Name}Command` | `Create{Name}Handler` |
| Update | `Update{Name}Command` | `Update{Name}Handler` |
| Delete | `Delete{Name}Command` | `Delete{Name}Handler` |
| List | `List{Name}sQuery` | `List{Name}sHandler` |
| FindById | `Find{Name}ByIdQuery` | `Find{Name}ByIdHandler` |

Весь набор генерируется `make:domain`. Query-хендлеры обращаются к `Repository`; команды дополнительно диспатчат [доменные события](events.md).
