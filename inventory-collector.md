# README — Sentinela Inventory Collector

## Visão Geral

O `inventory_collector.php` é o componente responsável por:

* Coletar informações de múltiplos servidores via API
* Normalizar os dados recebidos
* Persistir logs detalhados (raw + estruturado)
* Persistir métricas otimizadas para análise (Grafana)
* Controlar status (online/offline)
* Garantir consistência temporal (granularidade por minuto)

O sistema foi projetado para observabilidade contínua de infraestrutura distribuída.

---

# Arquitetura Completa do Ambiente

## Topologia Real

```text
[ SERVIDORES MONITORADOS ]
        ↓ (HTTP API JSON)
[ inventory_collector.php ]
        ↓
[ MySQL (container docker) ]
        ↓
 ├── Frontend PHP (Sentinela)
 └── Grafana (visualização)
```

---

## Servidores Envolvidos

### 1. Servidor de Coleta (CENTRAL)- 10.0.0.139

* Executa:

  * `inventory_collector.php`
  * Frontend PHP (`index.php`)
* Caminho:

```text
/var/www/html/inventory/api/inventory_central/
```

* Responsável por:

  * Orquestrar coleta
  * Persistir dados
  * Expor visualização web

---

### 2. Servidores Monitorados (AGENTES)

Exemplos reais:

* 10.0.0.141 → MQTT
* 10.0.0.37 → Ubuntu Desktop
* 10.0.0.139 → Home Assistant
* 10.0.0.5 → Web Server
* 10.0.2.148 → IA / processamento

Cada servidor:

* expõe API HTTP
* retorna JSON estruturado
* responde com métricas locais

---

### 3. Servidor de Banco de Dados

Container:

```text
mysql-mqtt
```

Porta:

```text
10.0.0.139:3306
```

Banco:

```text
sentinela
```

Usuário:

```text
inventory
```

---

### 4. Servidor Grafana

Container:

```text
grafana
```

Acesso:

```text
http://10.0.0.148:3000
```

Função:

* visualização
* dashboards
* análise temporal

---

# Containers Docker Envolvidos

Resumo do ambiente:

```text
mysql-mqtt           → banco de dados
grafana              → dashboards
inventory-agent      → agentes (opcional)
sentinela-core       → backend legado
sentinela-dashboard  → frontend antigo
```

---

# Fluxo de Execução

```php
foreach ($SERVERS as $serverIp) {
    $res = consultarServidor($serverIp, $TOKEN, $TIMEOUT);
    $resultado[] = $res;

    salvarMetricas($pdo, $res);

    if (salvarNoBanco($pdo, $res)) {
        $db_saved_count++;
    }
}
```

---

## Pipeline de Dados

```text
API → RAW JSON → PARSE → NORMALIZAÇÃO → BANCO
```

---

# Estrutura dos Dados (RAW)

Exemplo:

```json
{
  "hostname": "server01",
  "system": {
    "cpu_cores": 4,
    "uptime": 123456
  },
  "memory": {
    "total_kb": 8192000
  }
}
```

---

# Banco de Dados

## Localização

* Host: `10.0.0.139`
* Porta: `3306`
* Container: `mysql-mqtt`

---

# Tabela: server_inventory_logs

## Criação completa

```sql
CREATE TABLE server_inventory_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    collected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    time_bucket DATETIME,

    server_ip VARCHAR(45),
    hostname VARCHAR(255),
    api_version VARCHAR(50),
    response_ms INT,
    status VARCHAR(20),

    host_ip VARCHAR(45),

    agent_hostname VARCHAR(255),
    agent_version VARCHAR(50),
    agent_api_version VARCHAR(50),
    agent_execution_ms INT,

    cpu_cores INT,
    mem_total_kb BIGINT,
    uptime_seconds BIGINT,

    mem_total_str VARCHAR(50),
    mem_used_str VARCHAR(50),
    mem_free_str VARCHAR(50),

    network_interface VARCHAR(50),
    network_status VARCHAR(20),
    network_ip VARCHAR(45),

    raw_payload LONGTEXT,
    raw_original LONGTEXT,
    raw_parsed LONGTEXT,

    error_message TEXT
);
```

