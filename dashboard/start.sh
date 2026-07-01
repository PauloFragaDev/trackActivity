#!/bin/sh
set -e
php artisan migrate --force

if [ -n "$SUPABASE_DB_HOST" ]; then
    php artisan migrate --database=supabase --path=database/migrations/team --force
fi

php artisan db:seed --class=TeamUsersSeeder --force

php -S 0.0.0.0:$PORT -t public/
