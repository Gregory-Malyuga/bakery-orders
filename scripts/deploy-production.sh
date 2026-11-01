#!/usr/bin/env bash
set -euo pipefail

# Installed as root-owned /usr/local/sbin/bakery-deploy-production. The
# dedicated SSH account can invoke only this script through deploy-gate.sh.
IFS=' ' read -r commit image
read -r ghcr_token
read -r ghcr_actor
project=laravel-app
compose_dir=/root/bakery-orders
working_tag=laravel-app-php:latest
volume="${project}_postgres_data"

[[ "$commit" =~ ^[0-9a-f]{40}$ ]] || { echo 'Invalid commit SHA' >&2; exit 1; }
[[ "$image" =~ ^ghcr\.io/[a-z0-9._/-]+:[0-9a-f]{40}$ ]] || { echo 'Invalid image reference' >&2; exit 1; }
[[ -n "$ghcr_token" && "$ghcr_actor" =~ ^[a-zA-Z0-9-]+$ ]] || { echo 'GHCR credentials missing' >&2; exit 1; }

exec 9>/run/lock/bakery-release.lock
flock -n 9 || { echo 'Another release is in progress' >&2; exit 1; }
cd "$compose_dir"

# Verify the existing database volume. The update command targets only PHP.
actual_volume="$(docker inspect "${project}-postgres-1" --format '{{range .Mounts}}{{if eq .Destination "/var/lib/postgresql"}}{{.Name}}{{end}}{{end}}')"
[[ "$actual_volume" == "$volume" ]] || { echo 'Unexpected PostgreSQL volume' >&2; exit 1; }
old_image_id="$(docker inspect "${project}-php-1" --format '{{.Image}}')"

docker_config="$(mktemp -d /run/bakery-docker-config.XXXXXXXX)"
trap 'rm -rf "$docker_config"' EXIT
chmod 700 "$docker_config"
export DOCKER_CONFIG="$docker_config"
printf '%s\n' "$ghcr_token" | docker login ghcr.io -u "$ghcr_actor" --password-stdin >/dev/null
unset ghcr_token

docker pull "$image"
revision="$(docker image inspect "$image" --format '{{index .Config.Labels "org.opencontainers.image.revision"}}')"
[[ "$revision" == "$commit" ]] || { echo 'Image revision does not match requested commit' >&2; exit 1; }
docker tag "$image" "$working_tag"

compose=(docker compose -p "$project" -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.domain.yml)
"${compose[@]}" up -d --no-build --no-deps --force-recreate php
for attempt in $(seq 1 60); do
    if [[ "$(docker inspect "${project}-php-1" --format '{{.State.Health.Status}}')" == healthy ]]; then
        break
    fi
    sleep 2
done
[[ "$(docker inspect "${project}-php-1" --format '{{.State.Health.Status}}')" == healthy ]] || {
    echo 'PHP did not become healthy within 120 seconds' >&2
    exit 1
}
"${compose[@]}" exec -T php wget -qO- http://127.0.0.1:8000/health >/dev/null
new_volume="$(docker inspect "${project}-postgres-1" --format '{{range .Mounts}}{{if eq .Destination "/var/lib/postgresql"}}{{.Name}}{{end}}{{end}}')"
[[ "$new_volume" == "$volume" ]] || { echo 'PostgreSQL volume changed unexpectedly' >&2; exit 1; }

# Remove the temporary CI tag and the replaced image when Docker permits it.
docker image rm "$image" >/dev/null
new_image_id="$(docker inspect "${project}-php-1" --format '{{.Image}}')"
if [[ "$old_image_id" != "$new_image_id" ]]; then
    if ! docker image rm "$old_image_id" >/dev/null 2>&1; then
        echo "Previous image $old_image_id is still referenced and was retained" >&2
    fi
fi
echo "Deployed $commit as $working_tag; PostgreSQL volume $volume preserved"
