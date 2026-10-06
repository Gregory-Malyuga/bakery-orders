# ADR-0003 — BaseFilterBuilder: fallback WHERE field = value

**Status:** accepted

---

## Context

Домены создаются через `php artisan make:domain`, который генерирует полный CQRS-стек
(Model, DTO, Commands, Queries, Handlers, Controller, тесты).  
Фильтры домена описываются через `FilterData` — Spatie LaravelData DTO с публичными свойствами.

Для применения фильтров к Eloquent-запросу используется `BaseFilterBuilder::apply()`.

---

## Drivers

- Скаффолдинг должен давать рабочий endpoint «из коробки» без ручной правки после `make:domain`.
- Большинство фильтров — прямое сравнение `WHERE field = value`, кастомные фильтры — меньшинство.
- Явная регистрация каждого поля в `filterMap` — избыточный бойлерплейт при N полях.

---

## Options

1. **Явный filterMap** — каждое поле домена регистрируется в `$filterMap` вручную.  
   Надёжно, но требует ручного шага после каждого `make:domain`.

2. **Автоматический fallback** — `BaseFilterBuilder` применяет `WHERE Str::snake($key) = $value`
   для полей, не указанных в `$filterMap`.  
   Кастомная логика (LIKE, range, enum-cast) переопределяется точечно через `filterMap`.

---

## Decision

Принят вариант **2**.

`BaseFilterBuilder::apply()` итерирует публичные свойства `FilterData` через Reflection.  
Для каждого ненулевого свойства:

- если `$filterMap[$key]` задан → делегирует в `FilterInterface::apply()`;
- иначе → применяет `$query->where(Str::snake($key), $value)`.

Это **намеренное поведение**, а не fallback-«заглушка».  
После `make:domain Product name:string price:float is_active:boolean` фильтрация
по `?name=...&is_active=1` работает без правок.

---

## Consequences

**Плюсы:**
- Новый домен — рабочая фильтрация немедленно.
- `filterMap` нужен только для нестандартных операций (LIKE, диапазон, JSON-поле и т.п.).

**Риски и ограничения:**
- Если в `FilterData` появляется служебное/вычисляемое свойство, оно попадёт в WHERE.  
  Решение: объявить его `protected`/`private` или добавить явную проверку в конкретном билдере.
- Имена свойств (`camelCase`) преобразуются через `Str::snake()` → должны соответствовать именам колонок.
- Reflection без кэша: на горячем пути с большим числом запросов может добавить накладные расходы.  
  При необходимости — добавить статический кэш результатов `getProperties()`.
