const CACHE_NAME = "printease-v2";

const urlsToCache = [
  "/printease/",
  "/printease/index.php",
  "/printease/manifest.json",
  "/printease/assets/css/index.css",
  "/printease/assets/css/tailwind.css",
  "/printease/assets/images/printing-logo-192.png",
  "/printease/assets/images/printing-logo-512.png"
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
        return caches.match("/printease/index.php");
      });
    })
  );
});
