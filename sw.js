'use strict';

var CACHE_NAME = 'skytrufiber-static-v1';
var OFFLINE_URL = '/offline.html';
var PRECACHE_URLS = [OFFLINE_URL, '/pwa-icon.svg'];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function (cache) { return cache.addAll(PRECACHE_URLS); })
      .then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.filter(function (key) {
        return key.indexOf('skytrufiber-static-') === 0 && key !== CACHE_NAME;
      }).map(function (key) { return caches.delete(key); }));
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (event) {
  var request = event.request;
  if (request.method !== 'GET') return;

  var url;
  try { url = new URL(request.url); } catch (error) { return; }
  if (url.origin !== self.location.origin) return;

  // Never cache navigations, PHP responses, login pages, account pages,
  // API responses, sessions, or customer conversations.
  if (request.mode === 'navigate' || /\.php$/i.test(url.pathname) || url.pathname.indexOf('/fiber') === 0) {
    if (request.mode === 'navigate') {
      event.respondWith(fetch(request).catch(function () {
        return caches.match(OFFLINE_URL);
      }));
    }
    return;
  }

  // Only cache known static assets. HTML and data endpoints are excluded.
  if (!/\.(?:css|js|png|jpg|jpeg|webp|svg|woff2?)$/i.test(url.pathname)) return;

  event.respondWith(
    caches.match(request).then(function (cached) {
      var network = fetch(request).then(function (response) {
        if (response && response.ok && response.type === 'basic') {
          var copy = response.clone();
          caches.open(CACHE_NAME).then(function (cache) { cache.put(request, copy); });
        }
        return response;
      });
      return cached || network;
    })
  );
});

