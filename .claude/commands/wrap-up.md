# /wrap-up

Закрыть задачу после успешного `/pre-push`.

## Шаги

1. Отметить `[x]` у задачи в `docs/process/roadmap.md`
2. Обновить `docs/state.md`:
   - **Now** → `Idle` или следующая задача
   - **Recent decisions** → если было нетривиальное решение
   - **Last updated** → сегодняшняя дата
   - **Last commit** → `git log --oneline -1`
3. Если в процессе был провал quality gate или неожиданное поведение → вызвать `/history`
4. Выдать отчёт формата из CLAUDE.md §6

## Коммит

```bash
git add docs/state.md docs/process/roadmap.md
git commit -m "chore: wrap up <task title>"
```
