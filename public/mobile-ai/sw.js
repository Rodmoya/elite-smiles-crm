self.addEventListener('install', function (event) {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function () {
  // Phase 1 keeps this worker intentionally minimal.
});

self.addEventListener('push', function (event) {
  var title = 'Elite AI';
  var options = {
    body: 'New CRM activity is ready for review.',
    icon: '/crm/assets/img/ES-Logo-Stack-500-x-150-px.png',
    badge: '/crm/assets/img/ES-Logo-Stack-500-x-150-px.png'
  };

  if (event.data) {
    try {
      var payload = event.data.json();
      title = payload.title || title;
      options.body = payload.body || options.body;
      options.data = payload.data || {};
    } catch (error) {
      options.body = event.data.text() || options.body;
    }
  }

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var target = '/crm/mobile-ai?tab=notifications';

  if (event.notification && event.notification.data && event.notification.data.url) {
    target = event.notification.data.url;
  }

  event.waitUntil(clients.openWindow(target));
});
