const CACHE_NAME = "printease-v3";

const BASE_PATH = self.location.pathname.replace(/\/[^\/]*$/, "/");

const urlsToCache = [
  BASE_PATH,
  BASE_PATH + "index.php",
  BASE_PATH + "manifest.json",
  BASE_PATH + "assets/css/index.css",
  BASE_PATH + "assets/css/tailwind.css",
  BASE_PATH + "assets/images/printing-logo-192.png",
  BASE_PATH + "assets/images/printing-logo-512.png"
];

// INSTALL
self.addEventListener("install", event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(urlsToCache);
    })
  );
});

// ACTIVATE
self.addEventListener("activate", event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.map(key => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
});

// FETCH (OFFLINE SUPPORT)
self.addEventListener("fetch", event => {
  event.respondWith(
    caches.match(event.request).then(response => {
      return response || fetch(event.request).catch(() => {
        return caches.match(BASE_PATH + "index.php");
      });
    })
  );
});
