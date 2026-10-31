# History Index

Реестр инцидентов и неожиданных сбоев.
Записи создаются после установления причины и проверки исправления; открытые ошибки ведутся в [текущем roadmap](../process/roadmap.md). Команда `/history` описывает шаги для Claude Code, общий порядок — в [work-items.md](../process/work-items.md).

## Формат записи

```markdown
## HIST-001 — <краткое название>

**Дата:** YYYY-MM-DD
**Контекст:** Что делали, когда произошло.
**Симптом:** Что именно упало / повело себя неожиданно.
**Причина:** Почему это произошло.
**Фикс:** Что изменили для устранения.
**Паттерн для checklist:** <строка для docs/history/checklist.md>
```

---

## Записи

[HIST-001](HIST-001.md) — l5-swagger сканирует тестовый базовый класс, PHPUnit не найден
[HIST-002](HIST-002.md) — ImportRunCommandTest: SQLSTATE[25P02] в полном прогоне, проходит изолированно
[HIST-003](HIST-003.md) — generate_always + PHP-FPM OPcache: docs регенерируются из устаревшего кеша
[HIST-004](HIST-004.md) — class_exists() бросает ErrorException в Laravel при отсутствии файла
[HIST-005](HIST-005.md) — HandlerInterface::handle(): mixed несовместим с void-реализациями
