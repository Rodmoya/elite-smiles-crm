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
  var badgeCount = 0;
  var payload = {};
  var options = {
    body: 'New CRM activity is ready for review.',
    icon: '/crm/assets/img/ES-Logo-Stack-500-x-150-px.png',
    badge: '/crm/assets/img/ES-Logo-Stack-500-x-150-px.png',
    tag: 'elite-ai-alert',
    renotify: true,
    data: { url: '/crm/mobile-ai?tab=notifications' }
  };

  if (event.data) {
    try {
      payload = event.data.json();
      title = payload.title || title;
      options.body = payload.push_body || payload.body || payload.assistant_summary || options.body;
      options.tag = payload.tag || options.tag;
      options.data = payload.data || {};
      options.data.url = options.data.url || payload.url || '/crm/mobile-ai?tab=notifications';
      badgeCount = Number(payload.badge_count || payload.unread_count || 0);
      if (payload.lead_id && !options.data.url.includes('lead_id=')) {
        options.data.url = '/crm/mobile-ai?tab=assistant&lead_id=' + encodeURIComponent(payload.lead_id);
        if (payload.notification_id) {
          options.data.url += '&notification_id=' + encodeURIComponent(payload.notification_id);
        }
      }
    } catch (error) {
      options.body = event.data.text() || options.body;
    }
  }

  var badgePromise = Promise.resolve();
  if (badgeCount > 0) {
    if (self.registration && self.registration.setAppBadge) {
      badgePromise = self.registration.setAppBadge(badgeCount);
    } else if (self.navigator && self.navigator.setAppBadge) {
      badgePromise = self.navigator.setAppBadge(badgeCount);
    }
  }

  var clientMessagePromise = clients.matchAll({ type: 'window', includeUncontrolled: true })
    .then(function (openClients) {
      return Promise.all(openClients.map(function (client) {
        client.postMessage({ type: 'elite-ai-notification', payload: payload });
      }));
    });

  event.waitUntil(Promise.all([
    self.registration.showNotification(title, options),
    badgePromise,
    clientMessagePromise
  ]));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var target = '/crm/mobile-ai?tab=notifications';

  if (event.notification && event.notification.data && event.notification.data.url) {
    target = event.notification.data.url;
  }

  event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (openClients) {
    var existing = openClients.find(function (client) {
      try {
        return new URL(client.url).pathname.indexOf('/crm/mobile-ai') !== -1;
      } catch (error) {
        return false;
      }
    });
    if (!existing) return clients.openWindow(target);
    existing.postMessage({ type: 'elite-ai-notification-opened', url: target });
    if (typeof existing.navigate === 'function') {
      return existing.navigate(target).then(function () { return existing.focus(); });
    }
    return existing.focus();
  }));
});
