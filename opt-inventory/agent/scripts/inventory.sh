#!/bin/bash
# =============================================================
# inventory.sh — Coleta de inventário do servidor
# Projeto: Sentinela — Sítio Pé de Serra
#
# As portas TCP e containers Docker são passados pelo
# inventory_agent.php via variáveis de ambiente:
#   HOST_PORTS_JSON  — JSON array de portas do host
#   HOST_DOCKER_JSON — JSON array de containers Docker
#
# O script coleta o restante: OS, CPU, memória, disco, rede
# =============================================================

HOST_PROC=${HOST_PROC:-/host/proc}
HOST_SYS=${HOST_SYS:-/host/sys}
TIMEOUT_CMD="timeout 3"

# ── Hostname e IP ──────────────────────────────────────────
HOSTNAME=$(cat /host/etc/hostname 2>/dev/null | tr -d '\n' || hostname)
PRIMARY_IFACE=$(awk '$2 == "00000000" { print $1 }' "$HOST_PROC/net/route" 2>/dev/null | head -n1)
HOST_IP=$(ip -4 addr show "$PRIMARY_IFACE" 2>/dev/null | awk '/inet / {print $2}' | cut -d/ -f1)
TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

run_cmd() { $TIMEOUT_CMD bash -c "$1" 2>/dev/null || echo "unavailable"; }

# ── Sistema ────────────────────────────────────────────────
cpu_cores=$(grep -c ^processor "$HOST_PROC/cpuinfo" 2>/dev/null || echo 0)
mem_total=$(grep MemTotal "$HOST_PROC/meminfo" 2>/dev/null | awk '{print $2}')
uptime=$(cat "$HOST_PROC/uptime" 2>/dev/null | awk '{print $1}')
os_release=$(run_cmd "cat /host/os-release")
cpu_info=$(run_cmd "lscpu")
memory=$(run_cmd "free -h")
disks=$(run_cmd "lsblk -o NAME,SIZE,TYPE,MOUNTPOINT | grep -v '/etc/hosts'")
pci=$(run_cmd "lspci 2>/dev/null || echo unavailable")
network=$(run_cmd "ip -brief addr | grep -v 'lo\|docker'")
virtualization=$(run_cmd "systemd-detect-virt 2>/dev/null || echo unknown")
docker_info=$(run_cmd "docker info 2>/dev/null || echo unavailable")

# ── NVIDIA ────────────────────────────────────────────────
if command -v nvidia-smi >/dev/null 2>&1; then
    nvidia=$(run_cmd "nvidia-smi")
else
    nvidia="not_installed"
fi

# ── Portas e Docker — recebidos do PHP via env ─────────────
open_ports_json=${HOST_PORTS_JSON:-"[]"}
docker_json=${HOST_DOCKER_JSON:-"[]"}

# ── JSON final ────────────────────────────────────────────
jq -n \
  --arg  hostname       "$HOSTNAME" \
  --arg  host_ip        "$HOST_IP" \
  --arg  timestamp      "$TIMESTAMP" \
  --arg  cpu_cores      "$cpu_cores" \
  --arg  mem_total      "$mem_total" \
  --arg  uptime         "$uptime" \
  --arg  os_release     "$os_release" \
  --arg  cpu_info       "$cpu_info" \
  --arg  memory         "$memory" \
  --arg  disks          "$disks" \
  --arg  pci            "$pci" \
  --arg  network        "$network" \
  --arg  virtualization "$virtualization" \
  --arg  docker_info    "$docker_info" \
  --arg  nvidia         "$nvidia" \
  --argjson open_ports  "$open_ports_json" \
  --argjson docker      "$docker_json" \
  '{
    hostname:  $hostname,
    host_ip:   $host_ip,
    timestamp: $timestamp,
    system: {
      cpu_cores:      ($cpu_cores | tonumber? // 0),
      mem_total_kb:   ($mem_total  | tonumber? // 0),
      uptime_seconds: ($uptime     | tonumber? // 0)
    },
    services: {
      open_ports: $open_ports,
      docker:     $docker
    },
    raw: {
      os_release:     $os_release,
      cpu_info:       $cpu_info,
      memory:         $memory,
      disks:          $disks,
      pci:            $pci,
      network:        $network,
      virtualization: $virtualization,
      docker_info:    $docker_info,
      nvidia:         $nvidia
    }
  }'
