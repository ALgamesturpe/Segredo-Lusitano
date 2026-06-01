// SEGREDO LUSITANO — Service Worker
const CACHE_NAME = 'segredo-lusitano-v2';

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll([
        './assets/images/logo.png',
        './assets/images/logo_icon.png',
        './assets/images/logo_icon_qr.png',
        './assets/images/favicon-32.png',
      ]))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(
        keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;
  if (!event.request.url.startsWith(self.location.origin)) return;

  const pathname = new URL(event.request.url).pathname;

  // Apenas cachear assets estáticos — nunca páginas PHP
  const isStatic = /\.(css|js|png|jpg|jpeg|webp|gif|svg|ico|woff2?)(\?.*)?$/.test(pathname);
  if (!isStatic) return;

  // Cache-first para assets estáticos
  event.respondWith(
    caches.match(event.request).then(cached => {
      if (cached) return cached;
      return fetch(event.request).then(response => {
        if (response && response.status === 200) {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        }
        return response;
      });
    })
  );
});
