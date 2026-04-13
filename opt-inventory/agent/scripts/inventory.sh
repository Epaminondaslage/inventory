#!/bin/bash

# ================= CONFIG =================
HOST_PROC=${HOST_PROC:-/proc}
HOST_SYS=${HOST_SYS:-/sys}
TIMEOUT_CMD="timeout 2"
# ==========================================

# Hostname and IP detection (use mounts provided in docker-compose)
HOSTNAME=$(cat /host/etc/hostname 2>/dev/null || hostname)
# Determine primary interface from host's route
PRIMARY_IFACE=$(awk '$2 == "00000000" { print $1 }' "$HOST_PROC/net/route" | head -n1)
HOST_IP=$(ip -4 addr show "$PRIMARY_IFACE" 2>/dev/null | awk '/inet / {print $2}' | cut -d/ -f1)

TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

run_cmd() {
    CMD="$1"
    $TIMEOUT_CMD bash -c "$CMD" 2>/dev/null || echo "unavailable"
}

# ================= COLETA =================

cpu_cores=$(grep -c ^processor "$HOST_PROC/cpuinfo" 2>/dev/null || echo 0)
mem_total=$(grep MemTotal "$HOST_PROC/meminfo" 2>/dev/null | awk '{print $2}')
uptime=$(run_cmd "cat $HOST_PROC/uptime | awk '{print \$1}'")
os_release=$(run_cmd "cat /host/os-release")

cpu_info=$(run_cmd "lscpu")
memory=$(run_cmd "free -h")
disks=$(run_cmd "lsblk -o NAME,SIZE,TYPE,MOUNTPOINT | grep -v '/etc/hosts'")
pci=$(run_cmd "lspci")
vga=$(run_cmd "lspci | grep -i vga")
network=$(run_cmd "ip -brief addr | grep -v 'lo\\|docker'")
virtualization=$(run_cmd "systemd-detect-virt")

# Docker information
if docker info >/dev/null 2>&1; then
    docker_info=$(run_cmd "docker info")
    docker_ps=$(run_cmd "docker ps --format '{{.Names}} {{.Status}}'")
else
    docker_info="unavailable"
    docker_ps="unavailable"
fi

# NVIDIA GPU (optional)
if command -v nvidia-smi >/dev/null 2>&1; then
    nvidia=$(run_cmd "nvidia-smi")
else
    nvidia="not_installed"
fi

# ================= JSON OUTPUT =================

jq -n \
  --arg hostname "$HOSTNAME" \
  --arg host_ip "$HOST_IP" \
  --arg timestamp "$TIMESTAMP" \
  --arg cpu_cores "$cpu_cores" \
  --arg mem_total "$mem_total" \
  --arg uptime "$uptime" \
  --arg os_release "$os_release" \
  --arg cpu_info "$cpu_info" \
  --arg memory "$memory" \
  --arg disks "$disks" \
  --arg pci "$pci" \
  --arg vga "$vga" \
  --arg network "$network" \
  --arg virtualization "$virtualization" \
  --arg docker_info "$docker_info" \
  --arg docker_ps "$docker_ps" \
  --arg nvidia "$nvidia" \
  '{
    hostname: $hostname,
    host_ip: $host_ip,
    timestamp: $timestamp,
    system: {
      cpu_cores: ($cpu_cores | tonumber?),
      mem_total_kb: ($mem_total | tonumber?),
      uptime_seconds: ($uptime | tonumber?)
    },
    raw: {
      os_release: $os_release,
      cpu_info: $cpu_info,
      memory: $memory,
      disks: $disks,
      pci: $pci,
      vga: $vga,
      network: $network,
      virtualization: $virtualization,
      docker_info: $docker_info,
      docker_ps: $docker_ps,
      nvidia: $nvidia
    }
  }'
