#!/bin/bash
# ============================================================
# commit-inventory.sh — Gerenciamento do repositório Inventory
# Projeto: Sentinela — Sítio Pé de Serra
# ============================================================
# Funcionalidades:
#   commit  → sincroniza arquivos locais + pull + commit + push
#   pull    → baixa últimas mudanças do GitHub para o servidor
#   status  → mostra status do git e arquivos modificados
#   diff    → mostra diferenças nos arquivos
#   log     → histórico de commits
#   service → status do persistidor MQTT (systemd)
# ============================================================
# Repositório: Epaminondaslage/inventory
# Frontend:    /var/www/html/inventory/
# Agente:      /opt/inventory/agent/
# ============================================================

set -euo pipefail

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'
CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

info()    { echo -e "${GREEN}[✔]${NC} $*"; }
warn()    { echo -e "${YELLOW}[!]${NC} $*"; }
error()   { echo -e "${RED}[✘]${NC} $*"; }
title()   { echo -e "\n${BOLD}${CYAN}$*${NC}"; }
divider() { echo -e "${CYAN}────────────────────────────────────────${NC}"; }

REPO_DIR="/var/www/html/inventory"
AGENT_DIR="/opt/inventory/agent"
PERSIST_SVC="mqtt-inventory-persist"

# ── Verifica se está no repo correto ──────────────────────
check_repo() {
    if [ ! -d "$REPO_DIR/.git" ]; then
        error "Repositório git não encontrado em $REPO_DIR"
        exit 1
    fi
    cd "$REPO_DIR"
}

# ── Sincroniza arquivos do servidor para o repo ───────────
sync_files() {
    title "Sincronizando arquivos para o repo..."
    divider

    # Frontend — arquivos do /var/www/html/inventory/ → var-www-html-inventory/
    for f in index.php css/style.css api/inventory_central/inventory_collector.php; do
        SRC="$REPO_DIR/$f"
        DST="$REPO_DIR/var-www-html-inventory/$f"
        if [ -f "$SRC" ]; then
            mkdir -p "$(dirname "$DST")"
            cp "$SRC" "$DST"
            info "Sync: $f"
        fi
    done

    # Agente — arquivos do /opt/inventory/agent/ → opt-inventory/agent/
    for f in Dockerfile docker-compose.yml agent.sh docker-manage.sh \
              api/inventory_agent.php api/openapi.yaml \
              scripts/inventory.sh; do
        SRC="$AGENT_DIR/$f"
        DST="$REPO_DIR/opt-inventory/agent/$f"
        if [ -f "$SRC" ]; then
            mkdir -p "$(dirname "$DST")"
            cp "$SRC" "$DST"
            info "Sync: agent/$f"
        fi
    done

    # Persistidor
    if [ -f "/opt/inventory/mqtt_inventory_persist.py" ]; then
        cp "/opt/inventory/mqtt_inventory_persist.py" "$REPO_DIR/opt-inventory/"
        info "Sync: mqtt_inventory_persist.py"
    fi

    # Grafana — dashboard e readme
    if [ -d "$REPO_DIR/grafana" ]; then
        info "Sync: grafana/ (já no repo)"
    fi
}

# ── Comandos ──────────────────────────────────────────────

cmd_commit() {
    check_repo
    MSG="${1:-update $(date '+%Y-%m-%d %H:%M')}"

    sync_files

    title "Commit — $MSG"
    divider

    # Pull remoto sem conflito
    info "Baixando commits remotos..."
    git add -A
    git stash 2>/dev/null || true
    git pull origin main --rebase 2>/dev/null || warn "Pull falhou — continuando com push local"
    git stash pop 2>/dev/null || true

    # Verifica se tem algo para commitar
    if git diff --cached --quiet && git diff --quiet && git status --porcelain | grep -q '^'; then
        git add -A
        git commit -m "$MSG"
        info "Commit realizado"
    elif ! git status --porcelain | grep -q '.'; then
        warn "Nada para commitar — repositório limpo"
        exit 0
    else
        git add -A
        git commit -m "$MSG" || warn "Commit vazio ignorado"
    fi

    git push origin main
    info "Push concluído! ✅"
}

