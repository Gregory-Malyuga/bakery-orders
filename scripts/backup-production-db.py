#!/usr/bin/env python3
"""Create and verify a PostgreSQL dump on the deployment host before release.

The backup is written to the host filesystem, not the PostgreSQL container or
its Docker volume. The deployment fails closed if the dump cannot be verified.
"""

import datetime
import json
import os
from pathlib import Path
import re
import subprocess
import sys

POSTGRES_CONTAINER = "laravel-app-postgres-1"
POSTGRES_VOLUME = "laravel-app_postgres_data"
BACKUP_DIR = Path.home() / "backups" / "bakery-postgres"

PG_DUMP = (
    "docker", "exec", POSTGRES_CONTAINER, "sh", "-ec",
    'export PGPASSWORD="$POSTGRES_PASSWORD"; '
    'exec pg_dump --format=custom --no-owner --no-acl '
    '-U "$POSTGRES_USER" -d "$POSTGRES_DB"',
)
PG_RESTORE_LIST = ("docker", "exec", "-i", POSTGRES_CONTAINER, "pg_restore", "--list")


def check_database_volume() -> None:
    output = subprocess.run(
        ("docker", "inspect", POSTGRES_CONTAINER, "--format", "{{json .Mounts}}"),
        check=True, capture_output=True, text=True,
    ).stdout
    mounts = json.loads(output)
    if not any(
        mount.get("Destination") == "/var/lib/postgresql"
        and mount.get("Name") == POSTGRES_VOLUME
        for mount in mounts
    ):
        raise RuntimeError("PostgreSQL is not using the expected persistent volume")


def backup(commit: str) -> Path:
    if not re.fullmatch(r"[0-9a-f]{40}", commit):
        raise ValueError("Invalid commit SHA")

    check_database_volume()

    BACKUP_DIR.mkdir(mode=0o700, parents=True, exist_ok=True)
    if BACKUP_DIR.is_symlink() or not BACKUP_DIR.is_dir():
        raise RuntimeError("Backup directory must be a real directory")
    BACKUP_DIR.chmod(0o700)

    stamp = datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    destination = BACKUP_DIR / f"bakery-{stamp}-{commit[:12]}.dump"
    pending = destination.with_suffix(".dump.partial")

    if pending.exists() or destination.exists():
        raise FileExistsError(f"Backup already exists: {destination}")

    try:
        fd = os.open(pending, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        with os.fdopen(fd, "wb") as archive:
            subprocess.run(PG_DUMP, check=True, stdout=archive, stderr=subprocess.PIPE)
            archive.flush()
            os.fsync(archive.fileno())

        if pending.stat().st_size < 1024:
            raise RuntimeError("Backup is unexpectedly small")

        with pending.open("rb") as archive:
            subprocess.run(
                PG_RESTORE_LIST,
                check=True,
                stdin=archive,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.PIPE,
            )

        pending.replace(destination)
        destination.chmod(0o600)
        directory_fd = os.open(BACKUP_DIR, os.O_RDONLY)
        try:
            os.fsync(directory_fd)
        finally:
            os.close(directory_fd)
    except BaseException:
        pending.unlink(missing_ok=True)
        raise

    print(f"Verified PostgreSQL backup: {destination} ({destination.stat().st_size} bytes)", flush=True)
    return destination


if __name__ == "__main__":
    try:
        backup(sys.argv[1])
    except (IndexError, ValueError, OSError, RuntimeError, subprocess.CalledProcessError) as error:
        print(f"Database backup failed; deployment aborted: {error}", file=sys.stderr)
        sys.exit(1)
