#!/usr/bin/env python3
"""Image-only production release. Installed as /usr/local/sbin/bakery-deploy-production."""

import fcntl
import json
import os
import re
import subprocess
import sys
import tempfile


PROJECT = "laravel-app"
COMPOSE = "/opt/bakery/compose.yml"
WORKING_TAG = "laravel-app-php:latest"
DATABASE_VOLUME = "laravel-app_postgres_data"
CERTIFICATE_VOLUME = "laravel-app_caddy_data"
DOMAIN = "xn----8sbfmclbpnvckotr1m.xn--p1ai"


def run(*args: str, env: dict[str, str] | None = None, input_text: str | None = None) -> str:
    result = subprocess.run(
        args,
        env=env,
        input=input_text,
        text=True,
        check=True,
        stdout=subprocess.PIPE,
    )
    return result.stdout.strip()


def main() -> None:
    request = json.load(sys.stdin)
    commit = request.get("commit", "")
    image = request.get("image", "")
    if not re.fullmatch(r"[0-9a-f]{40}", commit):
        raise ValueError("Invalid commit SHA")
    if not re.fullmatch(r"ghcr\.io/[a-z0-9._/-]+:[0-9a-f]{40}", image) or not image.endswith(":" + commit):
        raise ValueError("Invalid image reference")

    required = ("APP_KEY", "APP_URL", "DB_PASSWORD", "SESSION_SECURE_COOKIE", "GHCR_TOKEN", "GHCR_ACTOR")
    if any(not isinstance(request.get(key), str) or not request[key] for key in required):
        raise ValueError("Required release value is missing")
    if any("\n" in request[key] or "\r" in request[key] for key in required):
        raise ValueError("Multiline release value is unsupported")
    actor = request["GHCR_ACTOR"]
    if not re.fullmatch(r"[A-Za-z0-9-]+", actor):
        raise ValueError("Invalid GHCR actor")

    with open("/run/lock/bakery-release.lock", "w", encoding="ascii") as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        for volume in (DATABASE_VOLUME, CERTIFICATE_VOLUME):
            run("docker", "volume", "inspect", volume)

        runtime = os.environ.copy()
        for key in ("APP_KEY", "APP_URL", "DB_PASSWORD", "SESSION_SECURE_COOKIE", "APP_NAME"):
            if isinstance(request.get(key), str) and request[key]:
                runtime[key] = request[key]

        old_image = None
        try:
            old_image = run("docker", "image", "inspect", WORKING_TAG, "--format", "{{.Id}}")
        except subprocess.CalledProcessError:
            pass

        with tempfile.TemporaryDirectory(prefix="bakery-docker-", dir="/run") as docker_config:
            runtime["DOCKER_CONFIG"] = docker_config
            run("docker", "login", "ghcr.io", "-u", actor, "--password-stdin", env=runtime,
                input_text=request["GHCR_TOKEN"] + "\n")
            print(run("docker", "pull", image, env=runtime), flush=True)
            revision = run("docker", "image", "inspect", image, "--format",
                           '{{index .Config.Labels "org.opencontainers.image.revision"}}', env=runtime)
            if revision != commit:
                raise ValueError("Image revision does not match requested commit")
            run("docker", "tag", image, WORKING_TAG, env=runtime)

            compose = ("docker", "compose", "-p", PROJECT, "-f", COMPOSE)
            run(*compose, "config", "--quiet", env=runtime)
            print(run(*compose, "up", "-d", "--no-build", "--wait", "--wait-timeout", "120",
                      env=runtime), flush=True)

            mounts = json.loads(run("docker", "inspect", "laravel-app-postgres-1", "--format", "{{json .Mounts}}"))
            if not any(mount.get("Destination") == "/var/lib/postgresql" and
                       mount.get("Name") == DATABASE_VOLUME for mount in mounts):
                raise ValueError("PostgreSQL volume changed unexpectedly")
            print(run(*compose, "exec", "-T", "php", "php", "artisan", "migrate", "--force",
                      "--no-interaction", env=runtime), flush=True)
            run("curl", "--fail", "--silent", "--show-error", "--max-time", "15", "--resolve",
                f"{DOMAIN}:443:127.0.0.1", f"https://{DOMAIN}/health", env=runtime)

            run("docker", "image", "rm", image, env=runtime)
            current_image = run("docker", "image", "inspect", WORKING_TAG, "--format", "{{.Id}}", env=runtime)
            if old_image and old_image != current_image:
                try:
                    run("docker", "image", "rm", old_image, env=runtime)
                except subprocess.CalledProcessError:
                    print(f"Previous image {old_image} is still referenced and was retained", file=sys.stderr)
            print(f"Deployed {commit}; PostgreSQL volume {DATABASE_VOLUME} preserved", flush=True)


if __name__ == "__main__":
    try:
        main()
    except (ValueError, OSError, subprocess.CalledProcessError) as error:
        print(f"Release failed: {error}", file=sys.stderr)
        sys.exit(1)
