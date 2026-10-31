# BUG-02 — SARIF upload не может прочитать workflow run

**Статус:** исправление внесено, ожидается проверка в CI

**Приоритет:** P1

**Дата обнаружения:** 2026-11-01

## Наблюдение

- Среда: GitHub Actions, job `Security / Trivy FS` в `.github/workflows/ci.yml`.
- Воспроизведение: запустить CI и дождаться шага `github/codeql-action/upload-sarif@v3` после создания `trivy-fs.sarif`.
- Ожидалось: SARIF загружен в Code Scanning, job продолжает проверку секретов.
- Получилось: после `Post-processing sarif files: ["trivy-fs.sarif"]` шаг завершается ошибкой `Resource not accessible by integration` при вызове API получения workflow run.
- Влияние: job проверки файловой системы помечается неуспешным; результаты сканирования не публикуются. Ошибка не относится к сборке PHP.
- Источник: переданный пользователем фрагмент журнала CI.

## Диагностика

- В workflow уже есть `security-events: write` и `contents: read`, но отсутствует `actions: read`.
- [Документация GitHub](https://docs.github.com/en/code-security/how-tos/find-and-fix-code-vulnerabilities/integrate-with-existing-tools/upload-sarif-file) указывает `actions: read` для загрузки SARIF в приватном репозитории.
- Ошибка API чтения workflow run согласуется с отсутствующим правом. Подтверждение на текущем CI run ещё требуется.

## Исправление и приёмка

- Добавить `actions: read` к правам workflow для обоих шагов загрузки SARIF: Trivy FS и Trivy Image.
- Проверить `git diff --check`, затем успешное выполнение SARIF upload и обоих security jobs в CI на `main`.
- Функциональность страниц, заказов, печати и выгрузок не затронута.
