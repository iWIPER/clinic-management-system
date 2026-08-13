#!/bin/sh
set -e

cd /var/www/html

role="${1:-web}"

case "$role" in
    web)
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
        ;;
    worker)
        php artisan config:cache
        exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=120
        ;;
    scheduler)
        php artisan config:cache
        exec php artisan schedule:run
        ;;
    migrate)
        php artisan config:cache
        exec php artisan migrate --force
        ;;
    *)
        exec "$@"
        ;;
esac
