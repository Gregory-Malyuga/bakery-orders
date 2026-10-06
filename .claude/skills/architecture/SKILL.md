---
name: architecture
description: >-
  Консультация по архитектуре Laravel MVC в проекте Suggester: куда положить новый класс,
  можно ли так делать по слоям, ревью на нарушение правил из CLAUDE.md §3.
  Триггер: вопросы "куда положить", "можно ли здесь", "не нарушает ли", "правильно ли".
  НЕ триггер: написание конкретного кода, тесты, SQL/ES-запросы.
---

# Architecture Skill

## Правила слоёв (Suggester)

**Controller** (`app/Http/Controllers/`):
- Только: получить запрос → вызвать Service → вернуть Resource/JSON
- Запрещено: бизнес-логика, прямые Eloquent-вызовы, обращение к ES/Redis

**FormRequest** (`app/Http/Requests/`):
- Только: `rules()` + `authorize()`

**Service** (`app/Services/`):
- Бизнес-логика и оркестрация
- Получает зависимости через конструктор (интерфейсы, не конкретные классы)
- Запрещено: Eloquent напрямую, HTTP-запросы, side-effects без Job/Event

**Repository** (`app/Repositories/`):
- Весь I/O: Eloquent, Elasticsearch-клиент, Redis (если кеш — данных, не сессий)
- Возвращает: DTO, Collection, скалярные значения
- Запрещено: бизнес-логика, HTTP

**Model** (`app/Models/`):
- Eloquent-схема, `$fillable`, `$casts`, связи
- Запрещено: бизнес-логика, вызовы внешних сервисов

**Job** (`app/Jobs/`):
- Async side-effects: индексация в ES, публикация в Kafka
- Не смешивать с синхронными операциями

## Контрольные вопросы при ревью

1. Есть ли Eloquent-вызов вне Repository?
2. Есть ли бизнес-логика в Controller?
3. Есть ли прямой вызов другого Service?
4. Есть ли `dd()` / `var_dump()`?

## Reference

- Слои: `CLAUDE.md §3`
- Запреты: `docs/process/forbidden.md`
- ADR по архитектурным решениям: `docs/adr/index.md`
