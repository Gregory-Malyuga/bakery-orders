# ADR Index

ADR иммутабельны после статуса `accepted`.
Новое решение = новый ADR; старый помечается `superseded by ADR-NNNN`.

| ID | Заголовок | Статус |
|---|---|---|
| 0000 | Template | draft |
| 0001 | Публичная регистрация пользователей | accepted |
| 0002 | Manager не может создавать и назначать Admin-роль | accepted |
| 0003 | BaseFilterBuilder: fallback WHERE field = value | accepted |
| 0004 | Split HandlerInterface into CommandHandlerInterface and QueryHandlerInterface | accepted |
| 0005 | Auth domain merged into User domain | accepted |
| 0006 | Repository interface in Application layer (ports & adapters) | accepted |
| 0007 | Domain events carry scalar IDs, not Eloquent models | accepted |
| 0008 | Async Elasticsearch sync via ShouldQueue | accepted |
| 0009 | Separate input and output DTOs | accepted |
| 0010 | Domain exceptions mapped to HTTP codes in bootstrap/app.php | accepted |

> Шаблон: `docs/adr/0000-template.md`
>
> **Примечание:** файлы ADR-0001 и ADR-0002 отсутствуют — решения зафиксированы только в индексе.
