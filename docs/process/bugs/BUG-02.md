# BUG-02 — SARIF upload падает в CI

**Статус:** прежняя попытка с `actions: read` не устранила сбой; новое исправление ожидает проверки в CI

**Приоритет:** P1

**Дата обнаружения:** 2026-11-01

## Наблюдение

- Среда: GitHub Actions, job `Security / Trivy FS` в `.github/workflows/ci.yml`.
- Воспроизведение: запустить CI и дождаться шага `github/codeql-action/upload-sarif@v3` после создания `trivy-fs.sarif`.
- Ожидалось: SARIF загружен в Code Scanning, job продолжает проверку секретов.
- Получилось: после `Post-processing sarif files: ["trivy-fs.sarif"]` шаг завершается ошибкой `Resource not accessible by integration` при вызове API получения workflow run.
- Влияние: job проверки файловой системы помечается неуспешным; результаты сканирования не публикуются. Ошибка не относится к сборке PHP.
- Источник: переданный пользователем фрагмент журнала CI.
- Повторный симптом после добавления `actions: read`: `Code scanning is not enabled for this repository` в шаге `upload-sarif@v3` при обработке `trivy-fs.sarif`. Шаг завершился кодом 1. В журнале также есть предупреждение о прекращении поддержки v3 в декабре 2026 года.

## Диагностика

- В workflow уже есть `security-events: write` и `contents: read`, но отсутствует `actions: read`.
- [Документация GitHub](https://docs.github.com/en/code-security/how-tos/find-and-fix-code-vulnerabilities/integrate-with-existing-tools/upload-sarif-file) указывает `actions: read` для загрузки SARIF в приватном репозитории.
- Новое сообщение GitHub указывает на отключённый Code Scanning; увеличение прав токена не включает эту функцию.

## Исправление и приёмка

- Сохранять оба SARIF-отчёта как артефакты workflow через `actions/upload-artifact@v4`; оставить проверки, падающие при секретах и исправимых CRITICAL уязвимостях. Убрать неиспользуемые права `actions: read` и `security-events: write`.
- Проверить `git diff --check`, затем успешное сохранение обоих артефактов и выполнение обоих security jobs в CI на `main`.
- Функциональность страниц, заказов, печати и выгрузок не затронута.
