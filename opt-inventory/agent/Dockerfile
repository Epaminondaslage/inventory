FROM php:8.2-apache

# ================================
# Dependências do sistema
# ================================
RUN apt-get update && apt-get install -y \
    procps \
    iproute2 \
    util-linux \
    pciutils \
    usbutils \
    jq \
    curl \
    net-tools \
    dnsutils \
    lsb-release \
    ca-certificates \
    docker.io \
    mosquitto-clients \
    && rm -rf /var/lib/apt/lists/*

# ================================
# Apache — muda porta para 8090
# network_mode:host compartilha a rede do host,
# então o Apache não pode usar a porta 80 (já usada pelo host)
# ================================
RUN echo "Listen 8090" > /etc/apache2/ports.conf
RUN sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8090>/' /etc/apache2/sites-enabled/000-default.conf
RUN a2enmod rewrite
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# ================================
# Copia arquivos
# ================================
COPY api/     /var/www/html/api/
COPY api/openapi.yaml /var/www/html/openapi.yaml
COPY swagger/ /var/www/html/docs/

# ================================
# Permissões
# ================================
RUN chmod -R 755 /var/www/html

# ================================
# Acesso ao Docker socket
# GID 127 = grupo docker do host (ajuste se diferente)
# ================================
RUN groupadd -f -g 143 dockerhost && usermod -aG dockerhost www-data

# ================================
# Diretórios runtime
# ================================
RUN mkdir -p /var/run/apache2 /var/lock /tmp

EXPOSE 8090
