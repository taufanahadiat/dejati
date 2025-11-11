// service-worker.js
const CACHE_NAME = 'dejati-cache-v1';
const OFFLINE_URL = '/offline.html';

// ✅ List of core files to cache (add your own assets)
const ASSETS_TO_CACHE = [
  '/',
  '/index.php',
  '/dist/css/adminlte.min.css',
  '/plugins/jquery/jquery.min.js',
  '/plugins/bootstrap/js/bootstrap.bundle.min.js',
  '/dist/js/adminlte.min.js',
  '/img/logo-only-white.png',
  OFFLINE_URL
];

// ✅ Install event: cache core assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(ASSETS_TO_CACHE))
      .then(() => self.skipWaiting())
  );
});

// ✅ Activate event: cleanup old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.map((key) => key !== CACHE_NAME && caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

// ✅ Fetch handler: cache-first fallback
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Force HTTPS
  if (url.protocol === 'http:') {
    const httpsUrl = url.href.replace('http:', 'https:');
    event.respondWith(Response.redirect(httpsUrl));
    return;
  }

  if (event.request.method !== 'GET') return;

  event.respondWith(
    caches.match(event.request).then((cached) =>
      cached || fetch(event.request).catch(() => caches.match(OFFLINE_URL))
    )
  );
});
