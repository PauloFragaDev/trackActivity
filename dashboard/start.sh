#!/bin/sh
set -e
php artisan migrate --force
php artisan migrate --database=supabase --path=database/migrations/team --force
php -S 0.0.0.0:$PORT -t public/
