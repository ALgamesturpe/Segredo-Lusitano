// SEGREDO LUSITANO — Service Worker
const CACHE_NAME = 'segredo-lusitano-v1';

// Activos estáticos pré-cacheados na instalação
const STATIC_ASSETS = [
  './assets/images/logo.png',
  './assets/images/logo_icon.png',
  './assets/images/favicon-32.png',
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(STATIC_ASSETS))
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
  // Ignorar pedidos cross-origin (CDN, fonts, leaflet)
  if (!event.request.url.startsWith(self.location.origin)) return;
  // Ignorar painel admin e APIs AJAX
  const pathname = new URL(event.request.url).pathname;
  if (pathname.includes('/admin/') || pathname.endsWith('_api.php') || pathname.endsWith('checkin.php')) return;

  // Estratégia: Network-first — se offline serve da cache
  event.respondWith(
    fetch(event.request)
      .then(response => {
        if (response && response.status === 200) {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        }
        return response;
      })
      .catch(() =>
        caches.match(event.request).then(cached =>
          cached || new Response(
            `<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8">
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <title>Sem ligação — Segredo Lusitano</title>
            <style>body{font-family:sans-serif;background:#1a3a2a;color:#f5efe6;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center;padding:2rem;}h2{color:#c9a84c;margin-bottom:.5rem;}p{opacity:.7;}</style>
            </head><body><div><h2>Sem ligação à internet</h2><p>Esta página não está disponível offline.<br>Liga-te à internet e tenta novamente.</p></div></body></html>`,
            { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
          )
        )
      )
  );
});
