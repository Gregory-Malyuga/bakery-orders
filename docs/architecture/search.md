# Search

Поиск пользователей в текущем приложении выполняет PostgreSQL через `UserRepository` и `EmailFilter`. Контейнер Elasticsearch не входит в локальный или рабочий Compose. Ниже описаны оставшиеся в общем каркасе опциональные компоненты Elasticsearch; для их использования потребуется отдельно поднять сервис и настроить индексацию.

См. также: [Persistence](persistence.md).

---

## Как подключается

Репозиторий, которому нужен ES, реализует интерфейс и подмешивает трейт из `app/Shared/Elasticsearch/`:

```php
class ExampleRepository extends BaseRepository implements ElasticsearchSearchable
{
    use InteractsWithElasticsearch;

    protected string $model = User::class;

    /** @return array<string, mixed> */
    public function buildEsQuery(Data $filters): array
    {
        return ['query' => ['bool' => ['must' => [...]]]];
    }
}
```

`make:domain {Name} --with-elasticsearch` сразу добавляет интерфейс, трейт и заготовку `buildEsQuery()`.

---

## Механика

`InteractsWithElasticsearch` переопределяет хук `postProcessBuilder()` из `BaseRepository`:

```
Repository::list($filters)
  → buildEsQuery($filters)                  собирает тело ES-запроса
  → client->search(index = таблица модели)  выполняет поиск
  → array_column(hits, '_id')               извлекает id
  → $qb->whereIn('id', $ids)                инжектит в Eloquent-запрос
  → orderBy + paginate                      обычный постпроцессинг
```

- Индекс = имя таблицы модели (`(new $model)->getTable()`).
- Пустой результат ES → `whereIn('id', [])` → Eloquent добавит `WHERE 0=1` → ноль строк без спец-обработки.
- Итог всегда — Eloquent-модели и `LengthAwarePaginator`, как у обычного репозитория.

ES-клиент резолвится из контейнера (`app(Client::class)`).