cmd_pull() {
    check_repo
    title "Pull — baixando atualizações do GitHub"
    divider

    git fetch origin
    git pull origin main --rebase
    info "Repositório atualizado"

    # Copia arquivos atualizados do repo para o servidor
    title "Aplicando arquivos no servidor..."

    if [ -f "$REPO_DIR/var-www-html-inventory/index.php" ]; then
        cp "$REPO_DIR/var-www-html-inventory/index.php" "$REPO_DIR/index.php"
        info "Aplicado: index.php"
    fi

    if [ -f "$REPO_DIR/var-www-html-inventory/api/inventory_central/inventory_collector.php" ]; then
        cp "$REPO_DIR/var-www-html-inventory/api/inventory_central/inventory_collector.php" \
           "$REPO_DIR/api/inventory_central/inventory_collector.php"
        info "Aplicado: inventory_collector.php"
    fi

    if [ -f "$REPO_DIR/opt-inventory/mqtt_inventory_persist.py" ]; then
        cp "$REPO_DIR/opt-inventory/mqtt_inventory_persist.py" \
           "/opt/inventory/mqtt_inventory_persist.py"
        info "Aplicado: mqtt_inventory_persist.py"
        warn "Reiniciando persistidor MQTT..."
        sudo systemctl restart "$PERSIST_SVC" && info "Persistidor reiniciado"
    fi

    info "Pull e aplicação concluídos ✅"
}

cmd_status() {
    check_repo
    title "Status do repositório"
    divider

    echo -e "  Diretório: ${BOLD}$REPO_DIR${NC}"
    echo -e "  Branch:    ${BOLD}$(git branch --show-current)${NC}"
    echo -e "  Remoto:    ${BOLD}$(git remote get-url origin 2>/dev/null | sed 's/https:\/\/[^@]*@/https:\/\//' || echo 'não configurado')${NC}"
    echo ""

    git status --short

    echo ""
    title "Status do persistidor MQTT"
    divider
    systemctl is-active "$PERSIST_SVC" >/dev/null 2>&1 \
        && info "mqtt-inventory-persist: ATIVO" \
        || warn "mqtt-inventory-persist: INATIVO"

    echo ""
    title "Últimos snapshots no banco"
    divider
    mysql -u root -pEp@m1n0nd@s -h 127.0.0.1 -P 3306 inventory_db \
        -e "SELECT hostname, ip, collected_at, mem_used FROM inventory_snapshots ORDER BY id DESC LIMIT 5;" 2>/dev/null \
        || warn "MySQL não acessível"
}

cmd_diff() {
    check_repo
    title "Diferenças nos arquivos"
    divider
    git diff --stat
    echo ""
    git diff
}

cmd_log() {
    check_repo
    title "Histórico de commits"
    divider
    git log --oneline -15
}

cmd_service() {
    title "Status do persistidor MQTT"
    divider
    sudo systemctl status "$PERSIST_SVC" --no-pager
}

cmd_help() {
    title "commit-inventory.sh — Ajuda"
    divider
    echo "  commit ['msg']  → sync + pull + commit + push"
    echo "  pull            → pull do GitHub + aplica no servidor"
    echo "  status          → git status + persistidor + banco"
    echo "  diff            → arquivos modificados"
    echo "  log             → histórico de commits"
    echo "  service         → status do mqtt-inventory-persist"
    echo ""
    echo "  Repo: Epaminondaslage/inventory"
    echo "  Dir:  $REPO_DIR"
}

# ── Main ──────────────────────────────────────────────────
CMD="${1:-help}"
shift 2>/dev/null || true

case "$CMD" in
    commit)  cmd_commit "$*" ;;
    pull)    cmd_pull ;;
    status)  cmd_status ;;
    diff)    cmd_diff ;;
    log)     cmd_log ;;
    service) cmd_service ;;
    help|--help|-h) cmd_help ;;
    *)
        error "Comando desconhecido: ${CMD}"
        cmd_help
        exit 1
        ;;
esac
