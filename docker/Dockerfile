FROM php:8.2-fpm-bookworm

# Install required system libraries and PHP extensions
RUN apt-get update && apt-get install -y \
    wget \
    libldap2-dev \
    libzip-dev \
    libonig-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    unzip \
    cron \
    iputils-ping \
    traceroute \
    mtr \
    whois \
    dnsutils \
    krb5-user \
    && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install ldap pdo pdo_mysql mbstring zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy application source code
COPY . /var/www/html

# Set proper ownership permissions
RUN chown -R www-data:www-data /var/www/html

# Copy security hardening config
COPY docker/php-security.ini /usr/local/etc/php/conf.d/security.ini

# Install PowerShell Core (pwsh) for Exchange PowerShell integration via WinRM
RUN wget -q https://packages.microsoft.com/config/debian/12/packages-microsoft-prod.deb \
    && dpkg -i packages-microsoft-prod.deb \
    && rm -f packages-microsoft-prod.deb \
    && apt-get update && apt-get install -y powershell \
    && rm -rf /var/lib/apt/lists/*

# Install PSWSMan module for WinRM support on Linux
RUN pwsh -Command ' \
    $ErrorActionPreference = "Stop"; \
    Install-Module PSWSMan -Force -AllowClobber -Scope CurrentUser; \
    Import-Module PSWSMan; \
    Install-WSMan; \
    if (!(Get-Module -ListAvailable -Name PSWSMan)) { throw "PSWSMan module not installed"; }' \
    && rm -rf /root/.local/share/powershell/ModuleAnalysisCache

# Remove phpdbg (unnecessary attack surface)
RUN rm -f /usr/bin/phpdbg /usr/local/bin/phpdbg 2>/dev/null; true
