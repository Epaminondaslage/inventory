# Sentinela - Implantação do Coletor Central de Inventário

**Data:** 2026-02-25 12:38:33

------------------------------------------------------------------------

## 1. Objetivo

Este documento descreve o processo de implantação do **Coletor Central
de Inventário** no servidor:

**10.0.0.5**

O coletor é responsável por:

-   Consultar todos os agentes de inventário distribuídos
-   Consolidar os dados recebidos
-   Detectar servidores online/offline
-   Medir tempo de resposta
-   Retornar um JSON unificado
-   Servindo como camada intermediária entre os agentes e o painel web

------------------------------------------------------------------------

## 2. Arquitetura

               (Agentes)
     10.0.0.141  ─┐
     10.0.0.139  ─┼───> 10.0.0.5 (Coletor Central)
     10.0.0.37   ─┘

Cada agente expõe:

    /api/inventory/inventory_agent.php

O coletor central expõe:

    /api/inventory_central/inventory_collector.php

------------------------------------------------------------------------

## 3. Criar Estrutura de Diretórios no 10.0.0.5

``` bash
sudo mkdir -p /var/www/html/api/inventory_central
```

------------------------------------------------------------------------

## 4. Ajustar Permissões

Definir dono do diretório:

``` bash
sudo chown -R www-data:www-data /var/www/html/api
```

Permissões recomendadas:

``` bash
sudo chmod 750 /var/www/html/api
sudo chmod 750 /var/www/html/api/inventory_central
```

------------------------------------------------------------------------

## 5. Criar o Arquivo inventory_collector.php

Criar o arquivo:

``` bash
sudo nano /var/www/html/api/inventory_central/inventory_collector.php
```

Após salvar o conteúdo do coletor:

``` bash
sudo chown www-data:www-data /var/www/html/api/inventory_central/inventory_collector.php
sudo chmod 640 /var/www/html/api/inventory_central/inventory_collector.php
```

------------------------------------------------------------------------

## 6. Estrutura Final Esperada

Verificar:

``` bash
tree /var/www/html/api
```

Resultado esperado:

    /var/www/html/api
    └── inventory_central
        └── inventory_collector.php

------------------------------------------------------------------------

## 7. Teste Local no 10.0.0.5

``` bash
curl http://localhost/api/inventory_central/inventory_collector.php
```

------------------------------------------------------------------------

## 8. Teste Remoto

``` bash
curl http://10.0.0.5/api/inventory_central/inventory_collector.php
```

------------------------------------------------------------------------

## 9. Resultado Esperado

O retorno será um JSON consolidado:

``` json
{
  "collector": "10.0.0.5",
  "timestamp": "...",
  "servers": [
    {
      "ip": "10.0.0.141",
      "status": "online",
      "response_ms": 120,
      "data": { ... }
    },
    {
      "ip": "10.0.0.139",
      "status": "offline"
    }
  ]
}
```

------------------------------------------------------------------------

## 10. Validação

O coletor está corretamente implantado quando:

-   Consegue consultar todos os agentes
-   Identifica corretamente servidores offline
-   Retorna JSON válido
-   Não apresenta erro de timeout



------------------------------------------------------------------------

**Módulo integrante da arquitetura Sentinela - Monitoramento de
Infraestrutura**
