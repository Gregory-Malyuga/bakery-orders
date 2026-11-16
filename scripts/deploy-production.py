#!/usr/bin/env python3
"""Deploy one immutable PHP image to the inactive slot and switch Caddy.

The database and Caddy containers are never recreated by this script.
The production Caddyfile is an existing bind mount: it is updated in place,
because replacing a bind-mounted file's inode does not update the mount.
"""

import fcntl
import json
import os
import pathlib
import re
import subprocess
import sys
import tempfile
from urllib.parse import urlparse


PROJECT = "laravel-app"
COMPOSE = os.path.join(os.path.dirname(os.path.realpath(__file__)), "compose.yml")
DOMAIN = "xn----8sbfmclbpnvckotr1m.xn--p1ai"
DB_VOLUME = "laravel-app_postgres_data"
CADDY_VOLUME = "laravel-app_caddy_data"
CADDY_CONTAINER = "laravel-app-caddy-1"
DB_CONTAINER = "laravel-app-postgres-1"
SLOTS = {"blue": ("php", "laravel-app-php-1"),
         "green": ("php-green", "laravel-app-php-green-1")}
UPSTREAM = re.compile(r"(?m)^(\s*reverse_proxy\s+)(php|php-green)(:8000\s*)$")
IMAGE_PATTERN = re.compile(r"ghcr\.io/[a-z0-9._/-]+:([0-9a-f]{40})$")


def run(*args, env=None, input_text=None):
    return subprocess.run(args, input=input_text, text=True, env=env,
                          check=True, stdout=subprocess.PIPE).stdout.strip()


def docker_inspect(container):
    return json.loads(run("docker", "inspect", container))[0]


def container_healthy(container):
    try:
        data = docker_inspect(container)
    except subprocess.CalledProcessError:
        return False
    return (data["State"]["Running"] and
            data["State"].get("Health", {}).get("Status") == "healthy")


def current_caddyfile():
    data = docker_inspect(CADDY_CONTAINER)
    mounts = [mount for mount in data["Mounts"]
              if mount["Destination"] == "/etc/caddy/Caddyfile" and mount["Type"] == "bind"]
    if len(mounts) != 1:
        raise ValueError("Expected exactly one bind-mounted Caddyfile")
    source = pathlib.Path(mounts[0]["Source"])
    if not source.is_file() or source.is_symlink():
        raise ValueError("Caddyfile bind mount must be a regular file")
    return source


def detect_active(config):
    matches = UPSTREAM.findall(config)
    if len(matches) != 1:
        raise ValueError("Expected exactly one php/ php-green reverse_proxy directive")
    return "blue" if matches[0][1] == "php" else "green"


def replace_upstream(config, slot):
    service = SLOTS[slot][0]
    return UPSTREAM.sub(lambda match: match.group(1) + service + match.group(3),
                        config, count=1)


def write_existing_file(path, contents):
    # In-place writes preserve the inode seen by the existing Docker bind mount.
    with path.open("r+", encoding="utf-8") as file:
        file.seek(0)
        file.write(contents)
        file.truncate()
        file.flush()
        os.fsync(file.fileno())


def reload_caddy():
    run("docker", "exec", CADDY_CONTAINER, "caddy", "reload",
        "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile")


def switch_to(path, previous, target):
    if not container_healthy(SLOTS[target][1]):
        raise ValueError("Refusing switch: target container is not healthy")
    updated = replace_upstream(previous, target)
    if updated == previous:
        raise ValueError("Caddyfile upstream did not change")
    try:
        write_existing_file(path, updated)
        reload_caddy()
    except Exception:
        write_existing_file(path, previous)
        reload_caddy()
        raise


def check_public():
    # Check actual HTTPS traffic through Caddy, not a direct container port.
    for resource in ("/health", "/login"):
        run("curl", "--fail", "--silent", "--show-error", "--max-time", "15",
            "--resolve", f"{DOMAIN}:443:127.0.0.1",
            f"https://{DOMAIN}{resource}")


def check_candidate(container):
    if not container_healthy(container):
        raise ValueError(f"Candidate {container} failed Docker healthcheck")
    for resource in ("/up", "/health", "/login"):
        run("docker", "exec", container, "curl", "--fail", "--silent",
            "--show-error", "--max-time", "15",
            f"http://127.0.0.1:8000{resource}")


def verify_database():
    data = docker_inspect(DB_CONTAINER)
    if not data["State"]["Running"]:
        raise ValueError("PostgreSQL container is not running")
    if not any(m.get("Destination") == "/var/lib/postgresql" and
               m.get("Name") == DB_VOLUME for m in data["Mounts"]):
        raise ValueError("Production PostgreSQL volume changed unexpectedly")


