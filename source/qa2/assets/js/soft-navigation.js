(()=>{
'use strict';
/*
 * Panel soft navigation: keep the current rendered document visible while the
 * next server-rendered page is fetched, then replace only the page content.
 * This removes browser-level blank/black document frames without adding fades.
 */
if(!document.body.classList.contains('app-page')||!window.fetch||!window.DOMParser)return;

const CONTENT_SELECTOR='.content-wrap';
const PAGE_MODULES=['calendar.js','reservation.js','rooms.js','updates.js'];
const HARD_PAGES=new Set(['voucher','report_export','public','core_hr']);
let requestController=null,serial=0,navigating=false;
try{history.scrollRestoration='manual'}catch(_){ }

const pageUrl=href=>{
  try{
    const url=new URL(href,location.href);
    if(url.origin!==location.origin)return null;
    if(!/\/index\.php$/i.test(url.pathname))return null;
    if(HARD_PAGES.has(url.searchParams.get('page')||''))return null;
    return url;
  }catch(_){return null}
};
const hardOnly=link=>link.hasAttribute('download')||link.hasAttribute('data-hard-navigation')||link.target&&link.target!=='_self'||link.relList?.contains('external');
const sameLocationExceptHash=url=>url.pathname===location.pathname&&url.search===location.search;
const rememberScroll=()=>{
  try{
    const current=history.state&&typeof history.state==='object'?{...history.state}:{};
    current.soknaShell={...(current.soknaShell||{}),scrollY:Math.max(0,Math.round(window.scrollY))};
    history.replaceState(current,'',location.href);
  }catch(_){ }
};
const shellState=(scrollY=0)=>({soknaShell:{scrollY:Math.max(0,Math.round(scrollY))}});
const focusHash=url=>{
  if(!url.hash)return false;
  try{const id=decodeURIComponent(url.hash.slice(1)),target=document.getElementById(id)||document.getElementsByName(id)[0];if(!target)return false;target.scrollIntoView({block:'start',inline:'nearest',behavior:'instant'});return true}catch(_){return false}
};
const extractPageData=doc=>{
  const script=[...doc.scripts].find(node=>!node.src&&node.textContent.includes('window.SOKNA='));
  if(!script)return null;
  const text=script.textContent||'',marker='window.SOKNA=',at=text.indexOf(marker);if(at<0)return null;
  let raw=text.slice(at+marker.length).trim().replace(/;\s*$/,'');
  try{return JSON.parse(raw)}catch(_){return null}
};
const updatePageData=next=>{
  if(!next||typeof next!=='object')return;
  const current=window.SOKNA&&typeof window.SOKNA==='object'?window.SOKNA:{};
  Object.keys(current).forEach(key=>delete current[key]);Object.assign(current,next);window.SOKNA=current;
};
const shellVersion=doc=>doc.querySelector('#sidebar .sidebar-version')?.textContent?.trim()||'';
const shellReleaseChanged=doc=>{
  const current=shellVersion(document),next=shellVersion(doc);
  return Boolean(current&&next&&current!==next);
};
const syncShellState=doc=>{
  const nextBody=doc.body,active=nextBody?.dataset.activePage||'',nav=nextBody?.dataset.navPage||active;
  document.body.className=nextBody?.className||`app-page page-${active}`;
  if(active)document.body.dataset.activePage=active;else delete document.body.dataset.activePage;
  if(nav)document.body.dataset.navPage=nav;else delete document.body.dataset.navPage;

  // Keep the persistent sidebar's version label synchronized with the server HTML.
  const incomingVersion=doc.querySelector('#sidebar .sidebar-version');
  const existingVersion=document.querySelector('#sidebar .sidebar-version');
  if(incomingVersion&&existingVersion){
    existingVersion.textContent=incomingVersion.textContent;
    existingVersion.title=incomingVersion.title;
  }
  const nextActiveSidebar=new Set([...doc.querySelectorAll('#sidebar .nav-link.active[data-sidebar-page]')].map(x=>x.dataset.sidebarPage));
  document.querySelectorAll('#sidebar .nav-link[data-sidebar-page]').forEach(link=>{
    const is=nextActiveSidebar.has(link.dataset.sidebarPage||'');link.classList.toggle('active',is);is?link.setAttribute('aria-current','page'):link.removeAttribute('aria-current');
  });
  const nextSettings=!!doc.querySelector('#sidebar .sidebar-settings.active');
  const settings=document.querySelector('#sidebar .sidebar-settings');if(settings){settings.classList.toggle('active',nextSettings);nextSettings?settings.setAttribute('aria-current','page'):settings.removeAttribute('aria-current')}
  document.querySelectorAll('[data-mobile-page]').forEach(link=>{const is=link.dataset.mobilePage===nav;link.classList.toggle('active',is);is?link.setAttribute('aria-current','page'):link.removeAttribute('aria-current')});
};
const refreshGenericUi=scope=>{
  window.SoknaForms?.refresh?.(scope);
  window.SoknaOverlay?.refresh?.(scope);
  window.SoknaDate?.refresh?.(scope);
  window.SoknaUI?.refresh?.(scope);
};
const rerunPageModules=async doc=>{
  const sources=[...doc.querySelectorAll('script[src]')].map(s=>s.src);
  for(const name of PAGE_MODULES){
    const src=sources.find(value=>{try{return new URL(value,location.href).pathname.endsWith(`/assets/js/${name}`)}catch(_){return false}});
    if(!src)continue;
    await new Promise(resolve=>{const script=document.createElement('script');script.src=src;script.async=false;script.dataset.soknaSoftReload=name;script.onload=script.onerror=()=>{script.remove();resolve()};document.body.append(script)});
  }
};
const commit=async(doc,url,{push=true,restoreY=null,requestId=null}={})=>{
  const next=doc.querySelector(CONTENT_SELECTOR),current=document.querySelector(CONTENT_SELECTOR);if(!next||!current)throw new Error('soft_nav_missing_content');
  /* Capture the outgoing scroll position before replacing the page subtree. */
  if(push)rememberScroll();
  /* Release shell/overlay scroll owners before copying the incoming body's
   * classes. A hidden drawer or open dialog must not survive on a new page. */
  window.SoknaNavigation?.preparePageSwap?.();
  window.SoknaOverlay?.preparePageSwap?.();
  updatePageData(extractPageData(doc));
  syncShellState(doc);
  const imported=document.importNode(next,true);current.replaceWith(imported);
  if(doc.title)document.title=doc.title;
  if(push)history.pushState(shellState(0),'',url.href)
  /* Set the new page's scroll position in the same task as DOM replacement.
   * Waiting for async page modules makes the incoming page visible at the outgoing
   * scroll offset, then suddenly jumps to the top once the modules finish. */
  const targetY=restoreY===null?0:Math.max(0,restoreY);
  const hasTarget=focusHash(url);
  if(!hasTarget)window.scrollTo({top:targetY,left:0,behavior:'instant'});
  refreshGenericUi(imported);
  /* A user can start scrolling a restored page while an asynchronous module is
   * loading. That input wins over the previously saved scroll position. */
  let userInteracted=false;
  let focusChanged=false;
  const markFocusChange=()=>{focusChanged=true};
  const markUserInteraction=()=>{userInteracted=true};
  const markScrollKey=event=>{
    if(['ArrowDown','ArrowUp','PageDown','PageUp','Home','End',' '].includes(event.key))userInteracted=true;
  };
  window.addEventListener('wheel',markUserInteraction,{passive:true});
  window.addEventListener('touchmove',markUserInteraction,{passive:true});
  window.addEventListener('pointerdown',markUserInteraction,{passive:true});
  window.addEventListener('keydown',markScrollKey);
  document.addEventListener('focusin',markFocusChange,true);
  try{
    await rerunPageModules(doc);
    await new Promise(resolve=>requestAnimationFrame(resolve));
    /* An older navigation must not override a newer page's scroll or focus. */
    if(requestId!==null&&requestId!==serial)return;
    /* Restore once for late-rendered modules, unless the user has already
     * chosen a different position on the current page. */
    if(!userInteracted){
      if(url.hash)focusHash(url);
      else if(restoreY!==null&&Math.abs(window.scrollY-targetY)>1)
        window.scrollTo({top:targetY,left:0,behavior:'instant'});
    }
    /* The user may already be typing in the new page while its asynchronous
     * modules load. Do not steal that focus (or close the mobile keyboard)
     * merely to complete the navigation's accessibility focus handoff. */
    const main=document.querySelector('#mainContent'),active=document.activeElement;
    if(!focusChanged&&!userInteracted&&!document.querySelector('dialog[open]')
      &&(!active||active===document.body||active===document.documentElement||!imported.contains(active)))
      main?.focus?.({preventScroll:true});
  }finally{
    window.removeEventListener('wheel',markUserInteraction);
    window.removeEventListener('touchmove',markUserInteraction);
    window.removeEventListener('pointerdown',markUserInteraction);
    window.removeEventListener('keydown',markScrollKey);
    document.removeEventListener('focusin',markFocusChange,true);
  }
};
const navigate=async(url,{push=true,restoreY=null}={})=>{
  if(navigating&&url.href===location.href)return true;
  const id=++serial;requestController?.abort();requestController=new AbortController();navigating=true;
  const main=document.querySelector('#mainContent');main?.setAttribute('aria-busy','true');
  try{
    const response=await fetch(url.href,{method:'GET',credentials:'same-origin',cache:'no-store',redirect:'follow',signal:requestController.signal,headers:{Accept:'text/html','X-Sokna-Soft-Navigation':'1'}});
    if(!response.ok)throw new Error(`soft_nav_http_${response.status}`);
    const type=(response.headers.get('content-type')||'').toLowerCase();if(!type.includes('text/html'))throw new Error('soft_nav_non_html');
    const html=await response.text();if(id!==serial)return true;
    const doc=new DOMParser().parseFromString(html,'text/html');
    if(!doc.body?.classList.contains('app-page')||!doc.querySelector(CONTENT_SELECTOR))throw new Error('soft_nav_incompatible_document');
    const finalUrl=pageUrl(response.url)||url;
    // A new release may have changed the executable JS/CSS. Do not retain old loaded
    // scripts or a stale sidebar after detecting a version change in the fetched HTML.
    if(shellReleaseChanged(doc)){location.assign(finalUrl.href);return true;}
    await commit(doc,finalUrl,{push,restoreY,requestId:id});
    return true;
  }catch(error){
    /* A superseded navigation must never trigger its click/form handler's
     * hard-navigation fallback. A newer request already owns the screen,
     * even if the older fetch rejects with a non-AbortError or bad response. */
    if(id!==serial||error?.name==='AbortError')return true;
    return false;
  }finally{if(id===serial){navigating=false;main?.removeAttribute('aria-busy')}}
};

try{const state=history.state&&typeof history.state==='object'?history.state:{};if(!state.soknaShell&&!state.soknaRooms)history.replaceState({...state,...shellState(window.scrollY)},'',location.href)}catch(_){ }
window.addEventListener('pagehide',rememberScroll);

document.addEventListener('click',async event=>{
  if(event.defaultPrevented||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;
  const link=event.target.closest?.('a[href]');if(!link||hardOnly(link))return;
  const url=pageUrl(link.href);if(!url)return;
  if(sameLocationExceptHash(url)){if(url.hash)return;event.preventDefault();return}
  event.preventDefault();
  const ok=await navigate(url,{push:true});if(!ok)location.assign(url.href);
});


document.addEventListener('submit',async event=>{
  if(event.defaultPrevented)return;
  const form=event.target;if(!(form instanceof HTMLFormElement)||String(form.method||'get').toLowerCase()!=='get'||form.hasAttribute('data-hard-navigation'))return;
  const action=pageUrl(form.action||location.href);if(!action)return;
  const data=event.submitter?new FormData(form,event.submitter):new FormData(form),url=new URL(action.href);url.search='';
  for(const [key,value] of data.entries())if(typeof value==='string')url.searchParams.append(key,value);
  event.preventDefault();const ok=await navigate(url,{push:true});if(!ok)location.assign(url.href);
});

window.addEventListener('popstate',async event=>{
  const url=pageUrl(location.href);if(!url)return;
  /* rooms.js owns back/forward while the current and destination are both its workspace. */
  if(document.querySelector('[data-rooms-v3]')&&event.state?.soknaRooms&&url.searchParams.get('page')==='rooms')return;
  const y=Number(event.state?.soknaShell?.scrollY),ok=await navigate(url,{push:false,restoreY:Number.isFinite(y)?y:0});if(!ok)location.reload();
});

window.SoknaSoftNavigation=Object.freeze({navigate:(href,options={})=>{const url=pageUrl(href);return url?navigate(url,options):Promise.resolve(false)},rememberScroll});
})();
