# Deptrac

Контроль зависимостей между слоями. Нарушение блокирует `composer pre-push` и CI.

См. также: [Onion](onion.md), [Shared](shared-module.md).

---

## Запуск

```bash
composer architecture
# = ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --fail-on-uncovered
```

Конфиг — `deptrac.yaml` (в корне). Анализируются `app/Shared` и `app/Domains`.

---

## DDD-слои и правила

Слои выделяются по namespace `Domains\{Name}\{Layer}\...`:

| Слой (deptrac) | Namespace | Кому может зависеть |
|---|---|---|
| `DomainLayer` | `Domains\*\Domain\*` | Laravel, Spatie |
| `ApplicationLayer` | `Domains\*\Application\*` | DomainLayer, InfrastructureLayer, Shared, Laravel, Spatie |
| `InfrastructureLayer` | `Domains\*\Infrastructure\*` | DomainLayer, Shared, Laravel, Elastic, Spatie |
| `PresentationLayer` | `Domains\*\Presentation\*` | ApplicationLayer, DomainLayer, Shared, Laravel, Spatie, OpenApi |
| `DomainProvider` | `Domains\*\Providers\*` | все слои домена + Shared + Laravel |
| `DomainTest` | `Domains\*\Tests\*` | все слои домена + Shared + TestSupport |

Ключевое следствие: **DomainLayer изолирован** (не видит Application/Infrastructure/Presentation), а Presentation **не видит** Infrastructure напрямую — только через Application.

> Замечание: `ApplicationLayer` намеренно может зависеть от `InfrastructureLayer` — командные и query-хендлеры обращаются к `Repository` напрямую.

---

## Shared-слои

| Слой | Namespace | Зависит от |
|---|---|---|
| `Shared` | `Shared\*` (кроме Testing) | Laravel, Elastic, Spatie |
| `TestSupport` | `Shared\Testing\*` | Shared, Laravel, Elastic, Spatie |

`Laravel`, `Elastic`, `OpenApi`, `Spatie` — внешние слои-токены (вендоры), сами ни от кого не зависят.

---

## Легаси-слои

В конфиге сохранены коллекторы под старую плоскую структуру (`App\Http\*`, `App\Models\*`, `App\Services\*`, `App\Jobs\*` и т.п.). После миграции на DDD соответствующего кода в `app/` больше нет — эти слои фактически пустые и подлежат удалению из `deptrac.yaml` при ближайшей чистке.
