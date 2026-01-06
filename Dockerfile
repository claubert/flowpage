FROM php:8.2-apache-bullseye

# Instalar extensões necessárias (mysqli para banco, curl, etc)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Ativar mod_rewrite do Apache
RUN a2enmod rewrite

# Copiar arquivos do projeto
COPY ./public /var/www/html

# Ajustar permissões
RUN chown -R www-data:www-data /var/www/html
