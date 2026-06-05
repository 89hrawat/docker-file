FROM php:8.3-fpm-alpine

RUN apk update && \
    apk upgrade && \
    apk add --no-cache \
        tzdata \
        curl \
        wget \
        bind-tools \
        iputils \
        net-tools \
        busybox-extras \
        bash

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS && \
    docker-php-ext-install mysqli pdo pdo_mysql opcache && \
    pecl install redis && \
    docker-php-ext-enable redis && \
    apk del .build-deps

WORKDIR /var/www/html

COPY index.php .

CMD ["php-fpm","-F"]
