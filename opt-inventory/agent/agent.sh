#!/bin/bash

# Unified management script for the Sentinela inventory-agent container.
#
# This script can start, stop, restart, rebuild and check the status of the
# inventory-agent.  It automatically detects whether the host has
# `docker compose` (the plugin), `docker-compose` (the legacy binary), or
# neither.  If no compose tool is available, it falls back to building and
# running the container with `docker run` and appropriate volume mounts.

set -e

BASE_DIR="/opt/inventory/agent"
CONTAINER_NAME="inventory-agent"
IMAGE_NAME="inventory-agent"

cd "$BASE_DIR" || {
    echo "Erro: diretório $BASE_DIR não encontrado"
    exit 1
}

# ================= DETECÇÃO COMPOSE =================
if docker compose version >/dev/null 2>&1; then
    COMPOSE_CMD="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE_CMD="docker-compose"
else
    COMPOSE_CMD=""
fi

# ================= VALIDA COMPOSE =================
compose_valid() {
    if [ -z "$COMPOSE_CMD" ]; then
        return 1
    fi

    $COMPOSE_CMD config >/dev/null 2>&1
}

# ================= COMPOSE =================

start_compose() {
    if compose_valid; then
        $COMPOSE_CMD up -d --build || start_manual
    else
        echo "⚠️ Compose inválido, usando modo manual..."
        start_manual
    fi
}

stop_compose() {
    if compose_valid; then
        $COMPOSE_CMD down || stop_manual
    else
        stop_manual
    fi
}

restart_compose() {
    if compose_valid; then
        $COMPOSE_CMD down || true
        $COMPOSE_CMD up -d --build || restart_manual
    else
        restart_manual
    fi
}

rebuild_compose() {
    if compose_valid; then
        $COMPOSE_CMD down || true
        $COMPOSE_CMD build --no-cache || rebuild_manual
        $COMPOSE_CMD up -d --force-recreate || rebuild_manual
    else
        echo "⚠️ Compose incompatível. Usando modo manual..."
        rebuild_manual
    fi
}

status_compose() {
    docker ps | grep "$CONTAINER_NAME" || true
    docker logs --tail=20 "$CONTAINER_NAME" || true
}

# ================= MODO MANUAL =================

start_manual() {

    echo "===> Iniciando modo manual..."

    docker build -t "$IMAGE_NAME" .

    if docker ps -a --format '{{.Names}}' | grep -Eq "^$CONTAINER_NAME$"; then
        docker rm -f "$CONTAINER_NAME"
    fi

    docker run -d --name "$CONTAINER_NAME" \
        -p 8090:80 \
        --restart always \
        -v /proc:/host/proc:ro \
        -v /sys:/host/sys:ro \
        -v /etc/os-release:/host/os-release:ro \
        -v /etc/hostname:/host/etc/hostname:ro \
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

rebuild_manual() {
    stop_manual
    docker build --no-cache -t "$IMAGE_NAME" .
    start_manual
}

status_manual() {
    docker ps | grep "$CONTAINER_NAME" || true
    docker logs --tail=20 "$CONTAINER_NAME" || true
}

# ================= MAIN =================

case "$1" in
    start)
        echo "===> Subindo $CONTAINER_NAME..."
        if compose_valid; then
            start_compose
        else
            start_manual
        fi
        ;;
    stop)
        echo "===> Parando $CONTAINER_NAME..."
        if compose_valid; then
            stop_compose
        else
            stop_manual
        fi
        ;;
    restart)
        echo "===> Reiniciando $CONTAINER_NAME..."
        if compose_valid; then
            restart_compose
        else
            restart_manual
        fi
        ;;
    status)
        echo "===> Status do contêiner $CONTAINER_NAME:"
        if compose_valid; then
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
        if compose_valid; then
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