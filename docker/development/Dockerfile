# This file is part of Pollaris.
# Copyright 2024-2026 Marien Fressinaud
# SPDX-License-Identifier: AGPL-3.0-or-later

FROM php:8.4-fpm

ENV COMPOSER_HOME /tmp

RUN apt-get update && apt-get install -y \
    git \
    libicu-dev \
    libpq-dev \
    libzip-dev \
    locales \
    unzip \
  && pecl install xdebug \
  && docker-php-ext-configure intl \
  && docker-php-ext-install -j$(nproc) intl pcntl pdo pdo_mysql zip \
  && docker-php-ext-enable xdebug \
  && echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini;

COPY --from=composer/composer /usr/bin/composer /usr/bin/composer
