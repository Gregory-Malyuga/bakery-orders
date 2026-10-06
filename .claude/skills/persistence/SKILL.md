---
name: persistence
description: >-
  Работа с хранилищем данных в Suggester: Eloquent-модели, Repository-классы,
  Elasticsearch-индексы и запросы, Redis-кеш, миграции.
  Триггер: "создать миграцию", "Repository", "Elasticsearch", "индекс", "закешировать".
  НЕ триггер: HTTP endpoint, бизнес-логика без I/O.
---

# Persistence Skill

## Elasticsearch (основной движок Suggester)

### Клиент

Использовать `elasticsearch/elasticsearch` через DI — не через `app()` напрямую.

```php
class SuggestRepository implements SuggestRepositoryInterface
{
    public function __construct(private readonly Client $es) {}

    public function suggest(string $query, int $size = 10): Collection
    {
        $response = $this->es->search([
            'index' => 'suggestions',
            'body'  => [
                'suggest' => [
                    'text' => $query,
                    'phrase-suggest' => ['phrase' => ['field' => 'text', 'size' => $size]],
                ],
            ],
        ]);

        return collect($response['suggest']['phrase-suggest'][0]['options'] ?? [])
            ->map(fn (array $option) => new SuggestDto($option['text'], $option['score']));
    }
}
```

### Индекс и маппинг

Маппинг хранить в `database/elasticsearch/<index>.json` или в `artisan`-команде.
При изменении маппинга — **реиндексация**, не редактирование существующего (ES не позволяет менять тип поля).

## Eloquent

### Правила

- Модели в `app/Models/` — только схема, `$fillable`, `$casts`, связи
- Все запросы — в Repository, не в Controller/Service
- Всегда `select()` нужные поля — не `SELECT *` в тяжёлых листингах
- Eager loading: `with()` в Repository, не lazy loading в цикле (N+1)

### Миграции

```bash
docker compose exec -T php sh -c "cd /var/www/app && php artisan make:migration create_<name>_table"
docker compose exec -T php sh -c "cd /var/www/app && php artisan migrate"
```

Никогда не редактировать применённую миграцию — создавать новую.

## Redis

Кешировать в Repository, не в Service:

```php
return Cache::remember("suggest:{$query}", 300, fn () => $this->fetchFromEs($query));
```

TTL для подсказок: не более 5 минут (данные меняются часто).

## Интерфейс Repository

Всегда определять интерфейс рядом с реализацией:

```
app/Repositories/
  SuggestRepositoryInterface.php
  ElasticsearchSuggestRepository.php
```

Привязка в `AppServiceProvider::register()`:
```php
$this->app->bind(SuggestRepositoryInterface::class, ElasticsearchSuggestRepository::class);
```

## Reference

- Запреты: `docs/process/forbidden.md`
- TDD для Repository: `docs/process/tdd.md §3`
