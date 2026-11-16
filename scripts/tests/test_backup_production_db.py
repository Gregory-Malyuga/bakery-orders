"""Unit checks for the host-side PostgreSQL backup release gate."""

import importlib.util
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

SCRIPT = Path(__file__).resolve().parents[1] / "backup-production-db.py"
spec = importlib.util.spec_from_file_location("backup_production_db", SCRIPT)
assert spec is not None and spec.loader is not None
backup_db = importlib.util.module_from_spec(spec)
spec.loader.exec_module(backup_db)

COMMIT = "a" * 40


class BackupReleaseTests(unittest.TestCase):
    def test_verified_backup_is_written_to_host_with_private_permissions(self):
        with tempfile.TemporaryDirectory() as root:
            directory = Path(root) / "backups"

            def fake_run(args, **kwargs):
                if args == backup_db.PG_DUMP:
                    kwargs["stdout"].write(b"PGDMP" + b"1" * 2048)
                elif args == backup_db.PG_RESTORE_LIST:
                    self.assertEqual(kwargs["stdin"].read(5), b"PGDMP")
                else:
                    self.fail(f"Unexpected command: {args}")
                return subprocess.CompletedProcess(args, 0)

            with patch.object(backup_db, "BACKUP_DIR", directory), \
                 patch.object(backup_db, "check_database_volume"), \
                 patch.object(backup_db.subprocess, "run", side_effect=fake_run):
                result = backup_db.backup(COMMIT)

            self.assertTrue(result.is_file())
            self.assertEqual(result.parent, directory)
            self.assertEqual(result.stat().st_mode & 0o777, 0o600)
            self.assertEqual(directory.stat().st_mode & 0o777, 0o700)
            self.assertFalse(list(directory.glob("*.partial")))

    def test_failed_dump_removes_partial_and_aborts(self):
        with tempfile.TemporaryDirectory() as root:
            directory = Path(root) / "backups"

            def failed_dump(args, **kwargs):
                raise subprocess.CalledProcessError(1, args)

            with patch.object(backup_db, "BACKUP_DIR", directory), \
                 patch.object(backup_db, "check_database_volume"), \
                 patch.object(backup_db.subprocess, "run", side_effect=failed_dump):
                with self.assertRaises(subprocess.CalledProcessError):
                    backup_db.backup(COMMIT)

            self.assertEqual(list(directory.iterdir()), [])

    def test_failed_archive_validation_removes_partial_and_aborts(self):
        with tempfile.TemporaryDirectory() as root:
            directory = Path(root) / "backups"

            def fake_run(args, **kwargs):
                if args == backup_db.PG_DUMP:
                    kwargs["stdout"].write(b"PGDMP" + b"1" * 2048)
                    return subprocess.CompletedProcess(args, 0)
                if args == backup_db.PG_RESTORE_LIST:
                    raise subprocess.CalledProcessError(1, args)
                self.fail(f"Unexpected command: {args}")

            with patch.object(backup_db, "BACKUP_DIR", directory), \
                 patch.object(backup_db, "check_database_volume"), \
                 patch.object(backup_db.subprocess, "run", side_effect=fake_run):
                with self.assertRaises(subprocess.CalledProcessError):
                    backup_db.backup(COMMIT)

            self.assertEqual(list(directory.iterdir()), [])

    def test_backup_rejects_invalid_revision(self):
        with self.assertRaises(ValueError):
            backup_db.backup("../unsafe")

    def test_expected_postgres_volume_is_required(self):
        fake = subprocess.CompletedProcess(
            args=["docker", "inspect"],
            returncode=0,
            stdout='[{"Destination":"/var/lib/postgresql","Name":"wrong_volume"}]',
        )
        with patch.object(backup_db.subprocess, "run", return_value=fake):
            with self.assertRaises(RuntimeError):
                backup_db.check_database_volume()


if __name__ == "__main__":
    unittest.main()
