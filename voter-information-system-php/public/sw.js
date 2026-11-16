/*
 * Service worker: makes the application installable as an app on Android / iPhone (PWA)
 * and shows a friendly page when there is no internet. Voter data is never stored on the phone:
 * pages always come from the server; only the app's own CSS / JS / fonts / icons are cached.
 */
const VERSION = 'vis-app-2026-11-17';
const SHELL = ['offline.html', 'js/offline.js', 'images/app/icon-192.png', 'manifest.webmanifest'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

const isStatic = (url) => /\/(css|js|vendor|fonts|images)\//.test(url.pathname) && url.origin === self.location.origin;

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (req.mode === 'navigate') {
    // pages (voter lists etc.): always from the network; offline page when there is no connection
    e.respondWith(fetch(req).catch(() => caches.match('offline.html', { ignoreSearch: true })));
    return;
  }
  if (isStatic(url)) {
    // app files: serve from cache, refresh in the background
    e.respondWith(caches.open(VERSION).then((c) => c.match(req).then((hit) => {
      const net = fetch(req).then((res) => { if (res.ok) c.put(req, res.clone()); return res; }).catch(() => hit);
      return hit || net;
    })));
  }
});
