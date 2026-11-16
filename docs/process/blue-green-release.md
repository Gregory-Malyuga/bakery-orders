# Blue-Green release / rollback

## Guarantees and limitations

- Two application services: `php` (Blue) and `php-green` (Green), one Caddy, one **unchanged** production PostgreSQL.
- During a normal deploy the active application is not recreated. The candidate is created in the inactive slot, migrations run automatically, and the running Caddy instance is gracefully reloaded to point at the candidate.
- No intentional HTTP downtime during a healthy cutover. Absolute zero downtime is **not** guaranteed: Docker host pressure, heavy SQL locks, in-flight requests or upstream failures can interrupt requests.
- **Rollback changes only the application upstream; it never undoes database migrations or data.** All changes in PostgreSQL, including new orders, are kept.
- Destructive/incompatible schema changes cannot safely accompany this process. Use expand/contract migrations and verify that **both** previous and new PHP versions can read/write the migrated schema.
- Both PHP containers remain running for immediate rollback, which consumes extra RAM (notably FrankenPHP's OPcache). On a ~1 GiB server, check `free -h`, container RSS and swap pressure **before** merging/activating this workflow. If two instances cannot coexist, the requested near-zero-downtime strategy is not safe on this host.

## Deploy via GitHub Actions

1. Merge into `main` only after CI passes. Review migrations for backward compatibility.
2. Open **Actions → Deploy Production (manual) → Run workflow** and choose `operation=deploy`.
3. The workflow verifies successful CI for the exact SHA and executes the mandatory production PostgreSQL backup.
4. The release script acquires an exclusive lock, validates existing volumes and running Caddy, pulls the exact SHA-tagged GHCR image and validates the OCI revision.
5. The script runs `artisan migrate --force --no-interaction` **in a short-lived container of the candidate image**, connected to the same PostgreSQL. A migration failure prevents the traffic switch; however a partially applied migration still requires investigation.
6. The inactive slot starts with the new image. Docker health plus direct `/up`, `/health` and `/login` must all pass.
7. The script edits the **existing bind-mounted Caddyfile in place**, reloads Caddy and checks public HTTPS `/health` and `/login`. If reload or public verification fails, the previous upstream is restored automatically.
8. The previous PHP container is left running as the rollback target. No Caddy or PostgreSQL container is recreated.

The active slot is detected from the actual running Caddy configuration file; it is not inferred from the image tag.

## One-click application rollback

Open **Actions → Deploy Production (manual) → Run workflow**, select `operation=rollback`.

The workflow skips building, migrations and backup, and switches Caddy to the previously deployed healthy slot. It verifies the public HTTPS endpoints and restores the original upstream if verification fails. This only works while the previous slot is still running and healthy; **it does not restore PostgreSQL**.

## First activation: critical operational checks

The current deployment is a single `php` application service. The first Blue-Green run keeps that service as Blue and creates `php-green` as the candidate. The script deliberately **does not run `docker compose up` for the whole stack**, since that would recreate Caddy or the active `php` container.

The existing Caddy container must have its `/etc/caddy/Caddyfile` as a regular, **host-writable bind-mounted file**. The script modifies this file in place to preserve the bind-mount inode and reloads the existing Caddy process. It never replaces that file by an atomic rename. The original release directory containing that bind mount **must not be deleted**, even when later release directories are created.

Check that the release SSH user can write this file and execute `docker exec ... caddy reload`. Check both services resolve each other on `laravel-app_backend`. Also check PostgreSQL's volume remains `laravel-app_postgres_data`.

Do not enable fully automatic promotion to production until a staging rehearsal has verified: normal release, rejection of a broken candidate, failed migration, public-check failure, rollback, sessions, and concurrent order placement.

## Database migration policy

- Adding a nullable column or a new table is generally compatible; verify Laravel code and PostgreSQL locks.
- Avoid dropping/renaming fields or changing required constraints during a release where the old PHP process might still receive traffic.
- For incompatible changes: deploy an additive schema first, switch code, backfill in a separate step, then remove obsolete schema **after the rollback window**.
- Before every deploy, the existing verified PostgreSQL backup remains mandatory. Restoring that backup is a separate disaster-recovery operation and may lose orders written after its creation.

## Limitations

- No distributed coordination across multiple app hosts.
- Existing open connections may complete against the previous upstream; Laravel sessions live in PostgreSQL so either PHP slot can accept the next request.
- File-based Caddy configuration assumes the original Caddy bind mount remains readable and writable. If Caddy configuration or routing architecture changes, revisit this implementation.
- Caddy reload is graceful, but a nonresponsive proxy, OOM kill, node/network failure or database-wide lock may still cause errors.
- Rollback from a migration that breaks old code is **unsafe** even if the former PHP container remains healthy. It is the responsibility of migration review/tests to prevent this situation.
