# Sentinela - Módulo de Inventário de Infraestrutura

**Data de geração:** 2026-02-25 11:53:20

------------------------------------------------------------------------

## 1. Objetivo

Este documento descreve a implementação do módulo de inventário de
hardware da infraestrutura do projeto Sentinela.

O módulo é composto por:

-   `inventory.sh` → Script shell que coleta informações estruturadas do
    sistema
-   `inventory_agent.php` → API local que expõe o inventário em formato
    JSON via HTTP

Foi criado para gerar um relatório completo do ambiente de hardware e sistema operacional de cada servidor da infraestrutura do projeto.

Ele permite:

- Mapear capacidades físicas
- Documentar arquitetura
- Identificar recursos disponíveis (CPU, RAM, GPU, disco)
- Detectar virtualização
- Registrar versão do sistema
- Padronizar auditoria da infraestrutura
------------------------------------------------------------------------
## 2.  Informações Coletadas

O script sh e o php geram um JSON contendo um relatório estruturado com:

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

--------------


## 3. Estrutura Padronizada de Diretórios

Recomenda-se utilizar a seguinte estrutura:

    /var/www/html/api/inventory/
        ├── inventory.sh
        ├── inventory_agent.php

Essa organização permite expansão futura para:

    /api/sentinela/
    /api/decision/
    /api/mqtt/

------------------------------------------------------------------------

## 4. inventory.sh

### Função

Script responsável por gerar um JSON estruturado contendo:

-   Hostname
-   Timestamp
-   Kernel
-   Arquitetura
-   Sistema operacional
-   CPU (modelo, núcleos, frequência)
-   Memória total e disponível
-   Disco raiz
-   GPU (detecção NVIDIA)
-   Docker (instalação e versão)
-   IP principal
-   Virtualização

### Permissões

Após criar o arquivo:

``` bash
chmod +x /var/www/html/api/inventory/inventory.sh
```

------------------------------------------------------------------------

## 5. inventory_agent.php

### Função

Expor o inventário via endpoint HTTP protegido.

### Endpoint

    http://IP_DO_SERVIDOR/api/inventory/inventory_agent.php

### Autenticação

Recomenda-se utilizar header Authorization:

    Authorization: Bearer SEU_TOKEN

Exemplo:

``` bash
curl -H "Authorization: Bearer sentinela_token_123" http://10.0.0.141/api/inventory/inventory_agent.php
```

Fallback via GET (opcional):

``` bash
curl "http://10.0.0.141/api/inventory/inventory_agent.php?token=sentinela_token_123"
```

------------------------------------------------------------------------
## 6. Teste do Script Local

Executar diretamente:

``` bash
cd /var/www/html/api/inventory
./inventory.sh
```

O retorno deve ser um JSON iniciando com:

    {
      "hostname": "..."

------------------------------------------------------------------------

## 7. Teste do Endpoint HTTP Local

No próprio servidor:

``` bash
curl -H "Authorization: Bearer sentinela_token_123" http://localhost/api/inventory/inventory_agent.php
```

Se estiver correto, o retorno será o mesmo JSON do script.

------------------------------------------------------------------------

## 8. Teste Remoto

De outro servidor da rede:

``` bash
curl -H "Authorization: Bearer sentinela_token_123" http://IP_DO_SERVIDOR/api/inventory/inventory_agent.php
```

------------------------------------------------------------------------

## 9. Validação de Funcionamento

O agente está corretamente implantado se:

-   O script executa sem erro
-   O endpoint retorna JSON válido
-   O token é validado corretamente
-   O acesso externo não autorizado é bloqueado

------------------------------------------------------------------------

## 10. Resultado

Após esta etapa, o servidor passa a atuar como:

Agente de Inventário Sentinela

Pronto para ser consultado pelo coletor central.

------------------------------------------------------------------------

## 11. Segurança

O agente possui:

-   Validação de token
-   Restrição opcional por IP interno
-   Execução via caminho absoluto
-   Validação de JSON antes de resposta

Recomenda-se:

-   Alterar o token padrão
-   Permitir acesso apenas pela rede interna
-   Não expor para internet pública

------------------------------------------------------------------------

## 12. Permissões Recomendadas

sudo chown www-data:www-data /var/www/html/api/inventory -R
sudo chmod 750 /var/www/html/api/inventory

------------------------------------------------------------------------

## 13. Fluxo de Funcionamento

1.  Servidor central envia requisição HTTP ao agente.
2.  Agente valida token e IP.
3.  Executa `inventory.sh`.
4.  Retorna JSON estruturado.
5.  Servidor central armazena e consolida dados.

------------------------------------------------------------------------

