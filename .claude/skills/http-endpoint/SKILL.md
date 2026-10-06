---
name: http-endpoint
description: >-
  Создание или изменение HTTP API endpoint в Suggester: контроллер, FormRequest,
  Resource, роут, feature-тест. Триггер: "добавить endpoint", "новый API", "GET/POST/PUT роут",
  "изменить контроллер". НЕ триггер: бизнес-логика без HTTP, Repository, модели.
---

# HTTP Endpoint Skill

## Чеклист нового endpoint

- [ ] FormRequest в `app/Http/Requests/` — `rules()` + `authorize()`
- [ ] JsonResource в `app/Http/Resources/` — форматирование ответа
- [ ] Controller в `app/Http/Controllers/` — тонкий, только делегирует
- [ ] Роут в `routes/api.php` с правильным HTTP-методом
- [ ] Feature-тест в `tests/Feature/Http/`

## Шаблон Controller

```php
class SuggestController extends Controller
{
    public function __construct(private readonly SuggestService $service) {}

    public function index(SuggestRequest $request): JsonResponse
    {
        $results = $this->service->suggest($request->validated('q'));
        return SuggestResource::collection($results)->response();
    }
}
```

## Шаблон Feature-теста

```php
it('returns suggestions for valid query', function () {
    $response = $this->getJson('/api/suggest?q=foo');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'text', 'score']]]);
});

it('returns 422 when query is missing', function () {
    $this->getJson('/api/suggest')->assertUnprocessable();
});
```

## Порядок создания (TDD)

1. Feature-тест (падает)
2. Роут (тест доходит до 404 → 500)
3. FormRequest
4. Controller (делегирует в Service)
5. Resource
6. Тест зеленеет

## Reference

- Слои: `CLAUDE.md §3`
- TDD-порядок: `docs/process/tdd.md §5-6`
