#!/bin/bash

# Unified management script for the Sentinela inventory‑agent container.
#
# This script can start, stop, restart, rebuild and check the status of the
# inventory-agent.  It automatically detects whether the host has
# `docker compose` (the plugin), `docker-compose` (the legacy binary), or
# neither.  If no compose tool is available, it falls back to building and
# running the container with `docker run` and appropriate volume mounts.

set -e

# Directory where this script and the Dockerfile reside.  Adjust only if
# you relocate the agent files somewhere else.
BASE_DIR="/opt/inventory/agent"
CONTAINER_NAME="inventory-agent"
IMAGE_NAME="inventory-agent"

# Ensure we are in the correct directory; exit if it doesn’t exist.
cd "$BASE_DIR" || {
    echo "Erro: diretório $BASE_DIR não encontrado"
    exit 1
}

# Determine which compose command, if any, is available.
# Prefer the v2 plugin (`docker compose`) and fall back to the legacy binary
# (`docker-compose`).  If neither is present, leave COMPOSE_CMD empty.
if docker compose version >/dev/null 2>&1; then
    COMPOSE_CMD="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE_CMD="docker-compose"
else
    COMPOSE_CMD=""
fi

# Functions using docker compose/compose plugin.
start_compose() {
    # Build and start in detached mode.  --build ensures the image
    # reflects any code changes on disk.
    $COMPOSE_CMD up -d --build
}

stop_compose() {
    # Gracefully stop and remove containers defined in the compose file.
    $COMPOSE_CMD down
}

restart_compose() {
    $COMPOSE_CMD down
    $COMPOSE_CMD up -d --build
}

status_compose() {
    # Show a concise status overview.  If the healthcheck is configured, it
    # prints the health state; otherwise, just list the container.
    docker ps | grep "$CONTAINER_NAME" || true
    if docker inspect --format='{{.State.Health.Status}}' "$CONTAINER_NAME" 2>/dev/null; then
        echo "Health status: $(docker inspect --format='{{.State.Health.Status}}' "$CONTAINER_NAME")"
    fi
    docker logs --tail=20 "$CONTAINER_NAME" || true
}

rebuild_compose() {
    # Force a clean build without cache and restart.
    $COMPOSE_CMD down
    $COMPOSE_CMD build --no-cache
    $COMPOSE_CMD up -d
}

# Functions for the manual docker run fallback when no compose tool is available.
start_manual() {
    # Build the image from the Dockerfile in the current directory.
    docker build -t "$IMAGE_NAME" .
    # Remove any existing container to avoid name conflicts.
    if docker ps -a --format '{{.Names}}' | grep -Eq "^$CONTAINER_NAME$"; then
        docker rm -f "$CONTAINER_NAME"
    fi
    # Run the container with the same settings defined in the compose file.
    docker run -d --name "$CONTAINER_NAME" \
        -p 8090:80 \
        --restart always \
        -v /proc:/host/proc:ro \
        -v /sys:/host/sys:ro \
        -v /etc/os-release:/host/os-release:ro \
        -v /var/run/docker.sock:/var/run/docker.sock \
        -v "$BASE_DIR/scripts":/scripts:ro \
        -e HOST_PROC=/host/proc \
        -e HOST_SYS=/host/sys \
        --read-only \
        --tmpfs /tmp \
        --tmpfs /var/run \
        --tmpfs /var/run/apache2 \
        --tmpfs /var/lock \
        --security-opt no-new-privileges \
        "$IMAGE_NAME"
}

stop_manual() {
    if docker ps -a --format '{{.Names}}' | grep -Eq "^$CONTAINER_NAME$"; then
        docker rm -f "$CONTAINER_NAME"
    else
        echo "Container $CONTAINER_NAME não encontrado"
    fi
}

restart_manual() {
    stop_manual
    start_manual
}

status_manual() {
    docker ps | grep "$CONTAINER_NAME" || true
    docker logs --tail=20 "$CONTAINER_NAME" || true
}

rebuild_manual() {
    stop_manual
    docker build --no-cache -t "$IMAGE_NAME" .
    start_manual
}

# Main dispatcher based on the first argument.
case "$1" in
    start)
        echo "===> Subindo $CONTAINER_NAME..."
        if [ -n "$COMPOSE_CMD" ]; then
            start_compose
        else
            start_manual
        fi
        ;;
    stop)
        echo "===> Parando $CONTAINER_NAME..."
        if [ -n "$COMPOSE_CMD" ]; then
            stop_compose
        else
            stop_manual
        fi
        ;;
    restart)
        echo "===> Reiniciando $CONTAINER_NAME..."
        if [ -n "$COMPOSE_CMD" ]; then
            restart_compose
        else
            restart_manual
        fi
        ;;
    status)
        echo "===> Status do contêiner $CONTAINER_NAME:"
        if [ -n "$COMPOSE_CMD" ]; then
            status_compose
        else
            status_manual
        fi
        ;;
    logs)
        docker logs -f "$CONTAINER_NAME"
        ;;
    rebuild)
        echo "===> Recriando imagem e contêiner..."
        if [ -n "$COMPOSE_CMD" ]; then
            rebuild_compose
        else
            rebuild_manual
        fi
        ;;
    test)
        echo "===> Testando endpoint..."
        curl -fsSL -H "Authorization: Bearer sentinela_token_123" http://localhost:8090/api/inventory_agent.php || true
        ;;
    *)
        echo "Uso: $0 {start|stop|restart|status|logs|rebuild|test}"
        exit 1
        ;;
esac