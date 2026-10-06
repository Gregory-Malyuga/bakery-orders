# Доменные события

Изменения состояния публикуются через нативную событийную систему Laravel.

См. также: [CQRS](cqrs.md), [Cross-module](cross-module.md).

---

## Где живут

Каждый домен держит три события в `Domain/Events/`:

```
{Name}Created
{Name}Updated
{Name}Deleted
```

Событие — тонкий класс с моделью внутри (трейт `Dispatchable`):

```php
namespace Domains\User\Domain\Events;

class UserCreated
{
    use Dispatchable;

    public function __construct(public readonly User $user) {}
}
```

---

## Кто диспатчит

Только **командные хендлеры** в `Application/`, после успешной записи через Repository:

```php
public function handle(object $message): mixed
{
    assert($message instanceof CreateUserCommand);

    $record = $this->repository->create([...]);
    UserCreated::dispatch($record);

    return $record;
}
```

Query-хендлеры событий не диспатчат.

---

## Правила

- Событие диспатчится из хендлера, не из модели и не из контроллера.
- Имя — в прошедшем времени (`Created`, не `Create`).
- Слушатели (listeners) регистрируются в провайдере того домена, который реагирует.
- Для межмодульной реакции слушатель живёт в домене-подписчике — см. [Cross-module](cross-module.md).

> Outbox / транзакционная гарантия доставки на текущем этапе не реализованы: события — синхронные Laravel-события. Если появится требование надёжной асинхронной доставки между сервисами, паттерн нужно оформить отдельным ADR.
