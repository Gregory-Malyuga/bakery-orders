# /implement

Взять следующую незакрытую задачу из `docs/process/roadmap.md` и реализовать её.

## Шаги

1. Read `docs/state.md` — убедиться, что нет активных Blocker
2. Read `docs/process/roadmap.md` — найти первый `[ ]`
3. Обновить `docs/state.md` → Now: `<task title>` · Next: `<следующая>`
4. Если задача — новый класс/компонент: создать скелет через `artisan make:*` (если уместно)
5. Реализовать по порядку из `docs/process/tdd.md`
6. После прохождения всех тестов: `/pre-push`
7. При успехе: `/wrap-up`
