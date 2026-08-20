// Service worker Yayasan Al Mawaddah.
// Aturan main: HANYA asset statis yang disentuh. Request PHP dan navigasi
// dibiarkan lewat jaringan apa adanya -- kalau ikut di-cache, redirect login
// (header('Location: ...')) jadi rusak seperti versi sebelumnya.
// Semua path relatif terhadap lokasi file ini, jadi folder proyek boleh diganti nama.

const CACHE = 'almawaddah-v2';
const PRECACHE = [
  'assets/css/style.css',
  'assets/js/app.js',
  'assets/img/logo_almawaddah.png'
];
const STATIC = /\.(?:css|js|png|jpe?g|svg|webp|ico|woff2?)$/i;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(PRECACHE))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET' || req.mode === 'navigate') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin || !STATIC.test(url.pathname)) return;

  // network-first: file yang baru diubah langsung kepakai, cache cuma cadangan offline
  event.respondWith(
    fetch(req)
      .then((res) => {
        if (res.ok) {
          const copy = res.clone();
          caches.open(CACHE).then((cache) => cache.put(req, copy));
        }
        return res;
      })
      .catch(() => caches.match(req).then((hit) => hit || Response.error()))
  );
});
