"""Unit tests for Blue-Green routing and failure paths (no Docker required)."""

import importlib.util
import pathlib
import tempfile
import unittest
from unittest.mock import patch


SCRIPT = pathlib.Path(__file__).resolve().parents[1] / "scripts" / "deploy-production.py"
SPEC = importlib.util.spec_from_file_location("bakery_release", SCRIPT)
release = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(release)


CONFIG = ("xn----8sbfmclbpnvckotr1m.xn--p1ai {\n"
          "    encode zstd gzip\n"
          "    reverse_proxy php:8000\n}\n")


class BlueGreenReleaseTests(unittest.TestCase):
    def test_detect_active_slot(self):
        self.assertEqual("blue", release.detect_active(CONFIG))
        self.assertEqual("green", release.detect_active(
            release.replace_upstream(CONFIG, "green")))

    def test_replace_upstream_preserves_unrelated_caddy_directives(self):
        expected = CONFIG.replace("reverse_proxy php:8000",
                                  "reverse_proxy php-green:8000")
        self.assertEqual(expected, release.replace_upstream(CONFIG, "green"))
        self.assertEqual(CONFIG, release.replace_upstream(expected, "blue"))

    def test_reject_unknown_or_multiple_upstreams(self):
        for config in ("site {\n reverse_proxy something:8000\n}\n",
                       CONFIG + "\nreverse_proxy php-green:8000\n"):
            with self.assertRaises(ValueError):
                release.detect_active(config)

    def test_refuse_switch_to_unhealthy_container(self):
        with tempfile.TemporaryDirectory() as directory:
            config_path = pathlib.Path(directory) / "Caddyfile"
            config_path.write_text(CONFIG)
            with patch.object(release, "container_healthy", return_value=False), \
                    patch.object(release, "reload_caddy") as reload:
                with self.assertRaises(ValueError):
                    release.switch_to(config_path, CONFIG, "green")
                reload.assert_not_called()
            self.assertEqual(CONFIG, config_path.read_text())

    def test_failed_reload_restores_configuration(self):
        with tempfile.TemporaryDirectory() as directory:
            config_path = pathlib.Path(directory) / "Caddyfile"
            config_path.write_text(CONFIG)
            with patch.object(release, "container_healthy", return_value=True), \
                    patch.object(release, "reload_caddy",
                                 side_effect=[RuntimeError("reload failed"), None]) as reload:
                with self.assertRaises(RuntimeError):
                    release.switch_to(config_path, CONFIG, "green")
                self.assertEqual(2, reload.call_count)
            self.assertEqual(CONFIG, config_path.read_text())

    def test_rollback_uses_previous_healthy_slot(self):
        with patch.object(release, "container_healthy", return_value=True), \
                patch.object(release, "switch_to") as switch, \
                patch.object(release, "check_public") as public:
            release.perform_rollback(pathlib.Path("/tmp/not-used"), CONFIG)
        self.assertEqual("green", switch.call_args.args[2])
        public.assert_called_once()


if __name__ == "__main__":
    unittest.main()
