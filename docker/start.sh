#!/bin/sh
set -eu

PORT="${PORT:-10000}"
case "$PORT" in
    ''|*[!0-9]*) echo 'PORT debe ser un puerto numérico.' >&2; exit 1 ;;
esac
if [ "${#PORT}" -gt 5 ] || [ "$PORT" -lt 1024 ] || [ "$PORT" -gt 65535 ]; then
    echo 'PORT debe estar entre 1024 y 65535.' >&2
    exit 1
fi
export PORT

# No migrar ni crear cuentas al arrancar un servicio público.
if [ "${APP_ENV:-}" != 'production' ]; then
    echo 'Configura APP_ENV=production para este contenedor.' >&2
    exit 1
fi
if [ "${APP_TIMEZONE:-}" != 'America/La_Paz' ]; then
    echo 'Configura APP_TIMEZONE=America/La_Paz para la clínica.' >&2
    exit 1
fi
if [ -z "${DB_HOST:-}" ] || [ -z "${DB_NAME:-}" ] || [ -z "${DB_USER:-}" ] || [ -z "${DB_PASSWORD:-}" ]; then
    echo 'Configura las variables DB_HOST, DB_NAME, DB_USER y DB_PASSWORD.' >&2
    exit 1
fi
case "${PGSSLMODE:-}" in
    require|verify-ca|verify-full) ;;
    *) echo 'Configura PGSSLMODE para exigir TLS a PostgreSQL.' >&2; exit 1 ;;
esac
apache2ctl -t
exec docker-php-entrypoint "$@"
