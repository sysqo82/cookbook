# Cookbook

A personal recipe management application built with Symfony 7 and PHP 8.5. It allows you to store and organise recipes with ingredients, steps, components, and images. An EasyAdmin-powered admin interface makes it easy to manage your cookbook content.

## Stack

- **PHP 8.5** with Symfony 7
- **MySQL** for data storage
- **Nginx** as the web server
- **Webpack Encore** for frontend assets
- **Anthropic Claude** (`claude-sonnet-4-6`) for AI recipe image parsing
- **AWS S3** for image storage
- **Symfony Messenger** for async worker queue

## Getting started

### Prerequisites

- Docker and Docker Compose

### Setup

1. Copy the example environment file and set the port the app will be available on:

   ```bash
   cp .env.example .env
   ```

2. Build and start the containers:

   ```bash
   docker compose up -d --build
   ```

   On first run the entrypoint will wait for the database to be ready and then automatically run migrations.

3. Open [http://localhost:10000](http://localhost:10000) in your browser.

### Cloudflare Tunnel (remote access)

Remote access for `cookbook.lockpc.co.uk` is managed by the `lock-pc-local` repository's `cloudflared` service.

This repository only serves the app locally on `http://localhost:10000`.

### Admin interface

The EasyAdmin interface is available at `/admin`.

Recipes can be imported from BBC Good Food via the import page at `/admin/import` — enter the recipe slug from the URL and it will be fetched and saved automatically.

Recipes can also be parsed from photos at `/admin/parse-images`. Upload one or more images of a recipe (e.g. a photo of a cookbook page) and Claude will extract the name, ingredients, components, steps, and a crop of the hero photo. The parsed recipe is saved with a **Pending Approval** status so you can review it before publishing. Queue status is visible in the admin sidebar.

## Environment variables

Copy `.env.example` to `.env` and fill in the values:

| Variable | Required | Description |
|---|---|---|
| `APP_SECRET` | Yes | Random 32-byte hex string — generate with `openssl rand -hex 32` |
| `DATABASE_URL` | Yes | MySQL connection string |
| `ANTHROPIC_API_KEY` | Yes* | API key for Claude — required if using AI image parsing |
| `MESSENGER_TRANSPORT_DSN` | Yes | Defaults to `doctrine://default?auto_setup=1` (queue stored in DB) |
| `AWS_S3_KEY` | Yes | AWS access key ID |
| `AWS_S3_SECRET` | Yes | AWS secret access key |
| `AWS_S3_REGION` | Yes | S3 bucket region (e.g. `eu-west-2`) |
| `AWS_S3_BUCKET` | Yes | S3 bucket name |
| `GOODFOOD_API_URL_FORMAT` | No | URL template for BBC Good Food recipe API |

\* AI parsing will fail at runtime if omitted, but the rest of the app works without it.

## Deployment

The production image is built from `docker/php/Dockerfile.prod` — it bundles PHP-FPM, Nginx, and the compiled app assets into a single image.

### Unraid (or any Docker host)

Two containers are needed, both from the same image:

**App container**
- Image: `ghcr.io/aussieveen/cookbook:TAG`
- Port: `80` (map to your host port)
- `CONTAINER_ROLE=app` (or omit — defaults to app)
- All env vars from the table above
- Restart policy: `unless-stopped`

**Worker container**
- Image: `ghcr.io/aussieveen/cookbook:TAG` (same image)
- No port mapping
- Command override: `php bin/console messenger:consume async failed --time-limit=3600 --failure-limit=5 --memory-limit=256M`
- All env vars from the table above (same values as app container) with the addition of
- `CONTAINER_ROLE=worker`
- Restart policy: `unless-stopped`

The worker exits cleanly after one hour (`--time-limit=3600`) to release memory and stale connections. The Docker restart policy relaunches it immediately — no cron or external supervisor needed.
