# Conventions

## Code Style

**Pint** (`./vendor/bin/pint`) — автоформатирование. Конфиг в `pint.json` (если отличается от Laravel-дефолтов).

```bash
# Проверка без правок
docker compose exec -T php sh -c "cd /var/www/app && ./vendor/bin/pint --test"

# Применить правки
docker compose exec -T php sh -c "cd /var/www/app && ./vendor/bin/pint"
```

**PHPStan level 8** — статический анализ.

```bash
docker compose exec -T php sh -c "cd /var/www/app && ./vendor/bin/phpstan analyse --level=8"
```

Baseline (`phpstan-baseline.neon`) допускается только для унаследованного кода — новый код должен проходить без baseline.

Правила проекта:
- Все публичные методы имеют типизированные параметры и возвращаемый тип
- `mixed` запрещён — уточняй тип
- `array` без generics запрещён там, где знаем структуру — используй `array<int, Dto>` или `Collection<int, Model>`

## Git

**Conventional Commits:**

```
feat: add User CRUD domain
fix: correct email validation in UserData
refactor: move ES query into InteractsWithElasticsearch
test: add pagination cases for UserApiTest
chore: drop legacy App\* layers from deptrac.yaml
```

**Ветки:** `feature/<slug>`, `fix/<slug>`, `chore/<slug>`, `refactor/<slug>` — kebab-case, lowercase.

**Rebase перед MR:** `git rebase origin/main` — никаких merge-коммитов внутри фича-ветки.

**Хуки:**
- `composer pre-push` запускается автоматически хуком pre-push (настроить через `git config`)
- Никогда не обходить через `--no-verify`

**Запрещено:**
- Пушить напрямую в `main`
- `git push --force` на shared-ветках (только `--force-with-lease` на своих)
- Коммитить `.env` и любые секреты
