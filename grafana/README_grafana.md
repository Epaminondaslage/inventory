# Sentinela — Dashboard Grafana de Inventário

> Painel analítico de inventário de servidores integrado ao MySQL `inventory_db` via Grafana 13.

**URL:** `http://10.0.2.148:3000/d/inventory-sentinela`
**Login:** `admin` / `Ep@m1n0nd@s`
**Datasource:** MySQL — `inventory_db` em 10.0.0.139:3306
**Arquivo:** `grafana/grafana_dashboard_v4.json`

---

## 1. Pré-requisitos

- Grafana rodando em container Docker no servidor **10.0.2.148:3000**
- MySQL `inventory_db` acessível em **10.0.0.139:3306**
- Persistidor `mqtt_inventory_persist.py` rodando como systemd no **10.0.0.139**
- Dados populados via agentes Docker nos 5 servidores

---

## 2. Instalação do Grafana

```bash
docker run -d \
  --name grafana \
  --restart always \
  -p 3000:3000 \
  -v /opt/grafana/data:/var/lib/grafana \
  -e GF_SECURITY_ADMIN_PASSWORD=Ep@m1n0nd@s \
  -e GF_SECURITY_ADMIN_USER=admin \
  grafana/grafana:latest
```

Aguarda subir e testa:

```bash
sleep 10
curl -s http://localhost:3000/api/health
```

---

## 3. Configuração do Datasource MySQL

```bash
GPASS='Ep@m1n0nd@s'

curl -s -u "admin:${GPASS}" \
  -H "Content-Type: application/json" \
  -X POST http://localhost:3000/api/datasources \
  -d '{
    "name": "inventory_db",
    "type": "mysql",
    "url": "10.0.0.139:3306",
    "database": "inventory_db",
    "user": "root",
    "secureJsonData": { "password": "Ep@m1n0nd@s" },
    "access": "proxy",
    "isDefault": true
  }' | python3 -m json.tool
```

Verifica a conexão:

```bash
# Pega o UID do datasource criado
UID=$(curl -s -u "admin:${GPASS}" http://localhost:3000/api/datasources | python3 -c "import sys,json; print(json.load(sys.stdin)[0]['uid'])")

curl -s -u "admin:${GPASS}" \
  -X GET "http://localhost:3000/api/datasources/uid/${UID}/health" | python3 -m json.tool
# Esperado: {"status": "OK", "message": "Database Connection OK"}
```

---

## 4. Importação do Dashboard

```bash
# Copia o JSON para o servidor
scp grafana/grafana_dashboard_v4.json epaminondas@10.0.2.148:/tmp/

# Importa via API
GPASS='Ep@m1n0nd@s'
curl -s -u "admin:${GPASS}" \
  -H "Content-Type: application/json" \
  -X POST http://localhost:3000/api/dashboards/db \
  -d @/tmp/grafana_dashboard_v4.json | python3 -m json.tool
```

Acessa em:
```
http://10.0.2.148:3000/d/inventory-sentinela
```

---

## 5. Estrutura do Dashboard

O dashboard usa **rows colapsáveis** por tema:

### 🖥 Visão Geral *(aberto por padrão)*
- **Servidores Online** — contagem de hostnames com snapshot < 15 min
- **Total Containers** — soma de containers Docker ativos
- **Total Snapshots** — total histórico de coletas no banco
- **Último Snapshot** — timestamp da coleta mais recente
- **Coletas Última Hora** — frequência de coleta
- **Tabela de Servidores** — hostname, IP, OS, arch, CPU, memória com % colorida, uptime, agente, horário do snapshot

### 💾 Memória *(aberto por padrão)*
- **5 gauges individuais** — um por servidor, % de uso com threshold verde/amarelo/vermelho
- **Histórico de Memória %** — timeseries com linha por servidor, legenda com Last/Mean/Max

### ⏱ Performance *(colapsado)*
- **Uptime por Servidor** — em horas ao longo do tempo
- **Tempo de Execução do Agente** — latência de coleta em ms (pontos)

### 🐳 Docker *(colapsado)*
- **Containers por Servidor** — tabela hostname + container + porta, com total no rodapé
- **Donut de Distribuição** — pizza com proporção de containers por servidor e legenda com nomes

### 🔌 Portas Abertas *(colapsado)*
- **Tabela completa** — hostname, porta, bind, tipo (Docker=azul / Host=verde)

---

## 6. Banco de Dados Consultado

