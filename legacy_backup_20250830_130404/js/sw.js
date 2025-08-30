// Service Worker for Supermon-ng
const CACHE_NAME = 'supermon-ng-v1';
const STATIC_CACHE = 'supermon-ng-static-v1';
const DYNAMIC_CACHE = 'supermon-ng-dynamic-v1';

// Files to cache immediately
const STATIC_FILES = [
    '../',
    '../index.php',
    '../supermon-ng.css',
    './jquery.min.js',
    './jquery-ui.min.js',
    './sweetalert2.min.js',
    './sweetalert2.min.css',
    './sweetalert2-config.js',
    './utils.js',
    './auth.js',
    './app.js',
    './modern-styles.css',
    '../favicon.ico',
    '../allstarlink.jpg'
];

// Install event - cache static files
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                return cache.addAll(STATIC_FILES);
            })
            .catch(error => {
                // Cache installation failed silently
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
    );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Skip external requests
    if (url.origin !== location.origin) {
        return;
    }

    // Handle API requests differently
    if (url.pathname.includes('.php') && !url.pathname.includes('index.php')) {
        event.respondWith(handleApiRequest(request));
        return;
    }

    // Handle static files
    event.respondWith(handleStaticRequest(request));
});

async function handleStaticRequest(request) {
    try {
        // Try cache first
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }

        // Fallback to network
        const networkResponse = await fetch(request);
        
        // Cache successful responses
        if (networkResponse.ok) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, networkResponse.clone());
        }

        return networkResponse;
    } catch (error) {
        // Return offline page if available
        const offlineResponse = await caches.match('../offline.html');
        if (offlineResponse) {
            return offlineResponse;
        }
        
        // Return a simple offline message
        return new Response('Offline - Please check your connection', {
            status: 503,
            statusText: 'Service Unavailable',
            headers: { 'Content-Type': 'text/plain' }
        });
    }
}

async function handleApiRequest(request) {
    try {
        // Try network first for API requests
        const networkResponse = await fetch(request);
        
        // Cache successful responses
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        // Try cache as fallback
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return error response
        return new Response('Network error - Please try again', {
            status: 503,
            statusText: 'Service Unavailable',
            headers: { 'Content-Type': 'text/plain' }
        });
    }
}

// Background sync for offline actions
self.addEventListener('sync', event => {
    if (event.tag === 'background-sync') {
        event.waitUntil(doBackgroundSync());
    }
});

async function doBackgroundSync() {
    try {
        // Get pending requests from IndexedDB
        const pendingRequests = await getPendingRequests();
        
        for (const request of pendingRequests) {
            try {
                await fetch(request.url, request.options);
                await removePendingRequest(request.id);
            } catch (error) {
                // Background sync failed for request silently
            }
        }
    } catch (error) {
        // Background sync failed silently
    }
}

// IndexedDB for storing pending requests
const dbName = 'SupermonOfflineDB';
const dbVersion = 1;
const storeName = 'pendingRequests';

async function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(dbName, dbVersion);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains(storeName)) {
                db.createObjectStore(storeName, { keyPath: 'id', autoIncrement: true });
            }
        };
    });
}

async function addPendingRequest(request) {
    const db = await openDB();
    const transaction = db.transaction([storeName], 'readwrite');
    const store = transaction.objectStore(storeName);
    
    return store.add({
        url: request.url,
        options: {
            method: request.method,
            headers: Object.fromEntries(request.headers.entries()),
            body: await request.text()
        },
        timestamp: Date.now()
    });
}

async function getPendingRequests() {
    const db = await openDB();
    const transaction = db.transaction([storeName], 'readonly');
    const store = transaction.objectStore(storeName);
    
    return store.getAll();
}

async function removePendingRequest(id) {
    const db = await openDB();
    const transaction = db.transaction([storeName], 'readwrite');
    const store = transaction.objectStore(storeName);
    
    return store.delete(id);
}

// Push notifications (if needed in the future)
self.addEventListener('push', event => {
    const options = {
        body: event.data ? event.data.text() : 'New notification from Supermon-ng',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            {
                action: 'explore',
                title: 'View',
                icon: '/favicon.ico'
            },
            {
                action: 'close',
                title: 'Close',
                icon: '/favicon.ico'
            }
        ]
    };

    event.waitUntil(
        self.registration.showNotification('Supermon-ng', options)
    );
});

self.addEventListener('notificationclick', event => {
    event.notification.close();

    if (event.action === 'explore') {
        event.waitUntil(
            clients.openWindow('/')
        );
    }
}); 