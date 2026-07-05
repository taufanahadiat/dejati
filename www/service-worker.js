const CACHE_NAME = 'dejati-cache-v4';
const OFFLINE_URL = '/offline.html';
const ASSETS_TO_CACHE = [
  OFFLINE_URL,
  '/manifest.json',
  '/assets/img/192.png',
  '/assets/img/512.png',
  '/assets/img/logo-dejati-black.png',
  '/assets/js/bluetooth-printer-manager.js',
  '/dist/css/adminlte.min.css',
  '/dist/css/style.css',
  '/plugins/jquery/jquery.min.js',
  '/plugins/bootstrap/js/bootstrap.bundle.min.js',
  '/dist/js/adminlte.min.js'
];

function isStaticAsset(url) {
  return url.pathname.startsWith('/assets/') ||
    url.pathname.startsWith('/dist/') ||
    url.pathname.startsWith('/plugins/') ||
    url.pathname === '/manifest.json' ||
    url.pathname === OFFLINE_URL;
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(ASSETS_TO_CACHE))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.map((key) => key !== CACHE_NAME && caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  const requestUrl = new URL(event.request.url);
  if (requestUrl.origin !== self.location.origin) return;

  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request, { cache: 'no-store' })
        .catch(() => caches.match(OFFLINE_URL))
    );
    return;
  }

  if (!isStaticAsset(requestUrl)) {
    event.respondWith(fetch(event.request, { cache: 'no-store' }));
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        if (!response || response.status !== 200 || response.type !== 'basic') return response;
        const responseToCache = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseToCache));
        return response;
      })
      .catch(() => caches.match(event.request))
  );
});