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
COPY src/composer.* /app/
# Install composer dependencies without development dependencies, scripts, and with minimal output
RUN composer install --no-dev --no-scripts --no-progress --prefer-dist

# Final FPM stage, copy necessary files and extensions
FROM php:8.2-fpm-alpine3.17 as fpm
# Install libstdc++ for gRPC extension compatibility
RUN apk add --no-cache libstdc++

# Copy the PHP extensions from the builder stage to the final stage
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
# Copy the PHP extension configuration from the builder stage to the final stage
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
# Copy the app files to the final stage
COPY src/ /var/www/html/
# Copy the composer dependencies from the builder stage to the final stage
COPY --from=builder /app/vendor/ /var/www/html/vendor/

# Enable gRPC extension
RUN docker-php-ext-enable grpc

# Set the working directory to /var/www/html
WORKDIR /var/www/html

#the nginx image to support the same app
FROM nginx:latest as nginx
COPY nginx/default.conf /etc/nginx/conf.d/default.conf
COPY src/ /var/www/html/
COPY --from=builder /app/vendor/ /var/www/html/vendor/

EXPOSE 80