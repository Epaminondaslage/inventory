
#  Arquitetura — Sentinela Inventory

## Visão Geral (Camadas)

```text
┌──────────────────────────────────────────────┐
│            CAMADA DE COLETA (AGENTES)        │
├──────────────────────────────────────────────┤
│ Servidores Monitorados (Linux / HA / etc)    │
│ - API HTTP (JSON)                            │
│ - Métricas locais                            │
│ - CPU / MEM / DISCO / REDE                   │
└──────────────────────────────────────────────┘
                     ↓ HTTP (JSON)
┌──────────────────────────────────────────────┐
│        CAMADA DE PROCESSAMENTO CENTRAL       │
├──────────────────────────────────────────────┤
│ inventory_collector.php                      │
│                                              │
│ - consulta APIs                              │
│ - trata offline/online                       │
│ - normaliza dados                            │
│ - salva métricas                             │
│ - salva logs completos                       │
└──────────────────────────────────────────────┘
                     ↓ PDO (MySQL)
┌──────────────────────────────────────────────┐
│            CAMADA DE DADOS (DB)              │
├──────────────────────────────────────────────┤
│ Container: mysql-mqtt                        │
│ Host: 10.0.0.139:3306                        │
│                                              │
│ Banco: sentinela                             │
│                                              │
│ Tabelas:                                     │
│ - server_inventory_logs (raw + auditoria)    │
│ - server_metrics_full (métricas Grafana)     │
└──────────────────────────────────────────────┘
         ↓                          ↓
     SQL (read)                SQL (read)
         ↓                          ↓
┌──────────────────────┐   ┌──────────────────────┐
│  FRONTEND PHP        │   │      GRAFANA         │
├──────────────────────┤   ├──────────────────────┤
│ index.php            │   │ dashboards           │
│                      │   │                      │
│ - tabela status      │   │ - CPU                │
│ - última atualização │   │ - memória            │
│ - visão operacional  │   │ - uptime             │
│                      │   │ - histórico          │
└──────────────────────┘   └──────────────────────┘
```

---

# 🌐 Diagrama de Rede (Realista)

```text
                 ┌────────────────────────────┐
                 │      10.0.0.148            │
                 │      GRAFANA               │
                 │      :3000                 │
                 └────────────┬───────────────┘
                              │
                              │ SQL
                              │
                 ┌────────────▼───────────────┐
                 │      10.0.0.139            │
                 │      MYSQL (Docker)        │
                 │      mysql-mqtt            │
                 └────────────┬───────────────┘
                              │
                              │ PDO
                              │
                 ┌────────────▼───────────────┐
                 │      SERVIDOR CENTRAL      │
                 │  inventory_collector.php   │
                 │  /var/www/html/inventory   │
                 └────────────┬───────────────┘
                              │
                              │ HTTP (API)
                              │
 ┌───────────────┬────────────┴────────────┬───────────────┐
 │               │                         │               │
 ▼               ▼                         ▼               ▼
10.0.0.5     10.0.0.37                10.0.0.141      10.0.0.139
WEB          DESKTOP                  MQTT            HOMEASSISTANT
SERVER       UBUNTU                   SERVER          (AGENTE)
```

---

# 🧩 Componentes Técnicos

## 1. Coletor

Arquivo:

```text
/var/www/html/inventory/api/inventory_central/inventory_collector.php
```

Função:

* polling distribuído
* ingestão de dados
* persistência

---

## 2. Banco (Container)

Container:

```text
mysql-mqtt
```

Porta:

```text
3306
```

Rede:

* acessível via host `10.0.0.139`

---

## 3. Grafana

Container:

```text
grafana
```

Porta:

```text
3000
```

Função:

* leitura direta do MySQL
* dashboards analíticos

---

## 4. Frontend PHP

Arquivo:

```text
/var/www/html/inventory/index.php
```

Função:

* visão operacional
* status atual
* última coleta

---

# 🔁 Ciclo de Vida dos Dados

```text
1. Cron dispara collector (3 min)
2. Collector chama API dos servidores
3. Recebe JSON bruto
4. Processa / normaliza
5. Salva:
   - logs completos
   - métricas otimizadas
6. Grafana lê métricas
7. Frontend mostra estado atual
```

---

# 🧠 Estratégia de Dados

## Separação de responsabilidades

| Camada   | Função                |
| -------- | --------------------- |
| Logs     | Debug, auditoria      |
| Métricas | Performance, gráficos |

---

# ⚙️ Estratégia de Escalabilidade

* Banco centralizado
* coleta distribuída
* frontend desacoplado
* grafana independente

---
