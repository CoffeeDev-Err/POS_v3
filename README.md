# POS v3 — 8ShineRice

A full-stack point-of-sale and inventory management system built for retail workflows. The application combines a responsive React interface with a Laravel API and MySQL persistence for sales, inventory, expenses, customer credit, users, and reporting.

## Features

- Role-based login for superadmin, admin, and cashier users
- Product and category management
- Point-of-sale checkout and transaction history
- Inventory movements and low-stock monitoring
- Order workflow with edit locking
- Customer credit ledger, payments, and due dates
- Expense management
- Sales, stock, product, and transaction reports
- Transaction voiding and audit logs
- Store settings and receipt configuration
- Light and dark themes
- Optional demo catalog seeding

## Tech stack

- **Frontend:** React 19, Vite, Bootstrap, Bootstrap Icons
- **Backend:** PHP 8.2+, Laravel 12
- **Database:** MySQL
- **Deployment:** Hostinger shared hosting, with Vercel/Render configuration retained in the repository

## Project structure

```text
POS_v3/
├── src/         # React pages, components, hooks, and API client
├── public/      # Static web assets
├── backend/     # Laravel API, migrations, tests, and seeders
├── scripts/     # Deployment bundle preparation
└── DEPLOY.md    # Production deployment instructions
```

## Requirements

- Node.js and npm
- PHP 8.2 or newer
- Composer
- MySQL

## Local development

### 1. Clone the repository

```bash
git clone https://github.com/CoffeeDev-Err/POS_v3.git
cd POS_v3
```

### 2. Set up the Laravel API

```bash
cd backend
composer install
```

Copy `backend/.env.example` to `backend/.env`, then configure:

- `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`
- `POS_ADMIN_PASSWORD`
- Store name, address, phone, and receipt footer as needed

Initialize the application:

```bash
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

The API runs at `http://127.0.0.1:8000/api` by default.

### 3. Set up the React frontend

Open another terminal from the repository root:

```bash
npm install
```

Copy `.env.example` to `.env`. The local API setting is:

```env
VITE_API_BASE_URL=http://127.0.0.1:8000/api
```

Start the frontend:

```bash
npm run dev
```

## Quality checks

Frontend:

```bash
npm run lint
npm run build
```

Backend:

```bash
cd backend
php artisan test
```

## Deployment

The recommended production path packages the React frontend and Laravel API for a single-domain Hostinger deployment. See [DEPLOY.md](DEPLOY.md) for environment values, upload layout, SSH commands, and GitHub Actions configuration.

## Security

Set a strong `POS_ADMIN_PASSWORD`, keep all `.env` files private, and use production-only database credentials. Demo data is disabled unless `POS_SEED_DEMO_DATA=true` is explicitly configured.

