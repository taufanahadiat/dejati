# Dejati POS Replacement App

This is a new REST API plus single-page frontend based on the existing PHP app in `/www`.

## Run

```bash
php -S 127.0.0.1:8080 -t new-app/public new-app/public/router.php
```

Open `http://127.0.0.1:8080`.

Demo users:

- `admin` / `admin123`
- `kasir` / `admin123`
- `jati35` / `admin123`

## What Was Preserved

- Cafe and carwash catalog in one POS flow.
- Product categories, variants, quantity, notes, and discounts.
- Carwash metadata: plate number, service employee, vehicle size, vacuum add-on.
- Carwash profit split: 30% employee and 70% management on saved orders.
- Paid transactions and open bills.
- Transaction history with details and finalization.
- Daily closing summary with cash, QRIS, card, cafe, carwash, expenses, and net total.

## Architecture

- `public/index.html`, `public/app.js`, `public/styles.css`: frontend application.
- `api/index.php`: REST API controller and business rules.
- `data/seed.json`: initial data extracted and normalized from `/www/database/si_cucian.sql`.
- `data/app.json`: runtime data store created automatically on first API request.

The JSON repository is intentionally isolated behind the API layer so it can be replaced by MySQL, PostgreSQL, or SQLite later without changing the frontend contract.
