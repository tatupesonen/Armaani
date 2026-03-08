#!/usr/bin/env bash
cp .env.example .env
composer install
npm ci
php artisan key:generate
php artisan migrate --force
npm run build
