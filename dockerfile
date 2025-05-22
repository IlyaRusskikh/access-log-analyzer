FROM php:8.4.7-cli

WORKDIR /app

COPY . /app

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install

ENTRYPOINT ["php", "/app/analyze.php"]