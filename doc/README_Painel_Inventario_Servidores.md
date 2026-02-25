# README - Painel Inventário de Servidores

**Data:** 2026-02-25 15:01:20

------------------------------------------------------------------------

## 1. Visão Geral

Este documento descreve o funcionamento do:

-   `index.php`
-   `css/style.css`

Do módulo **Inventário de Servidores**.

O painel consome os dados do coletor central:

    /api/inventory_central/inventory_collector.php

    Servidor central padrão: 10.0.0.5)

E apresenta:

-   Lista consolidada de servidores
-   Status Online / Offline com badge visual
-   Tempo de resposta
-   Modal com RAW completo formatado
-   Layout responsivo
-   Interface limpa (fundo branco, texto preto)

------------------------------------------------------------------------

## 2. Estrutura de Diretórios

    /var/www/html/inventory/
    │
    ├── index.php
    ├── css/
    │   └── style.css
    └── img/
        └── logo_inventory.jpg

------------------------------------------------------------------------

## 3. index.php

### 3.1 Função Principal

-   Consulta o coletor central via `file_get_contents`
-   Decodifica o JSON
-   Monta tabela consolidada
-   Conta servidores offline
-   Gera modal com RAW completo

### 3.2 Colunas da Tabela

  Coluna       Descrição
  ------------ ---------------------------------
  IP           Endereço do servidor
  Status       Badge visual (ONLINE / OFFLINE) com indicador gráfico
  Hostname     Nome do host
  Tempo (ms)   Tempo de resposta do agente
  Detalhes     Botão para abrir RAW completo

### 3.3 Badge de Status

Implementado com:

``` html
<span class="badge badge-online">
    <span class="dot dot-online"></span>
    ONLINE
</span>
```

-   Verde para ONLINE
-   Vermelho para OFFLINE
-   Bolinha indicadora

### 3.4 Modal

O modal:

-   Abre ao clicar em "Ver RAW"
- Mostra todos os blocos _raw
- Preserva quebras de linha
- Fecha com botão X
- Fecha clicando fora
- Largura padrão de 60% da tela

------------------------------------------------------------------------

## 4. CSS (style.css)

### 4.1 Organização

O CSS está dividido em:

-   Reset básico
-   Container
-   Header centralizado
-   Tabela (70% da largura da página)
-   Badges
-   Modal (60% da largura da página)
-   Responsividade

### 4.2 Header

Centralizado verticalmente:

-   Logo (`img/logo_inventory.jpg`)
-   Título: Inventário de Servidores
-   Botão Voltar

### 4.3 Tabela

-   Largura: 70% da página
-   Centralizada
-   Cabeçalho preto
-   Linhas com cores diferenciadas
-   Colunas Status, Hostname, Tempo e Detalhes centralizadas

### 4.4 Badges

``` css
.badge-online { background-color: #28a745; }
.badge-offline { background-color: #dc3545; }
```

### 4.5 Modal

-   Largura: 60%
-   Centralizado
-   Scroll interno
-   Overlay escuro
-   Responsivo (90% em telas menores)

------------------------------------------------------------------------

## 5. Responsividade

-   Tabela ajusta para telas menores
-   Modal reduz para 90% em dispositivos móveis
-   Fonte reduz automaticamente

------------------------------------------------------------------------

## 6. Fluxo de Funcionamento

1.  Usuário acessa `/inventory/index.php`
2.  Página consulta coletor central
3.  JSON consolidado é processado
4.  Tabela é gerada
5.  Clique em "Ver RAW" abre modal com dados completos

------------------------------------------------------------------------

## 7. Requisitos

-   PHP habilitado
-   Coletor central funcional
-   Permissões corretas (www-data)
-   SSH/SFTP configurado para deploy
-   Agentes de inventário ativos e acessíveis via rede interna

------------------------------------------------------------------------

## 8. Permissões Recomendadas

Diretórios:

    775

Arquivos:

    664

Owner / Group:

    www-data:www-data

------------------------------------------------------------------------


## 9. Autor

Módulo integrante da arquitetura de monitoramento de infraestrutura.
