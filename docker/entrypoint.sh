#!/bin/sh
set -e

# O código chega por bind mount, então os arquivos entram no container com o
# dono do host (root, no Windows). Os workers do php-fpm rodam como www-data e
# precisam escrever em storage/ e bootstrap/cache — sem isso o Laravel falha
# com "tempnam(): file created in the system's temporary directory", que é o
# PHP caindo no /tmp do sistema após não conseguir escrever.
#
# Corrigir aqui, e não no build, porque o bind mount substitui o que a imagem
# tiver nesses caminhos: um chown feito no Dockerfile é descartado no runtime.

if [ -d /app/storage ]; then
    mkdir -p \
        /app/storage/framework/cache \
        /app/storage/framework/sessions \
        /app/storage/framework/views \
        /app/storage/logs
    chown -R www-data:www-data /app/storage
fi

if [ -d /app/bootstrap/cache ]; then
    chown -R www-data:www-data /app/bootstrap/cache
fi

exec "$@"
