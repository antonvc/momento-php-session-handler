# Build stage for the gRPC extension
FROM php:8.2-fpm-alpine3.17 as builder

# Install dependencies, build, and enable the gRPC extension
RUN apk --no-cache add zlib-dev linux-headers ${PHPIZE_DEPS} \
  && pecl install grpc \
  && docker-php-ext-enable grpc

# Copy the composer binary from the composer:lts image
COPY --from=composer:lts /usr/bin/composer /usr/local/bin/composer

# Set the working directory to /app
WORKDIR /app
# Copy composer files to the container
COPY composer.* /app/
# Install composer dependencies without development dependencies, scripts, and with minimal output
RUN composer install --no-dev --no-scripts --no-progress --prefer-dist

# Final stage, copy necessary files and extensions
FROM php:8.2-fpm-alpine3.17
# Install libstdc++ for gRPC extension compatibility
RUN apk add --no-cache libstdc++

# Copy the PHP extensions from the builder stage to the final stage
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
# Copy the PHP extension configuration from the builder stage to the final stage
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
# Copy the composer dependencies from the builder stage to the final stage
COPY --from=builder /app/vendor/ /var/www/html/vendor/
# Copy the app files to the final stage
COPY index.php /var/www/html/index.php

# Enable gRPC extension
RUN docker-php-ext-enable grpc

# Set the working directory to /var/www/html
WORKDIR /var/www/html
