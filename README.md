# Sentinela — Módulo de Inventário de Infraestrutura

> Sistema distribuído de inventário de servidores com agentes Docker, publicação MQTT, persistência MySQL e frontend SPS Tipo 2.

**Versão:** 2.1.0 | **Atualizado:** 2026-05-21 | **Servidor central:** 10.0.0.139

---

## Sumário

1. [Visão Geral](#1-visão-geral)
2. [Arquitetura](#2-arquitetura)
3. [Infraestrutura — Servidores](#3-infraestrutura--servidores)
4. [Agente Docker — Instalação por Servidor](#4-agente-docker--instalação-por-servidor)
5. [API do Agente](#5-api-do-agente)
6. [Pipeline MQTT → MySQL](#6-pipeline-mqtt--mysql)
7. [Banco de Dados MySQL](#7-banco-de-dados-mysql)
8. [Coletor Central](#8-coletor-central)
9. [Frontend](#9-frontend)
10. [Scripts de Manutenção](#10-scripts-de-manutenção)
11. [Troubleshooting](#11-troubleshooting)

---

## 1. Visão Geral

O módulo de inventário coleta dados de hardware, sistema operacional, containers Docker e portas abertas de cada servidor da infraestrutura do Sítio Pé de Serra. Os dados são publicados via MQTT, persistidos em MySQL e exibidos em um painel web unificado.

**O que é coletado por servidor:**
- Sistema Operacional, Kernel, Arquitetura
- CPU — modelo, núcleos, frequência máxima
- Memória — total, usado, livre
- Discos — partições montadas e tamanho
- Rede — interfaces ativas com IPs
- Containers Docker — nome, imagem, status, portas mapeadas
- Portas abertas no host — bind, protocolo, serviço

---

## 2. Arquitetura

```
┌──────────────────────────────────────────────────────────────┐
│  SERVIDORES — Agentes Docker (porta 8090)                    │
│  10.0.0.5 · 10.0.0.139 · 10.0.0.141 · 10.0.2.148 · 10.0.0.37 │
└───────────────────┬──────────────────────────────────────────┘
                    │ HTTP REST (Bearer token)
                    ▼
          inventory_agent.php
          (PHP 8.2 + Apache dentro do container)
                    │
                    ├── Coleta: CPU, mem, disco, rede, docker ps --all
                    ├── Lê: /host/proc, /host/sys, /var/run/docker.sock
                    └── Publica: MQTT → broker 10.0.0.141:1883
                                 tópico: sentinela/inventory/{hostname}
                                 retain: true

                    │
                    ▼
         ┌──────────────────────┐
         │  Broker MQTT         │
         │  10.0.0.141:1883     │
         └──────────┬───────────┘
                    │ subscribe sentinela/inventory/#
                    ▼
         ┌──────────────────────┐
         │  mqtt_inventory_     │
         │  persist.py          │
         │  (systemd 10.0.0.139)│
         └──────────┬───────────┘
                    │ INSERT
                    ▼
         ┌──────────────────────┐
         │  MySQL inventory_db  │
         │  10.0.0.139:3306     │
         │  (container          │
         │   mysql-mqtt)        │
         └──────────┬───────────┘
                    │ SELECT
                    ▼
         ┌──────────────────────┐
         │  inventory_collector │
         │  .php + index.php    │
         │  http://10.0.0.139/  │
         │  inventory/          │
         └──────────────────────┘
```

---

## 3. Infraestrutura — Servidores

| Hostname | IP | Arch | OS | Docker | Compose | GID Docker |
|---|---|---|---|---|---|---|
| www | 10.0.0.5 | aarch64 | Ubuntu 18.04 | 20.10 | plugin | 127 |
| homeassistant | 10.0.0.139 | aarch64 | Ubuntu 22.04 | 29.2 | plugin | 143 |
| mqtt | 10.0.0.141 | aarch64 | Ubuntu 18.04 | 20.10 | 1.17 (legado) | 127 |
| ia | 10.0.2.148 | x86_64 | Ubuntu 24.04 | 29.3 | plugin | 988 |
| Ubuntu-desktop | 10.0.0.37 | x86_64 | Ubuntu 24.04 | 29.4 | plugin | 140 |

### Containers Docker por servidor

**10.0.0.5 — www**
| Container | Porta | Descrição |
|---|---|---|
| inventory-agent | 8090 | Agente de inventário |
| sentinela-swagger | 8091 | Swagger UI |
| conectados | 3002 | Monitor de rede |
| tasmota-monitor | 3030 | Monitor Tasmota |
| app-rusty | 3000 | App Rusty Marcellini |
| rfid | — | Backend RFID |
| rep | — | Repositório |
| weather-api-igarape | 3010 | API de clima |

**10.0.0.139 — homeassistant**
| Container | Porta | Descrição |
|---|---|---|
| inventory-agent | 8090 | Agente de inventário |
| sentinela-swagger | 8093 | Swagger UI |
| sentinela-core | 5050 | Core do Sentinela |
| sentinela-dashboard | 3011 | Dashboard Sentinela |
| sentinela-devices | 3020 | Dashboard de dispositivos |
| sentinela-ia-monitor | 3025 | Monitor IA |
| sentinela-mqtt-hub | 4000 | Hub MQTT |
| sentinela-portal | 8088 | Portal Sentinela |
| critical-events | 8092 | Eventos críticos |
| alarme-pds | 8091 | Sistema de alarme |
| mysql-mqtt | 3306 | Banco de dados |
| phpmyadmin-mqtt | 8080 | phpMyAdmin |
| n8n | 5678 | Automação n8n |
| frigate | — | NVR (parado) |

**10.0.0.141 — mqtt**
| Container | Porta | Descrição |
|---|---|---|
| inventory-agent | 8090 | Agente de inventário |
| mosquitto | 1883, 9001 | Broker MQTT |

**10.0.2.148 — ia**
| Container | Porta | Descrição |
|---|---|---|
| inventory-agent | 8090 | Agente de inventário |
| ollama | 11434 | LLM Ollama |
| open-webui | 3001 | Interface web LLM |
| mcp-server | 3100 | MCP Server |
| grafana | 3000 | Grafana |
| tuya-mqtt-bridge | 8877 | Bridge Tuya/MQTT |
| docai-* | 5005-5007 | APIs DocAI |

**10.0.0.37 — Ubuntu-desktop**
| Container | Porta | Descrição |
|---|---|---|
| inventory-agent | 8090 | Agente de inventário |
| sentinela-swagger | 8091 | Swagger UI |

---

## 4. Agente Docker — Instalação por Servidor

### Estrutura em cada servidor

```
/opt/inventory/
├── agent/
│   ├── Dockerfile
│   ├── docker-compose.yml
│   ├── agent.sh
│   ├── docker-manage.sh
│   ├── api/
│   │   ├── inventory_agent.php   ← coleta e publica MQTT
│   │   └── openapi.yaml
│   ├── scripts/
│   │   └── inventory.sh          ← coleta shell (cpu, mem, disco, rede)
│   └── swagger/
└── docker-manage.sh              ← script de manutenção
```

### Requisitos por servidor

**Todos os servidores:**
- Docker instalado e rodando
- `/var/run/docker.sock` acessível
- Porta 8090 liberada no firewall
- Acesso ao broker MQTT 10.0.0.141:1883

**Ubuntu 18.04 (www / mqtt):**
- Docker 20.10+
- `docker-compose` 1.17+ ou `docker compose` plugin

**Ubuntu 22.04+ / 24.04 (homeassistant / ia / desktop):**
- Docker 26+
- `docker compose` plugin instalado

### Instalação — passo a passo

```bash
# 1. Cria diretório
sudo mkdir -p /opt/inventory/agent
sudo chown -R epaminondas:epaminondas /opt/inventory

# 2. Copia os arquivos do agente (do Mac)
scp -r ~/inventory/agent/ epaminondas@IP:/opt/inventory/

# 3. Ajusta GID do docker no Dockerfile conforme o servidor
#    (ver tabela de GIDs na seção 3)
nano /opt/inventory/agent/Dockerfile
# Linha: RUN groupadd -f -g {GID} dockerhost && usermod -aG dockerhost www-data

# 4. Build e start
cd /opt/inventory/agent
chmod +x agent.sh docker-manage.sh
./agent.sh rebuild

# 5. Testa
curl -s -H "Authorization: Bearer sentinela_token_123" \
  http://localhost:8090/api/inventory_agent.php | python3 -m json.tool | grep '"hostname"'
```

### Atenção — servidor 10.0.0.141 (mqtt)

O servidor mqtt usa `docker-compose` 1.17 (muito antigo) que não suporta a sintaxe atual. O `agent.sh rebuild` detecta isso automaticamente e usa `docker run` direto:

```bash
docker rm -f inventory-agent
docker run -d \
  --name inventory-agent \
  --restart always \
  --network host \
  -v /proc:/host/proc:ro \
  -v /sys:/host/sys:ro \
  -v /etc/os-release:/host/os-release:ro \
  -v /etc/hostname:/host/etc/hostname:ro \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v /opt/inventory/agent/scripts:/scripts:ro \
  -e HOST_PROC=/host/proc \
  -e HOST_SYS=/host/sys \
  inventory-agent
```

### network_mode: host

Todos os containers usam `network_mode: host` para que o PHP consiga ler `/proc/net/tcp` do host e coletar as portas abertas corretamente. Com bridge network o `/proc/net/tcp` retornaria apenas as portas do namespace do container.

O Apache dentro do container é configurado para escutar na porta **8090** (não 80) para não conflitar com o Apache do host.

---

## 5. API do Agente

### Endpoint

```
GET http://{IP}:8090/api/inventory_agent.php
Authorization: Bearer sentinela_token_123
```

### Resposta JSON

```json
{
  "status": "success",
  "data": {
    "hostname": "homeassistant",
    "host_ip": "10.0.0.139",
    "timestamp": "2026-05-21T18:00:00Z",
    "system": {
      "cpu_cores": 8,
      "mem_total_kb": 16000000,
      "uptime_seconds": 253100
    },
    "services": {
      "open_ports": [
        { "bind": "0.0.0.0:22", "port": 22 },
        { "bind": "0.0.0.0:3306", "port": 3306 }
      ],
      "docker": [
        {
          "name": "sentinela-core",
          "image": "sentinela-sentinela-core",
          "status": "Up 2 days (healthy)",
          "ports_raw": "0.0.0.0:5050->5050/tcp",
          "mapped_ports": [5050]
        }
      ]
    },
    "raw": {
      "os_release": "...",
      "cpu_info": "...",
      "memory": "...",
      "disks": "...",
      "network": "...",
      "docker_info": "..."
    },
    "_agent": {
      "hostname": "homeassistant",
      "execution_ms": 343,
      "timestamp": "2026-05-21T18:00:00+00:00",
      "agent_version": "2.1.0",
      "api_version": "v1"
    }
  },
  "meta": {
    "version": "v1",
    "exec_ms": 343,
    "timestamp": 1779375375
  }
}
```

### Mudanças v2.1.0

- `docker ps` alterado para `docker ps --all` — inclui containers parados
- `network_mode: host` — coleta portas reais do host via `/proc/net/tcp`
- GID dockerhost mapeado por servidor — acesso correto ao socket Docker

---

## 6. Pipeline MQTT → MySQL

### Publicação pelo agente

A cada chamada HTTP ao agente, o `inventory_agent.php` publica o inventário completo no broker MQTT:

```
Tópico:  sentinela/inventory/{hostname}
Broker:  10.0.0.141:1883
Usuário: mqtt
Senha:   planeta
Retain:  true
QoS:     1
```

O `retain: true` garante que o persistidor receba o último snapshot imediatamente ao se conectar, sem precisar esperar nova coleta.

### Persistidor — mqtt_inventory_persist.py

Serviço Python rodando como **systemd** no servidor 10.0.0.139:

```
/opt/inventory/mqtt_inventory_persist.py
```

**Gerenciamento:**
```bash
sudo systemctl status mqtt-inventory-persist
sudo systemctl restart mqtt-inventory-persist
sudo journalctl -u mqtt-inventory-persist -f
```

**Funcionamento:**
1. Conecta ao broker 10.0.0.141:1883
2. Subscreve `sentinela/inventory/#`
3. Para cada mensagem recebida:
   - Faz parse do JSON
   - Identifica o servidor pelo hostname
   - Insere snapshot em `inventory_snapshots`
   - Insere serviços (portas + containers) em `server_services`
4. Reconecta automaticamente em caso de queda

---

## 7. Banco de Dados MySQL

**Container:** `mysql-mqtt` em 10.0.0.139:3306
**Banco:** `inventory_db`
**Usuário:** `root` / `Ep@m1n0nd@s`

### Tabelas

#### servers
Cadastro fixo dos 5 servidores monitorados.

| Coluna | Tipo | Descrição |
|---|---|---|
| id | INT PK | Identificador |
| hostname | VARCHAR | Nome do host |
| ip | VARCHAR | IP do servidor |
| label | VARCHAR | Nome amigável |
| description | VARCHAR | Descrição |
| mqtt_topic | VARCHAR | Tópico MQTT do servidor |

#### inventory_snapshots
Um registro por coleta de cada servidor.

| Coluna | Tipo | Descrição |
|---|---|---|
| id | INT PK | Identificador |
| server_id | INT FK | Referência a servers |
| hostname | VARCHAR | Hostname coletado |
| ip | VARCHAR | IP coletado |
| collected_at | DATETIME | Data/hora da coleta |
| received_at | DATETIME | Data/hora da persistência |
| cpu_cores | INT | Número de núcleos |
| mem_total | VARCHAR | Memória total (ex: 15Gi) |
| mem_used | VARCHAR | Memória usada |
| mem_free | VARCHAR | Memória livre |
| uptime_sec | INT | Uptime em segundos |
| os_name | VARCHAR | Nome do OS |
| arch | VARCHAR | Arquitetura (aarch64/x86_64) |
| agent_version | VARCHAR | Versão do agente |
| exec_ms | INT | Tempo de execução do agente |
| payload_json | JSON | Payload completo da coleta |

#### server_services
Portas e containers de cada snapshot.

| Coluna | Tipo | Descrição |
|---|---|---|
| id | INT PK | Identificador |
| snapshot_id | INT FK | Referência a inventory_snapshots |
| port | INT | Número da porta |
| protocol | VARCHAR | tcp/udp |
| bind_addr | VARCHAR | Endereço de bind |
| container | VARCHAR | Nome do container (se Docker) |
| scope | ENUM | host / docker |

### Consultas úteis

```sql
-- Último snapshot de cada servidor
SELECT hostname, ip, collected_at, cpu_cores, mem_used, os_name
FROM inventory_snapshots
ORDER BY id DESC LIMIT 5;

-- Containers por servidor
SELECT s.hostname, ss.container, ss.port
FROM server_services ss
JOIN inventory_snapshots s ON ss.snapshot_id = s.id
WHERE ss.scope = 'docker'
ORDER BY s.hostname, ss.port;

-- Histórico de uso de memória
SELECT hostname, collected_at, mem_used, mem_total
FROM inventory_snapshots
WHERE hostname = 'homeassistant'
ORDER BY collected_at DESC LIMIT 20;
```

---

## 8. Coletor Central

**Arquivo:** `/var/www/html/inventory/api/inventory_central/inventory_collector.php`
**Servidor:** 10.0.0.139
**URL:** `http://10.0.0.139/inventory/api/inventory_central/inventory_collector.php`

### Funcionamento

1. Conecta ao MySQL `inventory_db`
2. Busca a tabela `servers` (5 servidores cadastrados)
3. Para cada servidor:
   - Faz **ping TCP real** na porta 8090 com timeout de 2 segundos
   - Status `online` = conexão bem-sucedida; `offline` = falha
   - Busca último snapshot de `inventory_snapshots`
   - Busca serviços de `server_services`
   - Extrai docker do `payload_json`
4. Retorna JSON consolidado

### Parâmetros GET

| Parâmetro | Descrição | Exemplo |
|---|---|---|
| server | Filtra por hostname ou IP | `?server=homeassistant` |
| history | Número de snapshots históricos | `?history=10` |

### Resposta JSON

```json
{
  "generated_at": "2026-05-21T18:00:00-03:00",
  "summary": { "total": 5, "online": 5, "offline": 0 },
  "servers": [
    {
      "id": 1,
      "hostname": "homeassistant",
      "ip": "10.0.0.139",
      "label": "homeassistant",
      "description": "Servidor Sentinela / Home Assistant",
      "online": true,
      "last_seen": "2026-05-21 18:00:00",
      "snapshot": { "cpu_cores": 8, "mem_used": "6.0Gi", ... },
      "services": [ { "port": 22, "bind_addr": "0.0.0.0", "scope": "host" } ],
      "docker": [ { "name": "sentinela-core", "status": "Up 2 days (healthy)", ... } ],
      "raw": { ... }
    }
  ]
}
```

---

## 9. Frontend

**URL:** `http://10.0.0.139/inventory/`
**Arquivo:** `/var/www/html/inventory/index.php`
**Design:** SPS Tipo 2 — header branco fixo, fundo #f0f0f0, cards brancos

### Estrutura da interface

```
┌─────────────────────────────────────────────────┐
│  Header — Logo · Título · Countdown · Atualizar │
├─────────────────────────────────────────────────┤
│  Resumo — Servidores · Online · Offline ·       │
│           Serviços · Containers                 │
├─────────────────────────────────────────────────┤
│  Abas por servidor:                             │
│  [homeassistant] [ia] [mqtt] [Ubuntu-desktop]   │
│                  [www]                          │
├─────────────────────────────────────────────────┤
│  Painel do servidor selecionado:                │
│                                                 │
│  ┌─────────┐ ┌─────────┐ ┌──────────┐ ┌──────┐ │
│  │ Sistema │ │   CPU   │ │ Memória  │ │Docker│ │
│  │ OS/Arch │ │Modelo   │ │Total/Uso │ │Total │ │
│  │ Uptime  │ │Núcleos  │ │Livre+bar │ │Ativo │ │
│  └─────────┘ └─────────┘ └──────────┘ └──────┘ │
│                                                 │
│  ┌─────────────────┐ ┌───────────────────────┐  │
│  │     Discos      │ │        Rede           │  │
│  │ Partições+size  │ │ Interfaces + IPs      │  │
│  └─────────────────┘ └───────────────────────┘  │
│                                                 │
│  CONTAINERS DOCKER — 13 ativos · 1 parado       │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐         │
│  │sentinela │ │ mysql-   │ │  n8n     │  ...    │
│  │-core     │ │ mqtt     │ │          │         │
│  │● Ativo   │ │● Ativo   │ │● Ativo   │         │
│  └──────────┘ └──────────┘ └──────────┘         │
│  ┌──────────┐                                   │
│  │ frigate  │  ← fundo vermelho claro           │
│  │● Parado  │                                   │
│  └──────────┘                                   │
│                                                 │
│  PORTAS ABERTAS — 19 detectadas                 │
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐            │
│  │  22  │ │ 3306 │ │ 5050 │ │ 8088 │  ...       │
│  │ SSH  │ │MySQL │ │Core  │ │Portal│            │
│  │LOCAL │ │CONT. │ │CONT. │ │CONT. │            │
│  └──────┘ └──────┘ └──────┘ └──────┘            │
└─────────────────────────────────────────────────┘
```

### Funcionalidades

- **Auto-refresh** configurável: manual, 30s, 1min, 2min, 5min com countdown
- **Status online/offline** por ping TCP real na porta 8090
- **Containers parados** — fundo vermelho claro (`#fff5f5`)
- **Portas coloridas** — verde = container Docker; amarelo = serviço local
- **Portas efêmeras filtradas** — portas > 32767 não exibidas
- **Mapa de serviços** — 50+ portas conhecidas mapeadas com nome
- **Botão Voltar** — navega para página anterior

---

## 10. Scripts de Manutenção

### agent.sh

```bash
cd /opt/inventory/agent
./agent.sh rebuild   # reconstrói a imagem e recria o container
./agent.sh status    # status do container
./agent.sh logs      # logs em tempo real
```

### docker-manage.sh

Script adaptativo que detecta a versão do Docker disponível:

```bash
/opt/inventory/docker-manage.sh status              # lista containers
/opt/inventory/docker-manage.sh ps                  # todos os containers
/opt/inventory/docker-manage.sh logs inventory-agent
/opt/inventory/docker-manage.sh restart sentinela-core
/opt/inventory/docker-manage.sh stop frigate
/opt/inventory/docker-manage.sh start frigate
/opt/inventory/docker-manage.sh rebuild             # rebuild inventory-agent
/opt/inventory/docker-manage.sh prune               # limpeza de imagens
/opt/inventory/docker-manage.sh info                # info do Docker
```

### Disparo manual de coleta

```bash
# Coleta em todos os servidores
for IP in 10.0.0.5 10.0.0.139 10.0.0.141 10.0.2.148 10.0.0.37; do
  curl -s -H "Authorization: Bearer sentinela_token_123" \
    http://$IP:8090/api/inventory_agent.php > /dev/null && echo "$IP OK"
done
```

### Verificar persistidor MQTT

```bash
# Status do serviço
sudo systemctl status mqtt-inventory-persist

# Logs em tempo real
sudo journalctl -u mqtt-inventory-persist -f

# Verificar dados no banco
mysql -u root -pEp@m1n0nd@s -h 127.0.0.1 -P 3306 inventory_db \
  -e "SELECT hostname, ip, collected_at, cpu_cores, mem_used FROM inventory_snapshots ORDER BY id DESC LIMIT 10;"
```

---

## 11. Troubleshooting

### Servidor aparece offline no frontend

```bash
# Testa conectividade TCP na porta 8090
curl -v http://IP:8090/api/inventory_agent.php \
  -H "Authorization: Bearer sentinela_token_123" 2>&1 | grep -E "Connected|refused|HTTP"
```

### Docker vazio no painel

```bash
# Verifica GID do docker no host
getent group docker

# Verifica GID dentro do container
docker exec inventory-agent getent group dockerhost

# Se forem diferentes, corrige o Dockerfile e rebuild
sed -i 's/groupadd -f -g [0-9]* dockerhost/groupadd -f -g {GID_CORRETO} dockerhost/' \
  /opt/inventory/agent/Dockerfile
./agent.sh rebuild
```

### Portas não aparecem

O container precisa estar em `network_mode: host`. Verifica:

```bash
docker inspect inventory-agent | grep NetworkMode
# Deve retornar: "NetworkMode": "host"
```

### Persistidor MQTT não está salvando

```bash
# Testa conexão com o broker
mosquitto_pub -h 10.0.0.141 -p 1883 -u mqtt -P planeta \
  -t "test/ping" -m "ok" && echo "Broker OK"

# Verifica último registro no banco
mysql -u root -pEp@m1n0nd@s -h 127.0.0.1 -P 3306 inventory_db \
  -e "SELECT hostname, collected_at FROM inventory_snapshots ORDER BY id DESC LIMIT 5;"
```

### Container não sobe no 10.0.0.141 (Docker antigo)

O servidor mqtt usa Docker 20.10 com docker-compose 1.17 que não suporta a sintaxe atual. Sempre use o modo manual:

```bash
docker rm -f inventory-agent
docker build -t inventory-agent /opt/inventory/agent/
docker run -d --name inventory-agent --restart always --network host \
  -v /proc:/host/proc:ro -v /sys:/host/sys:ro \
  -v /etc/os-release:/host/os-release:ro \
  -v /etc/hostname:/host/etc/hostname:ro \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v /opt/inventory/agent/scripts:/scripts:ro \
  -e HOST_PROC=/host/proc -e HOST_SYS=/host/sys \
  inventory-agent
```

---

## Repositório

**GitHub:** [Epaminondaslage/inventory](https://github.com/Epaminondaslage/inventory)

```
inventory/
├── opt-inventory/
│   └── agent/              ← agente Docker (copiado para cada servidor)
│       ├── Dockerfile
│       ├── docker-compose.yml
│       ├── agent.sh
│       ├── docker-manage.sh
│       ├── api/
│       │   ├── inventory_agent.php
│       │   └── openapi.yaml
│       └── scripts/
│           └── inventory.sh
└── var-www-html-inventory/ ← frontend (deploy no 10.0.0.139)
    ├── index.php
    ├── css/style.css
    ├── img/
    └── api/
        └── inventory_central/
            └── inventory_collector.php
```
