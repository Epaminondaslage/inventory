# 📄 inventory.sh --- Script de Inventário de Hardware

**Gerado em:** 2026-02-25 11:41:21

------------------------------------------------------------------------

## 📌 Objetivo

O `inventory.sh` é um script em Shell criado para gerar um relatório
completo do ambiente de hardware e sistema operacional de cada servidor
da infraestrutura do projeto.

Ele permite:

-   Mapear capacidades físicas\
-   Documentar arquitetura\
-   Identificar recursos disponíveis (CPU, RAM, GPU, disco)\
-   Detectar virtualização\
-   Registrar versão do sistema\
-   Padronizar auditoria da infraestrutura

------------------------------------------------------------------------

## 🧠 Finalidade no Projeto

No contexto do projeto **Sentinela**, o script é utilizado para:

-   Classificar servidores por função (Core, IA, Transporte, Dados)\
-   Avaliar viabilidade de execução de modelos de IA\
-   Documentar capacidade de processamento\
-   Apoiar decisões arquiteturais\
-   Criar histórico técnico da infraestrutura

------------------------------------------------------------------------

## ⚙️ Informações Coletadas

O script gera um relatório estruturado contendo:

### 🔹 Identificação do Sistema

-   Hostname\
-   Data e hora\
-   Kernel\
-   Arquitetura\
-   Distribuição Linux

### 🔹 CPU

-   Modelo\
-   Arquitetura\
-   Número de núcleos\
-   Frequência\
-   Cache

### 🔹 Memória

-   RAM total\
-   RAM disponível\
-   Swap

### 🔹 Armazenamento

-   Discos físicos\
-   Partições\
-   Pontos de montagem

### 🔹 Dispositivos PCI

-   Controladores\
-   Placas de rede\
-   GPU (se houver)

### 🔹 GPU

-   Dispositivos NVIDIA\
-   Execução de `nvidia-smi` (se disponível)

### 🔹 Rede

-   Interfaces\
-   Endereços IP

### 🔹 Virtualização

-   Detecta se é VM ou bare metal

### 🔹 Docker

-   Versão instalada\
-   Storage driver\
-   Cgroup driver

------------------------------------------------------------------------

## 📂 Saída

O script gera automaticamente um arquivo de log no formato:

    hw_inventory_<hostname>_<timestamp>.log

Exemplo:

    hw_inventory_mqtt_2026-02-25_08-35-48.log

Isso permite:

-   Organização por máquina\
-   Versionamento histórico\
-   Comparação entre servidores

------------------------------------------------------------------------

## ▶️ Execução

``` bash
chmod +x inventory.sh
./inventory.sh
```

------------------------------------------------------------------------

## 🛠 Requisitos

-   Sistema Linux\
-   Utilitários padrão (`lscpu`, `lsblk`, `lspci`, `free`, `ip`)\
-   Permissões normais de usuário (não requer root)

------------------------------------------------------------------------

## 🎯 Benefícios Técnicos

-   Padroniza documentação da infraestrutura\
-   Facilita troubleshooting\
-   Apoia decisões de implantação de IA\
-   Garante rastreabilidade técnica\
-   Permite auditoria de capacidade

------------------------------------------------------------------------

## 🔒 Observação de Segurança

O script não coleta:

-   Senhas\
-   Tokens\
-   Conteúdo de arquivos\
-   Configurações sensíveis

Ele registra apenas metadados estruturais do sistema.
