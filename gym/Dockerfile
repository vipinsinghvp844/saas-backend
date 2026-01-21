FROM php:8.2-cli

# MySQL driver install karo
RUN docker-php-ext-install pdo pdo_mysql mysqli

WORKDIR /app
COPY . .

EXPOSE 10000

CMD ["php", "-S", "0.0.0.0:10000", "index.php"]
