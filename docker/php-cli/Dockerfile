# This Dockerfile provides a base level of OS packages with PHP-CLI and extensions

ARG PHP_CLI_VERSION=7.1-cli

FROM php:${PHP_CLI_VERSION}

# Set consistent timezone
ENV CONTAINER_TIMEZONE="UTC"
RUN rm -f /etc/localtime \
 && ln -s /usr/share/zoneinfo/${CONTAINER_TIMEZONE} /etc/localtime

 # Worker name and index (TODO: where to set this?)
 ENV WORKER_NAME="send-emails" \
     WORKER_INDEX="1"

# Install prerequisite OS packages
RUN apt-get update -y && apt-get upgrade -y && apt-get install -y curl git mysql-client

# install the PHP extensions we need
RUN set -ex \
  && buildDeps=' \
    libjpeg62-turbo-dev \
    libpng12-dev \
    libpq-dev \
    zlib1g-dev \
    libicu-dev \
    libmemcached-dev \
  ' \
  && apt-get install -y --no-install-recommends $buildDeps \
  && pecl install -o -f redis \
  && docker-php-ext-enable redis \
  && ls -la /usr/local/etc/php/conf.d/ \
  && docker-php-ext-configure gd \
    --with-jpeg-dir=/usr \
    --with-png-dir=/usr \
  && docker-php-ext-install -j "$(nproc)" gd mbstring opcache pdo pdo_mysql zip

RUN cd /tmp \
  && git clone -b php7 https://github.com/php-memcached-dev/php-memcached \
  && cd php-memcached \
  && phpize \
  && ./configure \
  && make \
  && cp /tmp/php-memcached/modules/memcached.so /usr/local/lib/php/extensions/no-debug-non-zts-20160303/memcached.so \
  && docker-php-ext-enable memcached \
  && ls -la /usr/local/etc/php/conf.d/

RUN docker-php-ext-install sockets

# See https://secure.php.net/manual/en/opcache.installation.php
RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=60'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
  } > /usr/local/etc/php/conf.d/opcache-recommended.ini

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
	php composer-setup.php && \
	mv composer.phar /usr/local/bin/composer && \
	php -r "unlink('composer-setup.php');"

# Register the COMPOSER_HOME environment variable.
ENV COMPOSER_HOME=/root/.composer

# Add global binary directory to PATH.
ENV PATH /root/.composer/vendor/bin:$PATH

# Allow Composer to be run as root.
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN curl -sS https://getcomposer.org/installer | \
   php -- --install-dir=/usr/bin/ --filename=composer

RUN composer global require drush/drush:8.x

WORKDIR /var/www/docroot

# Copy start script
COPY noop.php /root/

CMD [ "php", "/root/noop.php" ]