**Banco:** `inventory_db` em `10.0.0.139:3306`

### Tabelas utilizadas

#### inventory_snapshots
Snapshot de hardware coletado a cada publicação MQTT.

```sql
-- Últimos snapshots por servidor
SELECT hostname, ip, os_name, arch, cpu_cores,
       mem_used, mem_total, uptime_sec, exec_ms, collected_at
FROM inventory_snapshots
WHERE id IN (SELECT MAX(id) FROM inventory_snapshots GROUP BY hostname)
ORDER BY hostname;
```

#### server_services
Portas abertas e containers Docker de cada snapshot.

```sql
-- Containers ativos por servidor
SELECT s.hostname, ss.container, ss.port
FROM server_services ss
JOIN inventory_snapshots s ON ss.snapshot_id = s.id
WHERE s.id IN (SELECT MAX(id) FROM inventory_snapshots GROUP BY hostname)
  AND ss.scope = 'docker'
ORDER BY s.hostname, ss.port;

-- Portas host por servidor
SELECT s.hostname, ss.port, ss.bind_addr, ss.scope
FROM server_services ss
JOIN inventory_snapshots s ON ss.snapshot_id = s.id
WHERE s.id IN (SELECT MAX(id) FROM inventory_snapshots GROUP BY hostname)
  AND ss.scope = 'host' AND ss.port <= 32767
ORDER BY s.hostname, ss.port;
```

### Conversão de memória nas queries

Os valores de memória são armazenados como strings (`6.0Gi`, `15Gi`). As queries convertem para cálculo de percentual:

```sql
ROUND(
  CAST(REPLACE(REPLACE(mem_used,'Gi',''),'Mi','') AS DECIMAL(10,2)) /
  CAST(REPLACE(REPLACE(mem_total,'Gi',''),'Mi','') AS DECIMAL(10,2)) * 100
, 1) as mem_pct
```

---

## 7. Atualização do Dashboard

Para atualizar o dashboard após mudanças no JSON:

```bash
GPASS='Ep@m1n0nd@s'
curl -s -u "admin:${GPASS}" \
  -H "Content-Type: application/json" \
  -X POST http://localhost:3000/api/dashboards/db \
  -d @/tmp/grafana_dashboard_v4.json | python3 -m json.tool
```

O campo `"overwrite": true` no JSON garante que substitui o dashboard existente sem criar duplicata.

---

## 8. Troubleshooting

### Datasource não conecta
```bash
# Verifica conectividade com o MySQL
mysql -u root -pEp@m1n0nd@s -h 10.0.0.139 -P 3306 inventory_db \
  -e "SELECT COUNT(*) FROM inventory_snapshots;"
```

### Dashboard sem dados
```bash
# Verifica se há snapshots recentes
mysql -u root -pEp@m1n0nd@s -h 127.0.0.1 -P 3306 inventory_db \
  -e "SELECT hostname, collected_at FROM inventory_snapshots ORDER BY id DESC LIMIT 5;"

# Dispara coleta manual em todos os servidores
for IP in 10.0.0.5 10.0.0.139 10.0.0.141 10.0.2.148 10.0.0.37; do
  curl -s -H "Authorization: Bearer sentinela_token_123" \
    http://$IP:8090/api/inventory_agent.php > /dev/null && echo "$IP OK"
done
```

### Containers não aparecem no painel Docker
```bash
# Verifica se server_services tem registros docker
mysql -u root -pEp@m1n0nd@s -h 127.0.0.1 -P 3306 inventory_db \
  -e "SELECT scope, COUNT(*) FROM server_services GROUP BY scope;"

# Se docker=0, reinicia o persistidor e recoleta
sudo systemctl restart mqtt-inventory-persist
```

### Senha do Grafana perdida
```bash
docker exec -it grafana grafana cli admin reset-admin-password NovaSenha123
```

---

## 9. Servidores Monitorados

| Hostname | IP | OS | Arch | Docker GID |
|---|---|---|---|---|
| homeassistant | 10.0.0.139 | Ubuntu 22.04 | aarch64 | 143 |
| ia | 10.0.2.148 | Ubuntu 24.04 | x86_64 | 988 |
| www | 10.0.0.5 | Ubuntu 18.04 | aarch64 | 127 |
| mqtt | 10.0.0.141 | Ubuntu 18.04 | aarch64 | 127 |
| Ubuntu-desktop | 10.0.0.37 | Ubuntu 24.04 | x86_64 | 140 |
