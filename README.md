# Sentinela – Módulo de Inventário de Infraestrutura

**Versão Atualizada (Docker + Collector Padronizado)**
**Data de atualização: 2026-04**

---

## 1. Objetivo

Este módulo integra a arquitetura **Sentinela – Monitoramento de Infraestrutura**, com foco na **coleta distribuída e consolidação centralizada de inventário de servidores**.

O sistema foi projetado para:

* Padronizar coleta de dados (CPU, memória, disco, OS, rede)
* Executar coleta via **agente isolado em container Docker**
* Expor dados via API HTTP segura (token)
* Centralizar informações em um servidor coletor
* Exibir dados em painel web estruturado
* Permitir expansão para:

  * MQTT
  * automação
  * decisão inteligente
  * dashboards executivos

---

## 2. Arquitetura Geral

```
+------------------------+
|   SERVIDORES (Agents)  |
|  10.0.0.141, .37, etc  |
+-----------+------------+
            |
            v
   inventory_agent.php (Docker)
            |
            v
+------------------------+
|   COLETOR CENTRAL      |
|   10.0.0.5             |
| inventory_collector.php|
+-----------+------------+
            |
            v
+------------------------+
|      FRONTEND          |
| index.php + CSS + RAW  |
+------------------------+
```

---

## 3. Estrutura de Diretórios

### 🔹 Agente (em cada servidor)

```
/opt/inventory/agent/
├── Dockerfile
├── docker-compose.yml
├── agent.sh
├── api/
│   └── inventory_agent.php
└── scripts/
    └── inventory.sh
```

---

### 🔹 Coletor Central (10.0.0.5)

```
/var/www/html/api/inventory_central/
└── inventory_collector.php
```

---

### 🔹 Frontend

```
/var/www/html/inventory/
├── index.php
├── css/
│   └── style.css
└── img/
    └── logo_inventory.jpg
```

---

## 4. Fluxo de Funcionamento

1. O **collector central** consulta os servidores:

```
http://IP/api/inventory/inventory_agent.php
```

2. O agente:

* valida token (Authorization Bearer)
* executa `inventory.sh`
* retorna JSON estruturado

3. O collector:

* mede tempo de resposta
* valida HTTP (200)
* valida JSON
* classifica status:

  * online
  * offline
  * http_error
  * error_json

4. O frontend:

* consome collector
* exibe tabela
* abre modal com RAW detalhado

---

## 5. Container do Agente

### 🔹 docker-compose.yml (padrão)

```yaml
version: '3.8'

services:
  inventory-agent:
    build: .
    container_name: inventory-agent

    ports:
      - "8090:80"

    restart: always

    volumes:
      - /proc:/host/proc:ro
      - /sys:/host/sys:ro
      - /etc/os-release:/host/os-release:ro
      - /var/run/docker.sock:/var/run/docker.sock
      - ./scripts:/scripts:ro

    environment:
      - HOST_PROC=/host/proc
      - HOST_SYS=/host/sys

    read_only: true

    tmpfs:
      - /tmp
      - /var/run
      - /var/run/apache2
      - /var/lock

    security_opt:
      - no-new-privileges:true

    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/api/inventory_agent.php"]
      interval: 30s
      timeout: 5s
      retries: 3
```

---

## 6. Script de Controle do Container

Arquivo: `agent.sh`

Compatível com:

* `docker compose`
* `docker-compose` (Ubuntu 18.04)

### Uso:

```bash
./agent.sh start
./agent.sh stop
./agent.sh restart
./agent.sh status
./agent.sh rebuild
```

O script detecta automaticamente qual versão do Docker usar.

---

## 7. Implantação de Novo Servidor

### 🔹 Passo 1 – Criar diretório

```bash
sudo mkdir -p /opt/inventory
sudo chown -R epaminondas:epaminondas /opt/inventory
```

---

### 🔹 Passo 2 – Copiar agente

```bash
rsync -avz /opt/inventory/agent/ epaminondas@IP:/opt/inventory/agent/
```

---

### 🔹 Passo 3 – Subir container

```bash
cd /opt/inventory/agent
./agent.sh start
```

---

### 🔹 Passo 4 – Testar

```bash
curl http://IP:8090/api/inventory_agent.php
```

---

## 8. Padrão de API

### Endpoint do agente:

```
/api/inventory/inventory_agent.php
```

### Header obrigatório:

```
Authorization: Bearer sentinela_token_123
```

---

## 9. Correções Implementadas (Versão Atual)

### ✔ Correção crítica de tipagem (collector)

```php
if ((int)$httpCode !== 200)
```

Antes:

* retornava erro mesmo com HTTP 200

---

### ✔ Padronização de endpoint

```
/api/inventory/inventory_agent.php
```

---

### ✔ Tratamento de erros

* offline (sem resposta)
* http_error
* error_json

---

### ✔ Segurança

* uso de token Bearer
* container read-only
* isolamento via Docker

---

### ✔ Healthcheck funcional

* valida endpoint interno
* garante estado `healthy`

---

### ✔ Compatibilidade com sistemas legados

* suporte a Ubuntu 18.04
* fallback para `docker-compose`

---

### ✔ Frontend melhorado

* modal RAW estruturado
* separação por blocos:

  * SYSTEM
  * RAW
  * AGENT

---

## 10. Troubleshooting

### 🔴 Status "http_error" com 200

Causa:

* comparação incorreta de tipo

Solução:

```php
(int)$httpCode
```

---

### 🔴 Página não carrega

Testar:

```bash
curl http://localhost/api/inventory_central/inventory_collector.php
```

---

### 🔴 JSON inválido

```bash
php inventory_collector.php | jq .
```

---

### 🔴 Problemas com Docker

Verificar:

```bash
docker ps
docker-compose version
```

---

### 🔴 Problemas com rsync

Erro de permissão:

```bash
sudo chown -R epaminondas:epaminondas /opt/inventory
```

---

