---
name: troubleshoot
description: >-
  Диагностика ошибок в проекте Suggester: упавший тест, провал quality gate,
  неожиданное поведение Laravel/Elasticsearch/Kafka/Redis.
  Триггер: что-то упало, ошибка в логах, тест красный, pint/phpstan/phpunit провалился.
  НЕ триггер: архитектурные вопросы, написание нового кода.
---

# Troubleshoot Skill

## Протокол

1. **Открыть `docs/history/checklist.md`** — найти паттерн, совпадающий с симптомом
2. Если паттерн найден → применить описанный фикс
3. Если паттерна нет → диагностировать самостоятельно (шаги ниже)
4. После устранения → `/history` (зафиксировать новый паттерн)

## Диагностика по типу ошибки

### PHPUnit / Pest упал

```bash
docker compose exec -T php sh -c "cd /var/www/app && php artisan test --filter=<FailedTest> -v"
```

Частые причины:
- Нет `RefreshDatabase` в тесте с DB
- Mock не настроен под новую сигнатуру
- Тест не сбрасывает ES-индекс между запусками

### PHPStan level 8

```bash
docker compose exec -T php sh -c "cd /var/www/app && ./vendor/bin/phpstan analyse --level=8 2>&1 | head -50"
```

Частые причины:
- Возвращаемый тип `mixed` из Eloquent → явный `@return` или generic
- `array` без аннотации структуры

### Pint

```bash
docker compose exec -T php sh -c "cd /var/www/app && ./vendor/bin/pint --test 2>&1"
```

Просто применить: `./vendor/bin/pint` (без `--test`).

### Elasticsearch недоступен

```bash
docker compose exec -T php sh -c "curl -s http://elasticsearch:9200/_cluster/health"
```

Если `yellow/green` — проблема в маппинге или запросе, не в соединении.

### Kafka

```bash
docker compose exec -T php sh -c "cd /var/www/app && php artisan tinker --execute=\"app(\App\Services\SuggestService::class)->...\""
```

Проверить, что `KAFKA_BROKERS=kafka:9092` в env контейнера.
