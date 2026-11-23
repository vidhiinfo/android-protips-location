/*
 * Service worker: makes the application installable as an app on Android / iPhone (PWA)
 * and shows a friendly page when there is no internet. Pages always come from the server; only the app's own
 * CSS / JS / fonts / icons are cached, plus the empty "Offline survey" page so it opens without internet.
 * Voter data is kept on the phone only when a worker downloads a booth there (IndexedDB, removed after a few days / on logout).
 */
const VERSION = 'vis-app-2026-12-05';
const SHELL = ['offline.html', 'js/offline.js', 'js/offline-survey.js', 'js/field-offline.js', 'images/app/icon-192.png', 'manifest.webmanifest'];
const SURVEY_PAGE = 'survey-offline-page';
const pageKey = (url) => (/\/field$/.test(url.pathname) ? 'field-offline-page' : SURVEY_PAGE);

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
  if (req.mode === 'navigate' && /\/(survey\/offline|field)$/.test(url.pathname)) {
    // offline survey page: from the network when possible, else the copy kept from the last visit
    e.respondWith(fetch(req).then((res) => {
      if (res.ok && !res.redirected) { const copy = res.clone(); caches.open(VERSION).then((c) => c.put(pageKey(url), copy)); }
      return res;
    }).catch(() => caches.open(VERSION).then((c) => c.match(pageKey(url))).then((hit) => hit || caches.match('offline.html', { ignoreSearch: true }))));
    return;
  }
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
