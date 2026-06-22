const CACHE_NAME = 'caribweather-v8';
const APP_SHELL = [
  '/',
  '/offline.html',
  '/manifest.webmanifest',
  '/assets/css/styles.css',
  '/assets/js/app.js',
  '/assets/img/icon.svg'
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;

  if (new URL(request.url).pathname.startsWith('/api/')) {
    event.respondWith(networkFirst(request));
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => cached || fetch(request).then((response) => {
      const copy = response.clone();
      caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
      return response;
    }).catch(() => caches.match('/offline.html')))
  );
});

self.addEventListener('push', (event) => {
  let payload = { title: 'CaribWeather Alert', body: 'A weather alert was triggered.' };
  if (event.data) {
    try {
      payload = event.data.json();
    } catch (error) {
      payload.body = event.data.text();
    }
  }

  event.waitUntil(self.registration.showNotification(payload.title || 'CaribWeather Alert', {
    body: payload.body || payload.message || 'A weather alert was triggered.',
    icon: '/assets/img/icon.svg',
    badge: '/assets/img/icon.svg',
    data: { url: payload.url || '/#alerts' }
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data?.url || '/#alerts';
  event.waitUntil(clients.openWindow(url));
});

async function networkFirst(request) {
  const cache = await caches.open(CACHE_NAME);
  try {
    const response = await fetch(request);
    cache.put(request, response.clone());
    return response;
  } catch (error) {
    return (await cache.match(request)) || new Response(JSON.stringify({ error: 'offline' }), {
      headers: { 'Content-Type': 'application/json' },
      status: 503
    });
  }
}
