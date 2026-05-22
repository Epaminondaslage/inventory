#!/bin/bash
# =============================================================================
# docker-manage.sh — Gerenciamento Docker adaptativo
# Projeto: Sentinela — Sítio Pé de Serra
#
# Detecta automaticamente a versão do Docker disponível e usa
# o comando correto: docker compose (plugin) ou docker-compose (legado)
# ou docker run (fallback para Docker muito antigo como no 10.0.0.141)
#
# Uso:
#   ./docker-manage.sh status              — status de todos os containers
#   ./docker-manage.sh ps                  — lista containers
#   ./docker-manage.sh logs <container>    — logs em tempo real
#   ./docker-manage.sh restart <container> — reinicia um container
#   ./docker-manage.sh stop <container>    — para um container
#   ./docker-manage.sh start <container>   — inicia um container
#   ./docker-manage.sh rebuild             — reconstrói o inventory-agent
#   ./docker-manage.sh prune               — limpa imagens/containers não usados
#   ./docker-manage.sh info                — informações do Docker do host
# =============================================================================

set -euo pipefail

# ── Cores ──────────────────────────────────────────────────
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'
CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

info()    { echo -e "${GREEN}[✔]${NC} $*"; }
warn()    { echo -e "${YELLOW}[!]${NC} $*"; }
error()   { echo -e "${RED}[✘]${NC} $*"; }
title()   { echo -e "\n${BOLD}${CYAN}$*${NC}"; }
divider() { echo -e "${CYAN}────────────────────────────────────────${NC}"; }

# ── Detecção de ambiente ───────────────────────────────────
HOSTNAME_HOST=$(cat /etc/hostname 2>/dev/null | tr -d '\n' || hostname)
DOCKER_VERSION=$(docker version --format '{{.Server.Version}}' 2>/dev/null || echo "unknown")
AGENT_DIR="/opt/inventory/agent"

# ── Detecção do compose ────────────────────────────────────
detect_compose() {
    if docker compose version >/dev/null 2>&1; then
        echo "plugin"
    elif command -v docker-compose >/dev/null 2>&1; then
        # Verifica se suporta sintaxe atual
        if docker-compose config >/dev/null 2>&1; then
            echo "legacy"
        else
            echo "manual"
        fi
    else
        echo "manual"
    fi
}

COMPOSE_MODE=$(detect_compose)

compose_cmd() {
    case "$COMPOSE_MODE" in
        plugin)  docker compose "$@" ;;
        legacy)  docker-compose "$@" ;;
        manual)  warn "Compose não disponível — usando docker run direto"; return 1 ;;
    esac
}

# ── Funções principais ─────────────────────────────────────

cmd_status() {
    title "Status dos Containers — ${HOSTNAME_HOST} (${DOCKER_VERSION})"
    divider
    docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" 2>/dev/null
    echo ""
    STOPPED=$(docker ps -a --filter "status=exited" --format "{{.Names}}" 2>/dev/null)
    if [ -n "$STOPPED" ]; then
        warn "Containers parados:"
        docker ps -a --filter "status=exited" --format "  • {{.Names}} — {{.Status}}" 2>/dev/null
    fi
}

cmd_ps() {
    title "Containers — ${HOSTNAME_HOST}"
    divider
    docker ps -a --format "table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}" 2>/dev/null
}

cmd_logs() {
    local ctr="${1:-inventory-agent}"
    title "Logs: ${ctr}"
    divider
    docker logs -f --tail=50 "$ctr"
}

cmd_restart() {
    local ctr="${1:-}"
    if [ -z "$ctr" ]; then
        error "Informe o nome do container. Ex: ./docker-manage.sh restart inventory-agent"
        exit 1
    fi
    warn "Reiniciando: ${ctr}..."
    docker restart "$ctr"
    info "Reiniciado: ${ctr}"
    docker ps | grep "$ctr" || true
}

cmd_stop() {
    local ctr="${1:-}"
    if [ -z "$ctr" ]; then
        error "Informe o nome do container."
        exit 1
    fi
    warn "Parando: ${ctr}..."
    docker stop "$ctr"
    info "Parado: ${ctr}"
}

cmd_start() {
    local ctr="${1:-}"
    if [ -z "$ctr" ]; then
        error "Informe o nome do container."
        exit 1
    fi
    info "Iniciando: ${ctr}..."
    docker start "$ctr"
    docker ps | grep "$ctr" || true
}

