// KyuubiSoft Service Worker for Push Notifications

const CACHE_NAME = 'kyuubisoft-v1';

// Install event
self.addEventListener('install', (event) => {
  console.log('[SW] Installing service worker...');
  self.skipWaiting();
});

// Activate event
self.addEventListener('activate', (event) => {
  console.log('[SW] Service worker activated');
  event.waitUntil(clients.claim());
});

// Push notification received
self.addEventListener('push', (event) => {
  console.log('[SW] Push notification received');

  let data = {
    title: 'KyuubiSoft',
    body: 'New notification',
    icon: '/icon-192.png',
    badge: '/badge.png',
    url: '/',
    data: {}
  };

  if (event.data) {
    try {
      data = { ...data, ...event.data.json() };
    } catch (e) {
      data.body = event.data.text();
    }
  }

  const options = {
    body: data.body,
    icon: data.icon || '/icon-192.png',
    badge: data.badge || '/badge.png',
    vibrate: [100, 50, 100],
    data: {
      url: data.url || '/',
      notificationId: data.data?.notificationId,
      ...data.data
    },
    actions: [
      { action: 'open', title: 'Open' },
      { action: 'dismiss', title: 'Dismiss' }
    ],
    requireInteraction: false,
    tag: data.tag || 'default',
    renotify: true,
    timestamp: data.timestamp || Date.now()
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
  console.log('[SW] Notification clicked:', event.action);

  event.notification.close();

  if (event.action === 'dismiss') {
    return;
  }

  const url = event.notification.data?.url || '/';
  const notificationId = event.notification.data?.notificationId;

  event.waitUntil(
    (async () => {
      // Mark notification as clicked if we have an ID
      if (notificationId) {
        try {
          const token = await getAuthToken();
          if (token) {
            fetch(`/api/v1/push/history/${notificationId}/clicked`, {
              method: 'POST',
              headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
              }
            }).catch(console.error);
          }
        } catch (e) {
          console.error('Failed to mark notification as clicked:', e);
        }
      }

      // Try to focus existing window or open new one
      const windowClients = await clients.matchAll({
        type: 'window',
        includeUncontrolled: true
      });

      for (const client of windowClients) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          await client.focus();
          if (url && url !== '/') {
            client.navigate(url);
          }
          return;
        }
      }

      // Open new window if no existing window found
      if (clients.openWindow) {
        await clients.openWindow(url);
      }
    })()
  );
});

// Notification close handler
self.addEventListener('notificationclose', (event) => {
  console.log('[SW] Notification closed');
});

// Get auth token from IndexedDB
async function getAuthToken() {
  return new Promise((resolve) => {
    try {
      const request = indexedDB.open('kyuubisoft', 1);
      request.onerror = () => resolve(null);
      request.onsuccess = () => {
        try {
          const db = request.result;
          const tx = db.transaction('auth', 'readonly');
          const store = tx.objectStore('auth');
          const getRequest = store.get('token');
          getRequest.onsuccess = () => resolve(getRequest.result?.value || null);
          getRequest.onerror = () => resolve(null);
        } catch (e) {
          resolve(null);
        }
      };
    } catch (e) {
      resolve(null);
    }
  });
}

// Background sync (for offline support)
self.addEventListener('sync', (event) => {
  console.log('[SW] Background sync:', event.tag);
});

// Periodic background sync (for checking updates)
self.addEventListener('periodicsync', (event) => {
  console.log('[SW] Periodic sync:', event.tag);
});
