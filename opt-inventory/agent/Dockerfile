FROM php:8.2-apache

# ================================
# Instala dependências do sistema
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
    systemd \
    docker.io \
    && rm -rf /var/lib/apt/lists/*

# ================================
# Ajustes do Apache
# ================================
RUN a2enmod rewrite
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# ================================
# Copia API
# ================================
COPY api/ /var/www/html/api/

# ================================
# Copia OpenAPI (para Swagger)
# ================================
COPY api/openapi.yaml /var/www/html/openapi.yaml

# ================================
# Copia Swagger UI (docs)
# ================================
COPY swagger/ /var/www/html/docs/

# ================================
# Permissões
# ================================
RUN chmod -R 755 /var/www/html

# ================================
# Diretórios necessários (runtime)
# ================================
RUN mkdir -p /var/run/apache2 \
    /var/lock \
    /tmp

# ================================
# Porta padrão
# ================================
EXPOSE 80