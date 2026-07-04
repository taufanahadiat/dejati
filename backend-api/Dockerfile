FROM php:8.3-cli-alpine

WORKDIR /app

COPY public ./public
COPY data ./data

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/router.php"]
