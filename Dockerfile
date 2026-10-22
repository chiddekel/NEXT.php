# ==========================================
# NEXT.php Production Dockerfile
# Optimized for maximum performance & security
# ==========================================

FROM php:8.4-cli-alpine AS builder

# Install system dependencies & Composer
RUN apk add --no-cache curl git unzip libzip-dev \
    && docker-php-ext-install opcache pcntl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy dependency definition
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Copy application source
COPY . .

# Run NEXT.php production build (compiles routes & pre-renders SSG pages)
RUN if [ -d "app" ]; then php bin/next build; fi

# ==========================================
# Final Production Runtime Image
# ==========================================
FROM php:8.4-cli-alpine AS runner

# Production OPcache & Security Configuration
RUN apk add --no-cache curl \
    && docker-php-ext-install opcache pcntl \
    && { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.revalidate_freq=0'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini \
    && { \
        echo 'expose_php=Off'; \
        echo 'display_errors=Off'; \
        echo 'log_errors=On'; \
        echo 'error_reporting=E_ALL & ~E_DEPRECATED & ~E_STRICT'; \
        echo 'memory_limit=256M'; \
    } > /usr/local/etc/php/conf.d/security.ini

# Run as non-privileged user
RUN addgroup -S appgroup && adduser -S appuser -G appgroup

WORKDIR /app
COPY --from=builder --chown=appuser:appgroup /app /app

USER appuser

EXPOSE 3000

ENV APP_ENV=production \
    PORT=3000 \
    HOST=0.0.0.0

# Built-in health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:3000/__health || exit 1

CMD ["php", "bin/next", "start", "-p", "3000", "-H", "0.0.0.0"]
