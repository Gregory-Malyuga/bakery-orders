# Search

Полнотекстовый/фильтрующий поиск — через Elasticsearch 8 (`elasticsearch/elasticsearch`). Подключается к репозиторию опционально, не меняя его публичный контракт.

См. также: [Persistence](persistence.md).

---

## Как подключается

Репозиторий, которому нужен ES, реализует интерфейс и подмешивает трейт из `app/Shared/Elasticsearch/`:

```php
class UserRepository extends BaseRepository implements ElasticsearchSearchable
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
