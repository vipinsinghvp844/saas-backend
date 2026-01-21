FROM php:8.2-cli

RUN apt-get update \
    && docker-php-ext-install pdo pdo_mysql mysqli

WORKDIR /app
COPY . .

CMD ["sh", "-c", "php -S 0.0.0.0:$PORT"]
