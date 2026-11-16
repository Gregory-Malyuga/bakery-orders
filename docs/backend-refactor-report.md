# Backend refactor report

## Scope

Branch: `refactor/backend-architecture`  
Target: `main` (review required; **not merged**)

The backend was split from the historical `Bakery` module into separate entity/business-capability domains. The `laravel-app` User domain is the reference pattern: `Application`, `Domain`, `Infrastructure`, `Presentation`, explicit repository ports, Commands/Queries and Handlers.

| Area | Owner domains | Main change |
| --- | --- | --- |
| Identity | User, StoreAccount | Login, impersonation, administrative account commands and store-account membership |
| Commercial | Store, StoreGroup, Product, Price | Entity repositories, price operations and price source rules |
| Store assortment | StoreProductAvailability, StoreProductMinimum | Independent availability and per-store minimum operations |
| Orders | Order, OrderItem, OrderAdjustment, OrderReturn | Order workflows, corrections, return commands, calendar queries |
| Delivery | DeliverySchedule, DeliverySheet | Closed delivery dates, delivery sheet snapshots and print/archive cases |
| Production | Production, Recipe, Ingredient, Label | Recipes, calculations, ingredients and labels |
| Stock | StockItem, StockMovement, StockPrice, StockPriceChange | Items, inventory movements, daily prices and price history |
| Read/reporting | Catalog, Analytics, Invoice, Report | Read-model queries, Excel exports and reporting |

## HTTP/API compatibility

Existing route names and views remain in use. Controllers were split and routed to domain-owned use cases. Domain DTOs extend the shared `BaseData`. The existing `BaseDataCollection` / pagination conventions remain available for API collections; the existing server-rendered list pagination was deliberately preserved.

## Isolation rules

- Commands and Queries live in the owning Application layer.
- SQL and ORM persistence stay in Infrastructure repository implementations.
- Modules request data from other modules via interfaces rather than importing their internal database tables.
- Historical `Domains\\Bakery` aliases and wrappers remain **only for backward compatibility** with existing services/tests; they are not intended as the home for new business logic.
- Some infrastructure read projections join multiple tables for printable reports and inventory valuation. These are intentional read-model compositions; revisit them if requiring strict physical table isolation even for reporting joins.

## Tests and CI

No pre-existing test files were modified. CI includes PHP syntax, Pint, PHPStan, Deptrac, test suite, Composer checks, Trivy and documentation checks.

The last known green CI run at the time of creating this report was for commit `883b8b5`. The final squashed commit must be checked independently before merging to `main`.

## Review and merge

The feature history will be rewritten to one commit on top of the current `main` using force-with-lease on **feature branch only**; the open PR will then show a single commit. Approval and merge to `main` are separate actions and are **not** performed automatically.
