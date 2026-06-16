# CaribWeather

CaribWeather is a Progressive Web Application MVP for Caribbean weather monitoring. It implements the frontend experience described in the SRS: a responsive weather dashboard, interactive weather map, AI weather assistant interface, configurable alerts, historical weather analytics, and offline PWA behavior.

## Current Implementation

- HTML5, CSS3, JavaScript
- Tailwind CSS CDN for mobile-first layout
- Alpine.js for lightweight reactivity
- Leaflet.js with OpenStreetMap tiles
- Chart.js historical weather charts
- Web app manifest and service worker for PWA support
- Backend-ready API boundaries under `/api/...` with mock fallback data when no backend is running

## Running Locally

Use any static web server from the repository root. Service workers require `http://localhost` or HTTPS.

Examples:

```bash
python -m http.server 8000
```

Then open `http://localhost:8000`.

## Backend Integration Targets

The frontend is prepared to call these backend proxy endpoints so third-party API keys remain server-side:

- `GET /api/weather/current?location=...`
- `GET /api/weather/search?q=...`
- `GET /api/weather/historical?location=...&start=...&end=...`
- `POST /api/assistant/query`
- `GET|POST|PUT|DELETE /api/alerts`

Recommended production backend: PHP 8.3+, Laravel 12, MySQL/MariaDB, Laravel Sanctum, Scheduler/Queues, and optional Redis caching.

## Notes

This workspace does not currently include PHP, Composer, Node, or npm, so the Laravel application could not be scaffolded directly in this environment. The current MVP is a functional frontend/PWA layer designed to plug into that Laravel backend once the tooling is available.
