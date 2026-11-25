FROM php:8.3-apache
RUN apt-get update && apt-get install -y libcurl4-openssl-dev && docker-php-ext-install curl && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
COPY . /var/www/html/
RUN printf 'upload_max_filesize=5M\npost_max_size=7M\n' > /usr/local/etc/php/conf.d/amicao.ini
EXPOSE 80
