# CaribWeather

CaribWeather is a Laravel 12 Progressive Web Application MVP for Caribbean weather monitoring. It includes a responsive weather dashboard, Leaflet map, AI assistant interface, alerts manager, historical charts, and PWA offline/install support.

## Stack

- PHP 8.3+
- Laravel 12
- Laravel Sanctum
- SQLite for local development, MySQL/MariaDB recommended for production
- Tailwind CSS CDN for the current MVP UI
- Alpine.js
- Leaflet.js
- Chart.js
- PWA manifest and service worker

## Local Setup

```bash
composer install
npm.cmd install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Open `http://127.0.0.1:8000`.

PowerShell on this machine blocks `npm.ps1`, so use `npm.cmd` instead of `npm`.

## API Endpoints

The frontend is already wired to backend proxy endpoints:

- `GET /api/weather/current?location=...`
- `GET /api/weather/search?q=...`
- `GET /api/weather/historical?location=...&start=...&end=...`
- `GET /api/storms/active`
- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/auth/user`
- `POST /api/auth/logout`
- `POST /api/assistant/query`
- `GET /api/alerts`
- `POST /api/alerts`
- `PUT /api/alerts/{alert}`
- `DELETE /api/alerts/{alert}`
- `GET /api/notifications`
- `POST /api/notifications/{notification}/read`
- `GET /api/saved-locations`
- `POST /api/saved-locations`
- `DELETE /api/saved-locations/{savedLocation}`

Weather endpoints use Open-Meteo where possible and fall back safely when providers are unavailable. Historical analytics use Meteostat when `METEOSTAT_API_KEY` is configured, then fall back to Open-Meteo Archive. Alerts, saved locations, and in-app alert notifications are database-backed and scoped to a browser client ID for guest use, with nullable `user_id` columns ready for authenticated accounts.

Guest alerts and saved locations are automatically claimed by the account when the same browser logs in or registers.

## Map Layers

- Rainfall / Radar uses RainViewer public radar tiles.
- Tropical Storms uses the NOAA/NHC `CurrentStorms.json` feed through the Laravel proxy.
- Temperature, wind, and cloud layers show the selected location signal and support click-to-query point weather through `/api/weather/current`.

## Alert Scheduler

Run the checker manually during development:

```bash
php artisan caribweather:check-alerts
```

Run the Laravel scheduler locally in a second terminal if you want automatic checks every 15 minutes:

```bash
.\schedule.cmd
```

In production, configure cron to run `php artisan schedule:run` every minute.

## Environment Keys

Add provider keys to `.env` when available:

```env
OPENWEATHER_API_KEY=
OPENAI_API_KEY=
METEOSTAT_API_KEY=
METEOSTAT_API_HOST=meteostat.p.rapidapi.com
METEOSTAT_API_BASE_URL=https://meteostat.p.rapidapi.com
SENDGRID_API_KEY=
WEATHER_CACHE_TTL_MINUTES=10
AI_RATE_LIMIT_PER_HOUR=20
```

## Verification

Useful commands:

```bash
php artisan test
php artisan route:list
npm.cmd run build
```
