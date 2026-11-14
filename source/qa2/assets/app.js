(()=>{
'use strict';
const data=window.SOKNA||{};
/* Register service worker only on secure/localhost contexts. */
if('serviceWorker' in navigator&&(location.protocol==='https:'||location.hostname==='localhost'))window.addEventListener('load',()=>navigator.serviceWorker.register(`${data.baseUrl||'.'}/service-worker.js`).catch(()=>{}),{once:true});
})();
