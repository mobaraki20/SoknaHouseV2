'use strict';
const CACHE_NAME='sokna-v2-2.6.19-dev.25-qa2-static';
const STATIC_ASSETS=[
  './offline.html','./manifest.webmanifest',
  './assets/app.css','./assets/css/tokens.css','./assets/css/base.css','./assets/css/layout.css','./assets/css/components.css','./assets/css/pages.css',
  './assets/js/core.js','./assets/js/soft-navigation.js','./assets/js/navigation.js','./assets/js/overlays.js','./assets/js/feedback.js','./assets/js/forms.js','./assets/js/holidays-ir.js','./assets/js/public.js','./assets/js/date-picker.js','./assets/js/calendar.js','./assets/js/reservation.js','./assets/js/rooms.js','./assets/js/updates.js','./assets/app.js','./assets/voucher.css','./assets/voucher.js',
  './assets/brand/house-mark.png','./assets/brand/house-logo.png','./assets/icons/favicon-32.png','./assets/icons/favicon-48.png','./assets/icons/icon-192.png','./assets/icons/icon-512.png','./assets/icons/icon-maskable-192.png','./assets/icons/icon-maskable-512.png','./assets/icons/apple-touch-icon.png'
];

self.addEventListener('install',event=>{
  event.waitUntil(caches.open(CACHE_NAME).then(cache=>Promise.allSettled(STATIC_ASSETS.map(asset=>cache.add(new Request(asset,{cache:'reload'}))))).then(()=>self.skipWaiting()));
});

self.addEventListener('activate',event=>{
  event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>key!==CACHE_NAME).map(key=>caches.delete(key)))).then(()=>self.clients.claim()));
});

function isAllowedStatic(url){
  const path=url.pathname;
  return path.endsWith('/offline.html')||path.endsWith('/manifest.webmanifest')||path.includes('/assets/');
}

self.addEventListener('fetch',event=>{
  const request=event.request;
  if(request.method!=='GET')return;
  const url=new URL(request.url);
  if(url.origin!==self.location.origin)return;

  // Authenticated HTML/API remain network-only. A failed navigation gets only the neutral offline page.
  if(request.mode==='navigate'){
    event.respondWith(fetch(request,{cache:'no-store'}).catch(()=>caches.match('./offline.html')));
    return;
  }
  if(!isAllowedStatic(url))return;

  // Release assets are immutable within one CACHE_NAME. Serve them from the installed cache first.
  // asset_rev() query hashes and a new CACHE_NAME on every release provide cache invalidation.
  // This avoids forcing CSS/JS back through the network on every full-page panel navigation.
  event.respondWith(caches.open(CACHE_NAME).then(async cache=>{
    const cached=await cache.match(request,{ignoreSearch:true});
    if(cached)return cached;
    const response=await fetch(request,{cache:'no-store'});
    if(response.ok)cache.put(request,response.clone()).catch(()=>{});
    return response;
  }));
});
