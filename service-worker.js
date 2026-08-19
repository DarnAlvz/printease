const CACHE_VERSION = "v8";
const SHELL_CACHE = "printease-shell-" + CACHE_VERSION;
const RUNTIME_CACHE = "printease-runtime-" + CACHE_VERSION;

const BASE_PATH = self.location.pathname.replace(/\/[^\/]*$/, "/");

const urlsToCache = [
  BASE_PATH + "frontend/splash.php",
  BASE_PATH + "manifest.json",
  BASE_PATH + "assets/css/index.css",
  BASE_PATH + "assets/css/tailwind.css",
  BASE_PATH + "assets/js/pdf.min.js",
  BASE_PATH + "assets/js/pdf.worker.min.js",
  BASE_PATH + "assets/images/printing-logo-192.png",
  BASE_PATH + "assets/images/printing-logo-512.png",
  BASE_PATH + "assets/images/printing-logo-512-maskable.png",
  BASE_PATH + "assets/images/printing-logo.png"
];

// INSTALL
self.addEventListener("install", event => {
  event.waitUntil(
    caches.open(SHELL_CACHE).then(cache => {
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
          if (key !== SHELL_CACHE && key !== RUNTIME_CACHE) {
            return caches.delete(key);
          }
        })
      );
    })
  );
});

function isShellRequest(url) {
  return (
    url.pathname === BASE_PATH ||
    url.pathname === BASE_PATH + "index.php" ||
    url.pathname === BASE_PATH + "frontend/splash.php" ||
    url.pathname.indexOf(BASE_PATH + "assets/") === 0
  );
}

function isCacheable(response) {
  if (!response || response.status !== 200 || response.type !== "basic") return false;
  var contentType = response.headers.get("content-type") || "";
  if (contentType.indexOf("text/html") !== -1) return false;
  return true;
}

function cachePut(cacheName, request, response) {
  return caches.open(cacheName).then(cache =>
    cache.put(request, response).then(() => {
      if (cacheName !== RUNTIME_CACHE) return;
      return cache.keys().then(keys => {
        if (keys.length > 120) {
          return Promise.all(keys.slice(0, 20).map(key => cache.delete(key)));
        }
      });
    })
  );
}

// FETCH
// - Shell assets (splash, css, icons, pdf.js): cache-first with background refresh.
//   These live in their own cache and are never evicted, so pdf.js keeps working offline.
// - Pages and same-origin GETs: network-first, cached in a capped runtime cache and
//   served from there offline so pages like place_order.php remain usable offline.
self.addEventListener("fetch", event => {
  const request = event.request;
  if (request.method !== "GET") return;

  let url;
  try {
    url = new URL(request.url);
  } catch (err) {
    return;
  }
  if (url.origin !== self.location.origin) return;

  if (isShellRequest(url)) {
    event.respondWith(
      caches.open(SHELL_CACHE)
        .then(cache => cache.match(request))
        .then(cached => {
          const network = fetch(request)
            .then(response => {
              if (isCacheable(response)) {
                cachePut(SHELL_CACHE, request, response.clone());
              }
              return response;
            })
            .catch(() => cached);
          return cached || network;
        })
    );
    return;
  }

  event.respondWith(
    fetch(request)
      .then(response => {
        if (isCacheable(response)) {
          cachePut(RUNTIME_CACHE, request, response.clone());
        }
        return response;
      })
      .catch(() =>
        caches.open(RUNTIME_CACHE)
          .then(cache => cache.match(request))
          .then(cached => {
            if (cached) return cached;
            return caches.open(SHELL_CACHE).then(shell => shell.match(request));
          })
          .then(cached => {
            if (cached) return cached;
            if (request.mode === "navigate") {
              return caches.open(SHELL_CACHE).then(shell =>
                shell.match(BASE_PATH + "index.php")
              );
            }
            return new Response("", { status: 504, statusText: "Offline" });
          })
      )
  );
});
