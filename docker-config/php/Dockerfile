FROM php:8.2-fpm

# -----------------------------------------------------------
# SYSTEM DEPENDENCIES
# -----------------------------------------------------------
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zlib1g-dev \
    libpq-dev \
    libicu-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    curl \
    gnupg \
    libmagickwand-dev \
    && rm -rf /var/lib/apt/lists/*

# -----------------------------------------------------------
# PHP EXTENSIONS
# -----------------------------------------------------------
RUN docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && docker-php-ext-install zip \
    && docker-php-ext-install opcache

# -----------------------------------------------------------
# PECL EXTENSIONS (Redis, APCu, Imagick)
# -----------------------------------------------------------
RUN pecl install redis apcu imagick \
    && docker-php-ext-enable redis apcu imagick

# -----------------------------------------------------------
# INSTALL COMPOSER
# -----------------------------------------------------------
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# -----------------------------------------------------------
# NODEJS (NEW NODESOURCE METHOD)
# -----------------------------------------------------------
RUN apt-get update && apt-get install -y ca-certificates curl gnupg \
    && mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
        -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_20.x nodistro main" \
        > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# -----------------------------------------------------------
# INSTALL YARN
# -----------------------------------------------------------
RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg -o /usr/share/keyrings/yarn.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/yarn.gpg] https://dl.yarnpkg.com/debian stable main" \
        > /etc/apt/sources.list.d/yarn.list \
    && apt-get update \
    && apt-get install -y yarn \
    && rm -rf /var/lib/apt/lists/*

# -----------------------------------------------------------
# TIMEZONE
# -----------------------------------------------------------
ENV TZ=America/Montevideo
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# -----------------------------------------------------------
# PHP MEMORY LIMIT
# -----------------------------------------------------------
RUN echo 'memory_limit = 1024M' > /usr/local/etc/php/conf.d/memory-limit.ini

# -----------------------------------------------------------
# WORKDIR
# -----------------------------------------------------------
WORKDIR /var/www/html

# -----------------------------------------------------------
# (Optional) Change to non-root user (recommended)
# -----------------------------------------------------------
# RUN useradd -u 1000 -ms /bin/bash www \
#     && chown -R www:www /var/www
# USER www
