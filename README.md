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

Esse mecanismo permite:

-   Coleta remota de informações
-   Consolidação centralizada
-   Auditoria da infraestrutura
-   Classificação automática de servidores

------------------------------------------------------------------------

## 2. Estrutura Padronizada de Diretórios

Recomenda-se utilizar a seguinte estrutura:

    /var/www/html/api/inventory/
        ├── inventory.sh
        ├── inventory_agent.php

Essa organização permite expansão futura para:

    /api/sentinela/
    /api/decision/
    /api/mqtt/

------------------------------------------------------------------------

## 3. inventory.sh

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

## 4. inventory_agent.php

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

## 5. Segurança

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

## 6. Permissões Recomendadas

    sudo chown www-data:www-data /var/www/html/api/inventory -R
    sudo chmod 750 /var/www/html/api/inventory

------------------------------------------------------------------------

## 7. Fluxo de Funcionamento

1.  Servidor central envia requisição HTTP ao agente.
2.  Agente valida token e IP.
3.  Executa `inventory.sh`.
4.  Retorna JSON estruturado.
5.  Servidor central armazena e consolida dados.

------------------------------------------------------------------------

## 8. Benefícios Arquiteturais

-   Padronização da documentação de hardware
-   Base para painel consolidado
-   Histórico de mudanças de infraestrutura
-   Classificação automática de servidores
-   Integração futura com núcleo do Sentinela

------------------------------------------------------------------------

## 9. Próximos Passos Recomendados

-   Implementar coletor central com banco de dados
-   Criar painel web consolidado
-   Implementar versionamento histórico
-   Implementar assinatura HMAC para autenticação avançada

------------------------------------------------------------------------

**Módulo integrante da arquitetura Sentinela - Monitoramento de
Infraestrutura**