def runtime_env(request):
    env = os.environ.copy()
    for key in ("APP_KEY", "APP_URL", "DB_PASSWORD",
                "SESSION_SECURE_COOKIE", "APP_NAME"):
        value = request.get(key)
        if isinstance(value, str) and value:
            env[key] = value
    return env


def perform_rollback(path, config):
    active = detect_active(config)
    previous = "green" if active == "blue" else "blue"
    if not container_healthy(SLOTS[previous][1]):
        raise ValueError(f"Rollback slot {previous} is not healthy")
    switch_to(path, config, previous)
    try:
        check_public()
    except Exception:
        # Revert if the public path does not work after switching back.
        switch_to(path, replace_upstream(config, previous), active)
        raise
    print(f"Rolled back application traffic from {active} to {previous}; database unchanged",
          flush=True)


def deploy(request, path, config, env):
    commit = request.get("commit", "")
    image = request.get("image", "")
    match = IMAGE_PATTERN.fullmatch(image)
    if not re.fullmatch(r"[0-9a-f]{40}", commit) or not match or match.group(1) != commit:
        raise ValueError("Invalid image reference or commit SHA")
    actor = request.get("GHCR_ACTOR", "")
    token = request.get("GHCR_TOKEN", "")
    if not re.fullmatch(r"[A-Za-z0-9-]+", actor) or not isinstance(token, str) or not token:
        raise ValueError("Missing GHCR credentials")

    active = detect_active(config)
    target = "green" if active == "blue" else "blue"
    service, container = SLOTS[target]
    verify_database()

    with tempfile.TemporaryDirectory(prefix="bakery-docker-") as docker_config:
        env["DOCKER_CONFIG"] = docker_config
        run("docker", "login", "ghcr.io", "-u", actor, "--password-stdin",
            env=env, input_text=token + "\n")
        run("docker", "pull", image, env=env)
        revision = run("docker", "image", "inspect", image, "--format",
                       '{{index .Config.Labels "org.opencontainers.image.revision"}}',
                       env=env)
        if revision != commit:
            raise ValueError("Image revision differs from the requested commit")
        run("docker", "tag", image, f"laravel-app-php:{target}", env=env)

        compose = ("docker", "compose", "-p", PROJECT, "-f", COMPOSE)
        run(*compose, "config", "--quiet", env=env)
        # Run only the migration job in the candidate image; do not touch
        # the active app container. Migration failure leaves routing unchanged.
        print(f"Migrating shared PostgreSQL using candidate {target}", flush=True)
        print(run(*compose, "run", "--rm", "--no-deps",
                  "--entrypoint", "php", service,
                  "artisan", "migrate", "--force", "--no-interaction",
                  env=env), flush=True)
        print(run(*compose, "up", "-d", "--no-deps", "--no-build",
                  "--wait", "--wait-timeout", "120", service, env=env), flush=True)
        check_candidate(container)
        print(f"Candidate {target} healthy, switching Caddy", flush=True)
        switch_to(path, config, target)
        try:
            check_public()
        except Exception:
            print("Post-switch healthcheck failed; reverting to old application", file=sys.stderr)
            switch_to(path, replace_upstream(config, target), active)
            check_public()
            raise
        print(f"Deployed {commit} to {target}; previous {active} kept for rollback; "
              "PostgreSQL volume unchanged", flush=True)


def main():
    request = json.load(sys.stdin)
    action = request.get("action", "deploy")
    if action not in ("deploy", "rollback"):
        raise ValueError("Invalid release action")
    required = ("APP_KEY", "APP_URL", "DB_PASSWORD", "SESSION_SECURE_COOKIE")
    if any(not isinstance(request.get(k), str) or not request[k] or
           "\n" in request[k] or "\r" in request[k] for k in required):
        raise ValueError("Required release value is missing or multiline")
    url = urlparse(request["APP_URL"])
    if url.scheme != "https" or url.hostname != DOMAIN:
        raise ValueError("APP_URL must match the production HTTPS domain")

    lock_path = os.path.join(os.path.expanduser("~"), ".bakery-release.lock")
    with open(lock_path, "w", encoding="ascii") as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        run("docker", "volume", "inspect", DB_VOLUME)
        run("docker", "volume", "inspect", CADDY_VOLUME)
        if not container_healthy(CADDY_CONTAINER):
            raise ValueError("Caddy is not healthy; cannot guarantee traffic continuity")
        path = current_caddyfile()
        config = path.read_text(encoding="utf-8")
        if detect_active(config) not in SLOTS:
            raise ValueError("Unrecognized active upstream")
        if action == "rollback":
            perform_rollback(path, config)
        else:
            deploy(request, path, config, runtime_env(request))


if __name__ == "__main__":
    try:
        main()
    except (ValueError, OSError, subprocess.CalledProcessError) as error:
        print(f"Release failed: {error}", file=sys.stderr)
        sys.exit(1)
