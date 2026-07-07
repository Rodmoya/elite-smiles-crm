const CACHE_NAME = 'elite-smiles-kiosk-shell-v1';
const SHELL_ASSETS = [
  '/crm/patient-experience/kiosk/',
  '/crm/patient-experience/manifest.webmanifest',
  '/crm/patient-experience/sw.js',
  '/crm/assets/img/ES-Logo-Stack-500-x-150-px.png',
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.addAll(SHELL_ASSETS).catch(function () {
        return true;
      });
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.map(function (key) {
        if (key !== CACHE_NAME) {
          return caches.delete(key);
        }
        return Promise.resolve(true);
      }));
    }).then(function () {
      return self.clients.claim();
    })
  );
});

self.addEventListener('fetch', function (event) {
  const url = new URL(event.request.url);
  const isApi = url.pathname.indexOf('/app/api/patient_experience_kiosk.php') !== -1;
  if (event.request.method !== 'GET' || isApi || event.request.mode === 'navigate' && url.pathname.includes('/patient-experience/setup/')) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then(function (response) {
        if (url.origin === location.origin) {
          const copy = response.clone();
          caches.open(CACHE_NAME).then(function (cache) {
            cache.put(event.request, copy);
          });
        }
        return response;
      })
      .catch(function () {
        return caches.match(event.request).then(function (cached) {
          return cached || caches.match('/crm/patient-experience/kiosk/');
        });
      })
  );
});
