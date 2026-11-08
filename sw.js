const CACHE_NAME = 'shoppinglist-shell-v2';
const SHELL_ASSETS = [
  './js/main.js',
  './css/main.css',
  './icon.svg',
  './manifest.webmanifest',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(SHELL_ASSETS))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((key) => key.startsWith('shoppinglist-shell-') && key !== CACHE_NAME)
          .map((key) => caches.delete(key)),
      ))
      .then(() => self.clients.claim()),
  );
});

// Cache only versioned app-shell assets. Never cache Nextcloud pages, API
// responses, user data, or authenticated requests.
self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) return;

  const url = new URL(request.url);
  const isAppAsset = url.pathname.endsWith('/js/main.js')
    || url.pathname.endsWith('/css/main.css')
    || url.pathname.endsWith('/icon.svg')
    || url.pathname.endsWith('/manifest.webmanifest');
  if (!isAppAsset) return;

  event.respondWith(
    caches.match(request).then((cached) => cached || fetch(request)),
  );
});
