# Deployment Guide

This repo now supports a simple Hostinger shared-hosting deployment on a single domain:

- Frontend: `https://8shinerice.com`
- Laravel API: `https://8shinerice.com/api`
- Database: Hostinger MySQL

As of July 22, 2026, this is the recommended deployment path for this project.

## 1. Build the Hostinger upload bundle

From the repo root, create the production frontend build and the upload-ready folder structure:

```bash
npm install
npm run build:hostinger
```

This generates:

```text
.deploy/hostinger/
  public_html/
    index.html
    assets/
    api/
      index.php
      .htaccess
  laravel-pos/
    app/
    bootstrap/
    config/
    database/
    public/
    resources/
    routes/
    storage/
    artisan
```

The script already rewrites `public_html/api/index.php` so it points to the uploaded Laravel app folder outside the web root.

## 2. Frontend production environment

Create a local `.env.production` file in the repo root using:

```env
VITE_API_BASE_URL=https://8shinerice.com/api
```

The checked-in [.env.production.example](.env.production.example) already matches the one-domain Hostinger setup.

## 3. Backend production environment

Use [backend/.env.hostinger.example](backend/.env.hostinger.example) as the starting point for your real backend `.env`.

Important values:

- `APP_URL=https://8shinerice.com/api`
- `FRONTEND_URL=https://8shinerice.com`
- `CORS_ALLOWED_ORIGINS=https://8shinerice.com,https://www.8shinerice.com`
- `API_ROUTE_PREFIX=` (leave blank because the app already lives under `/api`)
- `DB_HOST=localhost`
- `DB_PORT=3306`
- `DB_DATABASE=u889675904_PosDb`
- `DB_USERNAME=u889675904_posuser`
- `DB_PASSWORD=...`
- `POS_ADMIN_PASSWORD=...`

## 4. Upload to Hostinger

Upload the generated folders from `.deploy/hostinger` as follows:

1. Upload everything inside `.deploy/hostinger/public_html/` to your domain's `public_html/`
2. Upload `.deploy/hostinger/laravel-pos/` to your hosting home directory beside `public_html`

Expected server layout:

```text
~/domains/8shinerice.com/public_html
~/laravel-pos
```

Only the Laravel `api/` public files should be web-accessible. The rest of the backend stays outside `public_html`.

## 5. Install backend dependencies over SSH

After uploading, connect with SSH and run:

```bash
cd ~/laravel-pos
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force --seed
php artisan config:cache
php artisan route:cache
```

If you update the backend `.env`, clear and rebuild the config cache:

```bash
php artisan config:clear
php artisan config:cache
```

## 6. Test the deployment

Check these URLs:

1. `https://8shinerice.com/api/up`
2. `https://8shinerice.com`

Then log in with:

- username: `owner`
- password: the value from `POS_ADMIN_PASSWORD`

## Notes

- Bluetooth printing still depends on browser/device support and HTTPS.
- This repo keeps the backend and frontend separate in source, but Hostinger upload is prepared as a one-domain package.
- The generated `.deploy/` folder is ignored by git and safe to regenerate.
