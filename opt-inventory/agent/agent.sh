#!/bin/bash

BASE_DIR="/opt/inventory/agent"
CONTAINER_NAME="inventory-agent"

cd "$BASE_DIR" || {
    echo "Erro: diretório $BASE_DIR não encontrado"
    exit 1
}

case "$1" in

    start)
        echo "===> Subindo container..."
        docker compose up -d --build
        ;;

    stop)
        echo "===> Parando container..."
        docker compose down
        ;;

    restart)
        echo "===> Reiniciando container..."
        docker compose down
        docker compose up -d --build
        ;;

    status)
        echo "===> Status do container:"
        docker ps | grep "$CONTAINER_NAME"

        echo ""
        echo "===> Health:"
        docker inspect --format='{{.State.Health.Status}}' "$CONTAINER_NAME" 2>/dev/null

        echo ""
        echo "===> Últimos logs:"
        docker logs --tail=20 "$CONTAINER_NAME"
        ;;

    logs)
        docker logs -f "$CONTAINER_NAME"
        ;;

    rebuild)
        echo "===> Rebuild completo..."
        docker compose down
        docker compose build --no-cache
        docker compose up -d
        ;;

    test)
        echo "===> Testando endpoint..."
        curl -H "Authorization: Bearer sentinela_token_123" \
        http://localhost:8090/api/inventory_agent.php | jq .
        ;;

    *)
        echo "Uso: $0 {start|stop|restart|status|logs|rebuild|test}"
        exit 1
        ;;

esac