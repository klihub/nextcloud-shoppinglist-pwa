const CACHE_NAME = 'shoppinglist-shell-v4';
const SHELL_ASSETS = [
  './js/main.js',
  './css/main.css',
  './icon.svg',
  './manifest.webmanifest',
];

async function fetchAndCache(cache, url) {
  const request = new Request(url, { cache: 'reload', credentials: 'same-origin' });
  const response = await fetch(request);
  if (!response.ok) {
    throw new Error(`Failed to fetch app asset: ${url} (${response.status})`);
  }
  await cache.put(url, response.clone());
  return response;
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => Promise.all(
        SHELL_ASSETS.map((asset) => fetchAndCache(cache, new URL(asset, self.registration.scope))),
      ))
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

// Prefer the latest app shell from the network, retaining the last good
// response as an offline fallback. Do not intercept pages or API requests.
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
    (async () => {
      const cache = await caches.open(CACHE_NAME);
      try {
        return await fetchAndCache(cache, request);
      } catch (error) {
        const cached = await cache.match(request, { ignoreSearch: true });
        if (cached) return cached;
        throw error;
      }
    })(),
  );
});
