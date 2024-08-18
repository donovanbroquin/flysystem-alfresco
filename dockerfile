FROM php:8.3.10-bookworm

# Install system dependencies, prepare supervisord and update access for final user
RUN apt update -y && \
    apt install -y git libzip-dev zip && \
    apt clean && \
    rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2.7.7 /usr/bin/composer /usr/bin/composer

# Install Xdebug
RUN pecl install xdebug && \
    docker-php-ext-enable xdebug && \
    echo "xdebug.mode=develop,debug,coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

WORKDIR /var/package

ENTRYPOINT ["tail"]
CMD ["-f","/dev/null"]