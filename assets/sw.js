/**
 * AdminBeautify Service Worker
 * 渐进式 Web 应用（PWA）支持
 *
 * 策略：
 *  - 插件静态资源（CSS / JS / 字体图标）→ Cache-First（离线可用）
 *  - 后台 HTML 页面                      → Network-First（优先最新，降级离线页）
 *  - Google Fonts 字体                   → StaleWhileRevalidate（后台静默更新）
 */

var CACHE_VERSION = 'ab-v2.1.20';
var STATIC_CACHE  = CACHE_VERSION + '-static';
var PAGE_CACHE    = CACHE_VERSION + '-pages';

/* 安装时预缓存的核心静态资源 */
var PRECACHE_URLS = [
    /* 会由 Plugin.php 在 sw 注册时通过 __SW_ASSETS__ 动态注入实际带版本号的 URL */
];

/* ------------------------------------------------------------------ */
/*  Install — 预缓存静态资源                                            */
/* ------------------------------------------------------------------ */
self.addEventListener('install', function (event) {
    self.skipWaiting();
    event.waitUntil(
        caches.open(STATIC_CACHE).then(function (cache) {
            if (PRECACHE_URLS.length) {
                return cache.addAll(PRECACHE_URLS);
            }
        })
    );
});

/* ------------------------------------------------------------------ */
/*  Activate — 清理旧版本缓存，并通知所有客户端 SW 已更新               */
/* ------------------------------------------------------------------ */
self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.filter(function (key) {
                    return key.startsWith('ab-') && key !== STATIC_CACHE && key !== PAGE_CACHE;
                }).map(function (key) {
                    return caches.delete(key);
                })
            );
        }).then(function () {
            return self.clients.claim();
        }).then(function () {
            /* 通知所有已打开的页面：新版本 SW 已激活，可以刷新 */
            return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
                clients.forEach(function (client) {
                    client.postMessage({ type: 'SW_UPDATED' });
                });
            });
        })
    );
});

/* ------------------------------------------------------------------ */
/*  Fetch — 请求拦截                                                    */
/* ------------------------------------------------------------------ */
self.addEventListener('fetch', function (event) {
    var req = event.request;
    var url = new URL(req.url);

    /* 只处理 GET 请求 */
    if (req.method !== 'GET') return;

    /* 登录页 / action 接口 — 始终走网络，不缓存（保证认证状态正确） */
    if (url.pathname.indexOf('login.php') !== -1 ||
        url.pathname.indexOf('action/') !== -1 ||
        url.search.indexOf('do=') !== -1) {
        /* 直接透传网络，不做任何缓存处理；网络失败时尝试回退缓存，缓存也无则返回 503 */
        event.respondWith(
            fetch(req).catch(function () {
                return caches.match(req).then(function (cached) {
                    return cached || new Response(
                        JSON.stringify({ code: 503, message: '网络不可用，请检查连接后重试' }),
                        { status: 503, statusText: 'Service Unavailable',
                          headers: { 'Content-Type': 'application/json' } }
                    );
                });
            })
        );
        return;
    }

    /* Google Fonts — StaleWhileRevalidate */
    if (url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com') {
        event.respondWith(staleWhileRevalidate(STATIC_CACHE, req));
        return;
    }

    /* 插件静态资源（CSS / JS）— Cache-First */
    if (url.pathname.indexOf('/AdminBeautify/assets/') !== -1) {
        event.respondWith(cacheFirst(STATIC_CACHE, req));
        return;
    }

    /* 后台 HTML 页面 — Network-First，网络失败时返回离线提示 */
    if (req.headers.get('accept') && req.headers.get('accept').indexOf('text/html') !== -1) {
        event.respondWith(networkFirst(PAGE_CACHE, req));
        return;
    }
});

/* ------------------------------------------------------------------ */
/*  缓存策略实现                                                        */
/* ------------------------------------------------------------------ */

