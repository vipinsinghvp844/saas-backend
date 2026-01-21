FROM php:8.2-cli

RUN apt-get update \
    && docker-php-ext-install pdo pdo_mysql mysqli

WORKDIR /app
COPY . .

# Railway ka PORT use karo (dynamic)
CMD ["sh", "-c", "php -S 0.0.0.0:$PORT index.php"]
