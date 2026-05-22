#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
mqtt_inventory_persist.py — Persistência de inventário via MQTT → MySQL
Projeto: Sentinela — Sítio Pé de Serra

Funcionalidades:
  - Subscreve sentinela/inventory/# no broker 10.0.0.141
  - Ao receber payload, parseia JSON e insere em inventory_db:
      inventory_snapshots — snapshot completo
      server_services     — portas e containers do snapshot
  - Cria registro em servers se hostname ainda não existir
  - Loga cada operação com timestamp
  - Reconecta automaticamente em caso de queda do broker

Deploy:
  pip3 install paho-mqtt mysql-connector-python --break-system-packages
  python3 mqtt_inventory_persist.py
  # ou via supervisord (ver abaixo)

Supervisord example:
  [program:mqtt-inventory-persist]
  command=python3 /opt/inventory/mqtt_inventory_persist.py
  autostart=true
  autorestart=true
  user=epaminondas
  stdout_logfile=/var/log/mqtt_inventory_persist.log
  stderr_logfile=/var/log/mqtt_inventory_persist.log
"""

import json
import re
import time
import logging
from datetime import datetime, timezone

import paho.mqtt.client as mqtt
import mysql.connector
from mysql.connector import pooling

# ── Configuração ──────────────────────────────────────────
MQTT_BROKER   = '10.0.0.141'
MQTT_PORT     = 1883
MQTT_USER     = 'mqtt'
MQTT_PASS     = 'planeta'
MQTT_TOPIC    = 'sentinela/inventory/#'
MQTT_CLIENT_ID = 'inventory-persist'

MYSQL_HOST    = '10.0.0.139'
MYSQL_PORT    = 3306
MYSQL_USER    = 'root'          # ajuste se necessário
MYSQL_PASS    = 'Ep@m1n0nd@s' # ajuste
MYSQL_DB      = 'inventory_db'

LOG_LEVEL     = logging.INFO
# ─────────────────────────────────────────────────────────

logging.basicConfig(
    level=LOG_LEVEL,
    format='%(asctime)s [%(levelname)s] %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)
log = logging.getLogger(__name__)

# ── Pool MySQL ────────────────────────────────────────────
def create_pool():
    return pooling.MySQLConnectionPool(
        pool_name='inventory',
        pool_size=3,
        host=MYSQL_HOST,
        port=MYSQL_PORT,
        user=MYSQL_USER,
        password=MYSQL_PASS,
        database=MYSQL_DB,
        charset='utf8mb4',
        autocommit=False,
    )

pool = None

def get_conn():
    global pool
    if pool is None:
        pool = create_pool()
    return pool.get_connection()

# ── Helpers ───────────────────────────────────────────────
def parse_os(os_release: str) -> str:
    """Extrai PRETTY_NAME do os_release."""
    m = re.search(r'PRETTY_NAME="([^"]+)"', os_release or '')
    return m.group(1) if m else None

def parse_arch(cpu_info: str) -> str:
    """Extrai Architecture do lscpu."""
    m = re.search(r'Architecture:\s+(\S+)', cpu_info or '')
    return m.group(1) if m else None

def parse_mem(memory_str: str, field: str) -> str:
    """Extrai total/used/free da saída de free -h."""
    for line in (memory_str or '').splitlines():
        if line.startswith('Mem:'):
            parts = line.split()
            mapping = {'total': 1, 'used': 2, 'free': 3}
            idx = mapping.get(field)
            return parts[idx] if idx and len(parts) > idx else None
    return None

def fmt_uptime(seconds) -> str:
    try:
        s = int(float(seconds))
        d, rem = divmod(s, 86400)
        h, rem = divmod(rem, 3600)
        m = rem // 60
        return f'{d}d {h}h {m}m' if d else f'{h}h {m}m'
    except Exception:
        return None

# ── Garante servidor no cadastro ──────────────────────────
def ensure_server(cur, hostname: str, ip: str) -> int:
    """Retorna server_id, criando registro se não existir."""
    cur.execute('SELECT id FROM servers WHERE ip = %s', (ip,))
    row = cur.fetchone()
    if row:
        return row[0]
    topic = f'sentinela/inventory/{hostname}'
    cur.execute(
        'INSERT INTO servers (hostname, ip, label, description, mqtt_topic) VALUES (%s,%s,%s,%s,%s)',
        (hostname, ip, hostname, 'Auto-descoberto', topic)
    )
    log.info(f'Novo servidor cadastrado: {hostname} ({ip})')
    return cur.lastrowid

# ── Persiste snapshot ─────────────────────────────────────
def persist(payload_str: str, topic: str):
    try:
        data = json.loads(payload_str)
    except json.JSONDecodeError as e:
        log.error(f'JSON inválido no tópico {topic}: {e}')
        return

    hostname = data.get('hostname') or topic.split('/')[-1]
    host_ip  = data.get('host_ip') or '0.0.0.0'
    system   = data.get('system', {})
    raw      = data.get('raw', {})
    agent    = data.get('_agent', {})
    services = data.get('services', {})

    # Campos calculados
    os_name   = parse_os(raw.get('os_release', ''))
    arch      = parse_arch(raw.get('cpu_info', ''))
    mem_total = parse_mem(raw.get('memory', ''), 'total')
    mem_used  = parse_mem(raw.get('memory', ''), 'used')
    mem_free  = parse_mem(raw.get('memory', ''), 'free')

    try:
        collected_at_str = data.get('timestamp') or agent.get('timestamp')
        collected_at = datetime.fromisoformat(
            collected_at_str.replace('Z', '+00:00')
        ).astimezone(timezone.utc).replace(tzinfo=None)
    except Exception:
        collected_at = datetime.utcnow()

    try:
        conn = get_conn()
        cur  = conn.cursor()

        server_id = ensure_server(cur, hostname, host_ip)

        # Insere snapshot
        cur.execute("""
            INSERT INTO inventory_snapshots
              (server_id, hostname, ip, collected_at,
               cpu_cores, mem_total, mem_used, mem_free,
               uptime_sec, os_name, arch,
               payload_json, agent_version, api_version, exec_ms)
            VALUES (%s,%s,%s,%s, %s,%s,%s,%s, %s,%s,%s, %s,%s,%s,%s)
        """, (
            server_id, hostname, host_ip, collected_at,
            system.get('cpu_cores'),
            mem_total, mem_used, mem_free,
            int(float(system.get('uptime_seconds') or 0)) or None,
            os_name, arch,
            payload_str,
            agent.get('agent_version'),
            agent.get('api_version'),
            agent.get('execution_ms'),
        ))
        snapshot_id = cur.lastrowid

        # Insere serviços — portas abertas
        for svc in services.get('open_ports', []):
            bind = svc.get('bind', '')
            # bind formato "IP:PORT" ou "[::]:PORT"
            m = re.match(r'^(.+):(\d+)$', bind)
            if not m:
                continue
            bind_addr = m.group(1).strip('[]') or '0.0.0.0'
            port = int(m.group(2))
            cur.execute("""
                INSERT INTO server_services
                  (snapshot_id, server_id, port, protocol, bind_addr, scope)
                VALUES (%s,%s,%s,'tcp',%s,'host')
            """, (snapshot_id, server_id, port, bind_addr))

        # Insere serviços — containers Docker
        for ctr in services.get('docker', []):
            name  = ctr.get('name', '')
            ports = ctr.get('ports_raw', '')
            # Parseia portas do formato "0.0.0.0:3000->80/tcp"
            for m in re.finditer(r'(?:[\d.]+):(\d+)->', ports or ''):
                port = int(m.group(1))
                cur.execute("""
                    INSERT INTO server_services
                      (snapshot_id, server_id, port, protocol, container, scope)
                    VALUES (%s,%s,%s,'tcp',%s,'docker')
                """, (snapshot_id, server_id, port, name))

        conn.commit()
        log.info(f'Snapshot persistido: {hostname} ({host_ip}) — snapshot_id={snapshot_id}')

    except Exception as e:
        log.error(f'Erro ao persistir {hostname}: {e}')
        try:
            conn.rollback()
        except Exception:
            pass
    finally:
        try:
            cur.close()
            conn.close()
        except Exception:
            pass

# ── MQTT callbacks ────────────────────────────────────────
def on_connect(client, userdata, flags, rc):
    if rc == 0:
        log.info(f'Conectado ao broker {MQTT_BROKER}:{MQTT_PORT}')
        client.subscribe(MQTT_TOPIC, qos=1)
        log.info(f'Subscrito em: {MQTT_TOPIC}')
    else:
        log.error(f'Falha na conexão MQTT, rc={rc}')

def on_disconnect(client, userdata, rc):
    log.warning(f'Desconectado do broker (rc={rc}), reconectando...')

def on_message(client, userdata, msg):
    topic   = msg.topic
    payload = msg.payload.decode('utf-8', errors='replace')
    log.debug(f'Mensagem recebida: {topic} ({len(payload)} bytes)')
    persist(payload, topic)

# ── Main ──────────────────────────────────────────────────
def main():
    log.info('mqtt_inventory_persist iniciando...')

    client = mqtt.Client(client_id=MQTT_CLIENT_ID, clean_session=True)
    client.username_pw_set(MQTT_USER, MQTT_PASS)
    client.on_connect    = on_connect
    client.on_disconnect = on_disconnect
    client.on_message    = on_message

    # Reconexão automática
    client.reconnect_delay_set(min_delay=5, max_delay=60)

    while True:
        try:
            client.connect(MQTT_BROKER, MQTT_PORT, keepalive=60)
            client.loop_forever()
        except Exception as e:
            log.error(f'Erro de conexão: {e} — tentando novamente em 10s')
            time.sleep(10)

if __name__ == '__main__':
    main()