/** Cache-First：先查缓存，缓存未命中则走网络并存入缓存 */
function cacheFirst(cacheName, request) {
    return caches.open(cacheName).then(function (cache) {
        return cache.match(request).then(function (cached) {
            if (cached) return cached;
            return fetch(request).then(function (response) {
                if (response && response.status === 200) {
                    cache.put(request, response.clone());
                }
                return response;
            });
        });
    });
}

/** Network-First：先走网络，失败时回退缓存 */
function networkFirst(cacheName, request) {
    return caches.open(cacheName).then(function (cache) {
        return fetch(request).then(function (response) {
            /* 只缓存 200 成功的响应；不缓存重定向到登录页的 302 */
            if (response && response.status === 200 && response.type !== 'opaqueredirect') {
                /* 跳过包含登录页的响应 */
                var respUrl = response.url || '';
                if (respUrl.indexOf('login.php') === -1) {
                    cache.put(request, response.clone());
                }
            }
            /* 服务端错误（5xx，含 Cloudflare 524）→ 优先回退缓存，避免暴露错误页 */
            if (response && response.status >= 500) {
                return cache.match(request).then(function (cached) {
                    return cached || response;
                });
            }
            return response;
        }).catch(function () {
            return cache.match(request).then(function (cached) {
                if (cached) return cached;
                /* 离线兜底：返回简单的离线提示页 */
                return new Response(
                    '<!DOCTYPE html><html><head><meta charset="utf-8"><title>离线</title>' +
                    '<meta name="viewport" content="width=device-width,initial-scale=1">' +
                    '<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;' +
                    'min-height:100vh;margin:0;background:#F3EDF7;color:#1C1B1F;}' +
                    '.box{text-align:center;padding:40px;background:#fff;border-radius:24px;' +
                    'box-shadow:0 4px 16px rgba(0,0,0,.1);max-width:360px;}' +
                    'h2{margin:16px 0 8px;font-size:1.4em;}p{color:#49454F;margin:0;font-size:.95em;}' +
                    'button{margin-top:24px;padding:12px 28px;border:none;border-radius:999px;' +
                    'background:#7D5260;color:#fff;font-size:1em;cursor:pointer;}' +
                    '</style></head><body>' +
                    '<div class="box"><svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#7D5260" stroke-width="1.5"><path d="M1 1l22 22M16.72 11.06A10.94 10.94 0 0 1 19 12.55M5 5a10.94 10.94 0 0 0-2.28 2M10.41 10.41a2 2 0 1 0 2.83 2.83"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/></svg>' +
                    '<h2>当前处于离线状态</h2><p>请检查网络连接后重试</p>' +
                    '<button onclick="location.reload()">重新加载</button></div></body></html>',
                    { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                );
            });
        });
    });
}

/** StaleWhileRevalidate：立即返回缓存，同时后台更新 */
function staleWhileRevalidate(cacheName, request) {
    return caches.open(cacheName).then(function (cache) {
        return cache.match(request).then(function (cached) {
            var fetchPromise = fetch(request).then(function (response) {
                if (response && response.status === 200) {
                    cache.put(request, response.clone());
                }
                return response;
            });
            return cached || fetchPromise;
        });
    });
}

/* ------------------------------------------------------------------ */
/*  接收来自页面的消息                                                   */
/* ------------------------------------------------------------------ */
self.addEventListener('message', function (event) {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    /* 清空所有缓存（供设置页"清除缓存"按钮调用），完成后通知所有客户端 */
    if (event.data && event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.keys().then(function (keys) {
                return Promise.all(keys.map(function (k) { return caches.delete(k); }));
            }).then(function () {
                return self.clients.matchAll({ type: 'window', includeUncontrolled: true });
            }).then(function (clients) {
                clients.forEach(function (c) {
                    c.postMessage({ type: 'CACHE_CLEARED' });
                });
            })
        );
    }
});