cmd_rebuild() {
    title "Rebuild — inventory-agent"
    divider

    if [ ! -d "$AGENT_DIR" ]; then
        error "Diretório não encontrado: ${AGENT_DIR}"
        exit 1
    fi

    cd "$AGENT_DIR"

    case "$COMPOSE_MODE" in
        plugin)
            info "Usando: docker compose"
            docker compose down || true
            docker compose build --no-cache
            docker compose up -d
            ;;
        legacy)
            info "Usando: docker-compose (legado)"
            docker-compose down || true
            docker-compose build --no-cache
            docker-compose up -d
            ;;
        manual)
            warn "Compose não disponível — modo manual com docker run"
            docker rm -f inventory-agent 2>/dev/null || true
            docker build --no-cache -t inventory-agent .
            docker run -d \
                --name inventory-agent \
                --restart always \
                --network host \
                -v /proc:/host/proc:ro \
                -v /sys:/host/sys:ro \
                -v /etc/os-release:/host/os-release:ro \
                -v /etc/hostname:/host/etc/hostname:ro \
                -v /var/run/docker.sock:/var/run/docker.sock \
                -v "${AGENT_DIR}/scripts":/scripts:ro \
                -e HOST_PROC=/host/proc \
                -e HOST_SYS=/host/sys \
                inventory-agent
            ;;
    esac

    sleep 3
    info "Container status:"
    docker ps | grep inventory-agent || warn "Container não encontrado!"

    info "Testando endpoint..."
    sleep 2
    RESP=$(curl -fs -H "Authorization: Bearer sentinela_token_123" \
        http://localhost:8090/api/inventory_agent.php 2>/dev/null | \
        python3 -c "import sys,json; d=json.load(sys.stdin); print('OK —', d.get('data',d).get('hostname','?'))" 2>/dev/null || echo "ERRO")
    if [[ "$RESP" == OK* ]]; then
        info "$RESP"
    else
        warn "Endpoint não respondeu: ${RESP}"
    fi
}

cmd_prune() {
    title "Limpeza Docker — ${HOSTNAME_HOST}"
    divider
    warn "Removendo containers parados, imagens não usadas e volumes órfãos..."
    docker system prune -f
    info "Limpeza concluída."
    docker system df
}

cmd_info() {
    title "Informações Docker — ${HOSTNAME_HOST}"
    divider
    echo -e "  Versão:        ${BOLD}${DOCKER_VERSION}${NC}"
    echo -e "  Compose mode:  ${BOLD}${COMPOSE_MODE}${NC}"
    echo -e "  Hostname:      ${BOLD}${HOSTNAME_HOST}${NC}"
    echo ""
    docker system df 2>/dev/null || true
    echo ""
    docker info 2>/dev/null | grep -E "Containers|Images|Server Version|Operating System|Architecture|Total Memory|Name:" || true
}

cmd_help() {
    title "docker-manage.sh — Ajuda"
    divider
    echo "  status              Lista containers ativos e parados"
    echo "  ps                  Lista todos os containers"
    echo "  logs <container>    Logs em tempo real (padrão: inventory-agent)"
    echo "  restart <container> Reinicia um container"
    echo "  stop <container>    Para um container"
    echo "  start <container>   Inicia um container"
    echo "  rebuild             Reconstrói o inventory-agent"
    echo "  prune               Remove recursos Docker não utilizados"
    echo "  info                Informações do Docker"
    echo ""
    echo "  Servidor: ${HOSTNAME_HOST} | Docker: ${DOCKER_VERSION} | Compose: ${COMPOSE_MODE}"
}

# ── Main ───────────────────────────────────────────────────
CMD="${1:-status}"
shift 2>/dev/null || true

case "$CMD" in
    status)          cmd_status ;;
    ps)              cmd_ps ;;
    logs)            cmd_logs "${1:-inventory-agent}" ;;
    restart)         cmd_restart "${1:-}" ;;
    stop)            cmd_stop "${1:-}" ;;
    start)           cmd_start "${1:-}" ;;
    rebuild)         cmd_rebuild ;;
    prune)           cmd_prune ;;
    info)            cmd_info ;;
    help|--help|-h)  cmd_help ;;
    *)
        error "Comando desconhecido: ${CMD}"
        cmd_help
        exit 1
        ;;
esac
