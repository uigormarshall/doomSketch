#!/bin/sh
set -e

cd /var/www/html

# Rede de segurança: garante um .env (normalmente já vem baked do build).
[ -f .env ] || cp .env.example .env

# Apenas o container principal (app) roda migrations e cria o symlink de storage.
# O worker e o scheduler herdam o resto via restart até o banco estar migrado.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "[entrypoint] aguardando o banco e aplicando migrations..."
    php artisan migrate --force
    php artisan storage:link 2>/dev/null || true

    # Seed opcional de demonstração: defina SEED_DEMO=true no compose.
    if [ "${SEED_DEMO:-false}" = "true" ]; then
        echo "[entrypoint] populando dados de demonstração..."
        php artisan db:seed --class=DemoSeeder --force || true
    fi
fi

exec "$@"