---

## Índice crítico

```sql
ALTER TABLE server_inventory_logs
ADD UNIQUE KEY uniq_server_minute (server_ip, time_bucket);
```

---

## Finalidade

* Auditoria
* Debug
* Histórico completo
* Reprocessamento

---

# Tabela: server_metrics_full

## Criação completa

```sql
CREATE TABLE server_metrics_full (
    id INT AUTO_INCREMENT PRIMARY KEY,

    time DATETIME,
    server_ip VARCHAR(45),
    status VARCHAR(20),

    hostname VARCHAR(255),

    cpu_cores INT,

    mem_total_kb BIGINT,
    mem_used_kb BIGINT,
    mem_free_kb BIGINT,
    mem_used_percent FLOAT,

    uptime_seconds BIGINT,

    response_ms INT,
    api_version VARCHAR(50),

    disks_json JSON,
    network_json JSON,
    raw_json JSON
);
```

---

## Índices

```sql
CREATE INDEX idx_time ON server_metrics_full(time);
CREATE INDEX idx_server_time ON server_metrics_full(server_ip, time);
CREATE INDEX idx_status ON server_metrics_full(status);
```

---

## Finalidade

* Grafana
* Queries rápidas
* Séries temporais

---

# Frontend (index.php)

## Localização

```text
/var/www/html/inventory/index.php
```

---

## Função

* Exibir status dos servidores
* Mostrar última atualização
* Apresentar tabela com:

```text
IP | STATUS | HOSTNAME | CPU | MEMÓRIA | API | TEMPO
```

---

## Origem dos dados

Consulta diretamente:

```text
server_inventory_logs
```

ou

```text
server_metrics_full
```

---

## Campo de última atualização

Obtido via:

```sql
SELECT MAX(time) FROM server_metrics_full;
```

Formatado em:

```text
dd-mm-aaaa hh:mm:ss
```

---

# Função: salvarMetricas()

Responsável por alimentar Grafana.

```php
$stmt->execute([
    ':time' => date('Y-m-d H:i:s'),
    ':server_ip' => $server["ip"],
    ...
]);
```

---

# Função: salvarNoBanco()

Responsável por log detalhado.

---

## Modo ONLINE

* grava tudo

## Modo OFFLINE

```php
status = 'offline'
error_message = 'timeout'
```

---

# Controle Temporal

## time_bucket

```php
date('Y-m-d H:i:00')
```

---

## time (métricas)

```php
date('Y-m-d H:i:s')
```

---

# Cron

```bash
*/3 * * * * /usr/bin/php /var/www/html/inventory/api/inventory_central/inventory_collector.php >> /tmp/sentinela.log 2>&1
```

---

# Logs

Arquivo:

```text
/tmp/sentinela.log
```

---

# Integração com Grafana

## Datasource

```text
mysql-sentinela
```

---

## Query padrão

```sql
SELECT
  time AS time,
  mem_used_percent AS value,
  server_ip
FROM server_metrics_full
WHERE $__timeFilter(time)
```

---

# Problemas Comuns

## 1. Banco não conecta

```sql
GRANT ALL ON sentinela.* TO 'inventory'@'%';
FLUSH PRIVILEGES;
```

---

## 2. Cron não roda

Verificar:

```bash
tail -f /tmp/sentinela.log
```

---

## 3. Sem dados no Grafana

* coluna `time`
* índices
* datasource

---

# Boas Práticas

* separação log x métrica
* uso de JSON para flexibilidade
* índices compostos
* granularidade por minuto
* prepared statements

---

# Evoluções Futuras

* alertas automáticos
* parsing de disco
* retenção de dados
* clusterização

---

# Conclusão

O sistema está dividido em:

### 1. Coleta

* inventory_collector.php

### 2. Armazenamento

* MySQL container

### 3. Visualização

* Frontend PHP
* Grafana

---

Se quiser, próximo passo lógico:

👉 gerar um **diagrama técnico (tipo arquitetura corporativa)**
👉 ou um **dashboard Grafana nível NOC completo (produção)**
