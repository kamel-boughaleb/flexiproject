#!/usr/bin/env bash
set -euo pipefail

wait_until_up() {
    local container="$1" deadline=$((SECONDS + 600))

    until docker exec "$container" curl -fs -o /dev/null http://127.0.0.1:8000/up 2>/dev/null; do
        if (( SECONDS > deadline )) || [ "$(docker inspect -f '{{.State.Restarting}}' "$container")" = true ]; then
            echo "==> $container did not come up" >&2
            docker logs --tail 80 "$container" >&2
            return 1
        fi
        sleep 5
    done
}

main() {
    local docker_dir app_dir container
    docker_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    app_dir="$(dirname "$docker_dir")"
    container="$(sed -n 's/^FLEXIPROJECT_HOSTNAME=//p' "$docker_dir/.env" | tr -d '"' | head -n 1)"
    : "${container:?FLEXIPROJECT_HOSTNAME missing from $docker_dir/.env}"

    echo "==> git pull"
    git -C "$app_dir" pull --ff-only

    echo "==> rebuild and restart $container"
    docker compose --project-directory "$docker_dir" up -d --build --force-recreate --no-deps app

    echo "==> waiting for /up"
    wait_until_up "$container"

    echo "==> deployed $(git -C "$app_dir" log --oneline -1)"
}

# Bash reads a script as it runs, and `git pull` may rewrite this very file:
# parsing everything into main() first keeps the old version running to the end.
main "$@"; exit
