#!/bin/bash

HOST=$(hostname)
TIMESTAMP=$(date +"%Y-%m-%d %H:%M:%S")

escape_json() {
    echo "$1" | sed 's/\\/\\\\/g' | sed 's/"/\\"/g' | awk '{printf "%s\\n", $0}'
}

SYSTEM_INFO=$(escape_json "$(uname -a)")
OS_RELEASE=$(escape_json "$(cat /etc/os-release 2>/dev/null)")
CPU_INFO=$(escape_json "$(lscpu)")
MEMORY=$(escape_json "$(free -h)")
DISKS=$(escape_json "$(lsblk -o NAME,SIZE,TYPE,MOUNTPOINT)")
PCI=$(escape_json "$(lspci)")
VGA=$(escape_json "$(lspci | grep -i vga)")
NVIDIA=$(escape_json "$(nvidia-smi 2>/dev/null)")
NETWORK=$(escape_json "$(ip -brief addr)")
VIRT=$(escape_json "$(systemd-detect-virt 2>/dev/null)")
DOCKER=$(escape_json "$(docker info 2>/dev/null)")

cat <<EOF
{
  "hostname": "$HOST",
  "timestamp": "$TIMESTAMP",
  "system_info_raw": "$SYSTEM_INFO",
  "os_release_raw": "$OS_RELEASE",
  "cpu_info_raw": "$CPU_INFO",
  "memory_raw": "$MEMORY",
  "disks_raw": "$DISKS",
  "pci_raw": "$PCI",
  "vga_raw": "$VGA",
  "nvidia_raw": "$NVIDIA",
  "network_raw": "$NETWORK",
  "virtualization_raw": "$VIRT",
  "docker_raw": "$DOCKER"
}
EOF
