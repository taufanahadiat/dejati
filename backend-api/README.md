# Dejati Coffee Mobile REST API

REST API for the Android APK. It is based on the existing `/www` PHP POS workflow, but does not modify `/www`.

## Local Run

```bash
php -S 127.0.0.1:8090 -t backend-api/public backend-api/public/router.php
```

## Production URL

Configure the web server so this project is served from:

```text
https://api.dejaticoffee.com
```

A sample Caddy config is included in `backend-api/Caddyfile`.

## Auth

```bash
curl -X POST https://api.dejaticoffee.com/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"admin123"}'
```

Use the returned token as:

```text
Authorization: Bearer TOKEN
```

## Main Endpoints

- `POST /auth/login`
- `POST /auth/logout`
- `GET /auth/me`
- `GET /catalog`
- `GET|POST /categories`
- `GET|POST /products`
- `PUT|DELETE /products/{id}`
- `GET|POST /carwash-products`
- `GET|POST /transactions`
- `GET /transactions/{id}`
- `PATCH /transactions/{id}/finalize`
- `GET /reports/summary?from=YYYY-MM-DD&to=YYYY-MM-DD`
- `GET /reports/daily`
- `GET|POST /expenses`
- `GET|POST /closing`
- `GET|POST /users`
- `GET|PUT /printer-settings`

The current repository implementation uses JSON files for portability. The API layer isolates persistence so it can be swapped to MySQL using the existing `/www/database/si_cucian.sql` schema.

## Docker Deployment

From the repository root:

```bash
docker compose -f docker-compose.api.yml up -d --build
```

The API listens on `127.0.0.1:8090` on the host.

## Cloudflare Tunnel Route

The DNS route has been created with:

```bash
cloudflared tunnel route dns dae86d49-b921-48f9-85aa-249439fa4808 api.dejaticoffee.com
```

Apply the validated ingress config with:

```bash
backend-api/apply-cloudflared-api-route.sh
```

That script backs up `/etc/cloudflared/config.yml`, installs `backend-api/cloudflared-config.proposed.yml`, validates it, restarts `cloudflared`, and tests `https://api.dejaticoffee.com/health`.
