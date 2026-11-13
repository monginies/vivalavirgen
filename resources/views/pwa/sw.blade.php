// Service worker de Viva la Virgen (generado por la app). Versión {{ $v = '1' }}
const CACHE = 'vlv-v{{ $v }}';
const OFFLINE = '/offline';

self.addEventListener('install', e => {
    e.waitUntil(caches.open(CACHE).then(c => c.addAll([OFFLINE, '/css/app.css?v=37', '/img/logo-96.png'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
    e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});

// Siempre de la red (los datos tienen que estar al día); sin conexión, página de aviso.
self.addEventListener('fetch', e => {
    if (e.request.mode === 'navigate') {
        e.respondWith(fetch(e.request).catch(() => caches.match(OFFLINE)));
    }
});

// Notificaciones push
self.addEventListener('push', e => {
    let data = {};
    try { data = e.data ? e.data.json() : {}; } catch (err) { data = {body: e.data ? e.data.text() : ''}; }
    e.waitUntil(self.registration.showNotification(data.title || 'Viva la Virgen', {
        body: data.body || '',
        icon: '/img/icon-192.png',
        badge: '/img/icon-maskable-192.png',
        tag: data.tag || undefined,
        data: {url: data.url || '/app/'},
    }));
});

self.addEventListener('notificationclick', e => {
    e.notification.close();
    const url = e.notification.data && e.notification.data.url ? e.notification.data.url : '/app/';
    e.waitUntil(self.clients.matchAll({type: 'window', includeUncontrolled: true}).then(list => {
        for (const c of list) { if (c.url.includes(url) && 'focus' in c) return c.focus(); }
        return self.clients.openWindow(url);
    }));
});
