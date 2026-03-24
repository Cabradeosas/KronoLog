# Imagen base PHP 8.2 con Apache
FROM php:8.2-apache

# Instalar dependencias del sistema y extensiones PHP necesarias
RUN apt-get update && apt-get install -y \
    zip unzip curl git \
    && docker-php-ext-install pdo pdo_mysql mysqli

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Establecer directorio de trabajo
WORKDIR /var/www/html

# Copiar todo el proyecto al contenedor
COPY . .

# Instalar dependencias de Composer sin paquetes de desarrollo
RUN composer install --no-dev --optimize-autoloader

# Asignar permisos correctos a Apache
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80