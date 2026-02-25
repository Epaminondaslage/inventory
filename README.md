# Sentinela - Módulo de Inventário de Infraestrutura

**Data de geração:** 2026-02-25 11:53:20

------------------------------------------------------------------------

## 1. Objetivo


Este documento tem como objetivo descrever a arquitetura, organização, fluxo de funcionamento e padrões de implantação do módulo Inventário de Servidores, integrante da arquitetura Sentinela – Monitoramento de Infraestrutura.

O módulo foi projetado para:

- Padronizar a coleta de informações de hardware e sistema operacional em múltiplos servidores.
- Disponibilizar os dados via API segura (agente local).
- Consolidar as informações em um servidor central.
- Apresentar os dados de forma visual, organizada e responsiva através de um painel web.
- Permitir expansão futura dentro do ecossistema Sentinela.
- Garantir segurança, isolamento por rede interna e controle de acesso via token.

O Inventário de Servidores estabelece a base estrutural para monitoramento contínuo da infraestrutura, servindo como camada inicial para futuras integrações com:

- módulos de decisão automatizada, integração MQTT e dashboards executivos.

Este README documenta a estrutura recomendada, o fluxo operacional e a arquitetura técnica necessária para implantação, manutenção e expansão do módulo.

------------------------------------------------------------------------

## 2. Índice de documentação

-  [Módulo de Inventário de Infraestrutura](README_inventory_api.md)
-  [Implantação do Coletor Central de Inventário](README_implantacao_coletor_inventory.md)
-  [Painel Inventário de Servidores](Painel_inventario_servidores.md)

------------------------------------------------------------------------


## 3. Estrutura Padronizada de Diretórios

Recomenda-se utilizar a seguinte estrutura no servidor agente
(monitorado):

    /var/www/html/api/inventory/
        ├── inventory.sh
        ├── inventory_agent.php

Servidor coletor central (ex: 10.0.0.5):

    /var/www/html/api/inventory_central/
        ├── inventory_collector.php

Painel web (frontend):

    /var/www/html/inventory/
        ├── index.php
        ├── css/
        │   └── style.css
        └── img/
            └── logo_inventory.jpg

Essa organização permite expansão futura:

    /api/sentinela/
    /api/decision/
    /api/mqtt/
    /inventory/dashboard/
    /inventory/history/



------------------------------------------------------------------------

## 4. Fluxo de Funcionamento

<img src="img/fluxo.jpg" alt="fluxo" >

1.  Servidor central consulta agente.
2.  Agente valida token.
3.  inventory.sh é executado.
4.  JSON é retornado.
5.  Coletor consolida dados.
6.  index.php apresenta visualmente.

------------------------------------------------------------------------

## 5. Arquitetura

    AGENTES (141, 139, etc.)
            ↓
    inventory_agent.php
            ↓
    COLETOR CENTRAL (10.0.0.5)
            ↓
    inventory_collector.php
            ↓
    PAINEL WEB
            ↓
    index.php + CSS + Modal RAW

------------------------------------------------------------------------


**Módulo integrante da arquitetura Sentinela - Monitoramento de Infraestrutura**
