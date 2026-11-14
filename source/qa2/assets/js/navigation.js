(()=>{
'use strict';
const {$,$$,iconSvg,lockScroll,unlockScroll}=window.SoknaCore;
const navPage=document.body.dataset.navPage||document.body.dataset.activePage||'today';
$$('[data-mobile-page]').forEach(link=>{const active=link.dataset.mobilePage===navPage;link.classList.toggle('active',active);active?link.setAttribute('aria-current','page'):link.removeAttribute('aria-current')});

/* Theme: user preference is device-local; "system" follows OS live. */
const THEME_KEY='sokna:theme';
const systemTheme=window.matchMedia('(prefers-color-scheme: dark)');
const getThemePref=()=>{try{return localStorage.getItem(THEME_KEY)||'system'}catch{return 'system'}};
const applyTheme=pref=>{const safe=['system','light','dark'].includes(pref)?pref:'system';const dark=safe==='dark'||(safe==='system'&&systemTheme.matches);document.documentElement.dataset.theme=dark?'dark':'light';document.documentElement.dataset.themePreference=safe;const themeMeta=document.querySelector('meta[name="theme-color"]');if(themeMeta)themeMeta.setAttribute('content',dark?'#171411':'#846554');const schemeMeta=document.querySelector('meta[name="color-scheme"]');if(schemeMeta)schemeMeta.setAttribute('content',dark?'dark':'light');$$('[data-theme-choice]').forEach(b=>{const active=b.dataset.themeChoice===safe;b.classList.toggle('active',active);b.setAttribute('aria-pressed',active?'true':'false')})};
const saveTheme=pref=>{try{localStorage.setItem(THEME_KEY,pref)}catch{}applyTheme(pref)};
$$('[data-theme-choice]').forEach(b=>b.addEventListener('click',()=>saveTheme(b.dataset.themeChoice||'system')));
if(systemTheme.addEventListener)systemTheme.addEventListener('change',()=>{if(getThemePref()==='system')applyTheme('system')});else systemTheme.addListener?.(()=>{if(getThemePref()==='system')applyTheme('system')});
applyTheme(getThemePref());



const mobileNavQuery=window.matchMedia('(max-width:860px)');
const navTriggers=$$('[data-nav-open]'),sidebar=$('#sidebar'),main=$('#mainContent'),mobileNav=$('.mobile-nav');
const visualViewport=window.visualViewport||null;
const editableSelector='input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]):not([type="reset"]):not([type="file"]):not([readonly]):not([disabled]),textarea:not([readonly]):not([disabled]),[contenteditable="true"]';
let stableViewportHeight=Math.max(document.documentElement.clientHeight||0,window.innerHeight||0,visualViewport?.height||0),softKeyboardOpen=false;
const activeEditable=()=>{const el=document.activeElement;return el?.matches?.(editableSelector)?el:null};
const setSoftKeyboardState=open=>{const next=Boolean(open&&mobileNavQuery.matches);if(next===softKeyboardOpen)return false;softKeyboardOpen=next;document.body.classList.toggle('soft-keyboard-open',next);return true};
const syncSoftKeyboard=()=>{const active=activeEditable(),currentLayout=Math.max(document.documentElement.clientHeight||0,window.innerHeight||0);if(!mobileNavQuery.matches||!active){setSoftKeyboardState(false);stableViewportHeight=Math.max(currentLayout,visualViewport?.height||0);return}const baseline=Math.max(stableViewportHeight,currentLayout),visible=visualViewport?.height||window.innerHeight||baseline,threshold=Math.max(120,Math.round(baseline*.18)),open=baseline-visible>threshold,changed=setSoftKeyboardState(open);if(open&&changed)requestAnimationFrame(()=>active.closest('.search-box,.field,.topbar-search-shell,.mobile-header-search-shell')?.scrollIntoView({block:'nearest',inline:'nearest'}))};
const scheduleSoftKeyboardSync=()=>requestAnimationFrame(syncSoftKeyboard);
document.addEventListener('focusin',e=>{if(e.target?.matches?.(editableSelector))setTimeout(scheduleSoftKeyboardSync,0)});document.addEventListener('focusout',()=>setTimeout(syncSoftKeyboard,80));visualViewport?.addEventListener('resize',scheduleSoftKeyboardSync);visualViewport?.addEventListener('scroll',scheduleSoftKeyboardSync);window.addEventListener('resize',scheduleSoftKeyboardSync);window.addEventListener('orientationchange',()=>{setSoftKeyboardState(false);setTimeout(()=>{stableViewportHeight=Math.max(document.documentElement.clientHeight||0,window.innerHeight||0,visualViewport?.height||0);syncSoftKeyboard()},120)});
let lastNavTrigger=null;const focusableSelector='a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';const drawerFocusables=()=>sidebar?[...sidebar.querySelectorAll(focusableSelector)].filter(el=>!el.hidden&&el.getClientRects().length>0):[];
const setBackgroundInert=open=>{if(main)main.inert=open;if(mobileNav)mobileNav.inert=open};const restoreTrigger=()=>{const t=lastNavTrigger;lastNavTrigger=null;if(t?.isConnected)requestAnimationFrame(()=>t.focus())};
const setNav=(requested,trigger=null,restoreFocus=true)=>{const open=Boolean(requested&&mobileNavQuery.matches),wasOpen=document.body.classList.contains('nav-open');if(open&&trigger)lastNavTrigger=trigger;document.body.classList.toggle('nav-open',open);open?lockScroll('navigation-drawer'):unlockScroll('navigation-drawer');navTriggers.forEach(b=>b.setAttribute('aria-expanded',open?'true':'false'));sidebar?.setAttribute('aria-hidden',open||!mobileNavQuery.matches?'false':'true');setBackgroundInert(open);if(open&&!wasOpen)requestAnimationFrame(()=>{(sidebar?.querySelector('[data-nav-close]')||drawerFocusables()[0])?.focus()});else if(!open&&wasOpen&&restoreFocus)restoreTrigger();else if(!open&&!restoreFocus)lastNavTrigger=null};
navTriggers.forEach(b=>b.addEventListener('click',()=>setNav(true,b)));$$('[data-nav-close]').forEach(b=>b.addEventListener('click',()=>setNav(false,null,true)));sidebar?.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>setNav(false,null,false)));

/* Desktop shell popovers are anchored to their trigger and mutually exclusive. */
const popovers=$$('[data-shell-popover]');
const closePopovers=(except=null)=>{popovers.forEach(p=>{if(p===except)return;p.hidden=true;const trigger=$(`[data-shell-popover-trigger="${p.id}"]`);trigger?.setAttribute('aria-expanded','false')})};
$$('[data-shell-popover-trigger]').forEach(trigger=>trigger.addEventListener('click',e=>{e.stopPropagation();const p=$(`#${CSS.escape(trigger.dataset.shellPopoverTrigger||'')}`);if(!p)return;const opening=p.hidden;closePopovers(p);p.hidden=!opening;trigger.setAttribute('aria-expanded',opening?'true':'false')}));
popovers.forEach(p=>p.addEventListener('click',e=>e.stopPropagation()));

/* Topbar inline quick search; no second dialog. */
let quickAbort=null,quickTimer=0;
const clearSearch=shell=>{const results=$('[data-quick-search-results]',shell);results?.replaceChildren();if(results)results.hidden=true};
const renderSearch=(shell,items)=>{const results=$('[data-quick-search-results]',shell);if(!results)return;results.replaceChildren();results.hidden=false;if(!items.length){const p=document.createElement('p');p.className='quick-search-empty';p.textContent='موردی پیدا نشد.';results.append(p);return}items.forEach(item=>{const a=document.createElement('a');a.className='quick-search-result';a.href=String(item.href||'#');a.setAttribute('role','option');const kind=document.createElement('span');kind.className='quick-search-result-kind';kind.textContent=item.kind==='reservation'?'رزرو':'مهمان';const text=document.createElement('span'),b=document.createElement('b'),small=document.createElement('small');b.textContent=String(item.title||'');small.textContent=String(item.meta||'');text.append(b,small);const arrow=document.createElement('span');arrow.className='quick-search-result-arrow';arrow.innerHTML=iconSvg('chevron-left');a.append(kind,text,arrow);results.append(a)})};
const runSearch=async(shell,value)=>{const q=value.trim(),endpoint=window.SOKNA?.quickSearchUrl||'';if(q.length<2||!endpoint){quickAbort?.abort();clearSearch(shell);return}quickAbort?.abort();quickAbort=new AbortController();try{const response=await fetch(endpoint+(endpoint.includes('?')?'&':'?')+'q='+encodeURIComponent(q),{credentials:'same-origin',headers:{Accept:'application/json'},signal:quickAbort.signal});if(!response.ok)throw new Error('search_failed');const data=await response.json();renderSearch(shell,Array.isArray(data.items)?data.items:[])}catch(error){if(error?.name==='AbortError')return;renderSearch(shell,[])}};
$$('[data-quick-search-shell]').forEach(shell=>{const input=$('[data-quick-search-input]',shell),results=$('[data-quick-search-results]',shell);input?.addEventListener('input',()=>{window.clearTimeout(quickTimer);quickTimer=window.setTimeout(()=>runSearch(shell,input.value),160)});input?.addEventListener('focus',()=>{if(input.value.trim().length>=2&&results?.children.length)results.hidden=false});shell.addEventListener('click',e=>e.stopPropagation())});

/* Date summary works by hover/focus and remains meaningful on tap. */
const summarizeDay=day=>{const host=day.closest('[data-shell-popover],.mobile-shell-sheet'),panel=$('[data-day-summary-panel]',host);if(!panel)return;const holiday=day.dataset.holidayName||'';panel.replaceChildren();const b=document.createElement('b');b.textContent=day.dataset.summaryDate||'';const s=document.createElement('span');s.textContent=`${day.dataset.summaryConfirmed||'۰'} قطعی · ${day.dataset.summaryHold||'۰'} در انتظار · ${day.dataset.summaryFree||'۰'} آزاد · ${day.dataset.summaryArrivals||'۰'} ورود · ${day.dataset.summaryDepartures||'۰'} خروج${holiday?' · '+holiday:''}`;panel.append(b,s)};
$$('[data-day-summary]').forEach(day=>{const jalali=day.dataset.calendarJalali||'',holiday=window.SoknaIranHolidays?.info?.(jalali)||null;if(holiday){day.classList.add('official-holiday');day.dataset.holidayName=holiday.label||'تعطیل رسمی';day.title=holiday.label||'تعطیل رسمی'}day.addEventListener('mouseenter',()=>summarizeDay(day));day.addEventListener('focus',()=>summarizeDay(day))});

/* Mobile sheets use the same real calendar/notification data. */
const mobileSheets=$$('[data-mobile-date-sheet],[data-mobile-notifications-sheet]');
const closeMobileSheets=()=>{mobileSheets.forEach(s=>s.hidden=true);document.body.classList.remove('mobile-shell-open');unlockScroll('mobile-shell')};
const openMobileSheet=sheet=>{if(!sheet)return;closePopovers();mobileSheets.forEach(s=>s.hidden=s!==sheet);sheet.hidden=false;document.body.classList.add('mobile-shell-open');lockScroll('mobile-shell');requestAnimationFrame(()=>sheet.querySelector('button,a,input')?.focus())};
$('[data-mobile-date-open]')?.addEventListener('click',()=>openMobileSheet($('[data-mobile-date-sheet]')));$('[data-mobile-notifications-open]')?.addEventListener('click',()=>openMobileSheet($('[data-mobile-notifications-sheet]')));$$('[data-mobile-shell-close]').forEach(b=>b.addEventListener('click',closeMobileSheets));

document.addEventListener('click',e=>{closePopovers();$$('[data-quick-search-shell]').forEach(shell=>{if(!shell.contains(e.target))clearSearch(shell)})});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closePopovers();closeMobileSheets();$$('[data-quick-search-shell]').forEach(clearSearch);if(document.body.classList.contains('nav-open')){e.preventDefault();setNav(false,null,true)}return}if(e.key==='/'&&!e.ctrlKey&&!e.metaKey&&!e.altKey&&!e.defaultPrevented&&!document.activeElement?.matches?.(editableSelector)){const input=$$('[data-quick-search-input]').find(el=>el.getClientRects().length>0);if(input){e.preventDefault();input.focus();input.select()}}});
document.addEventListener('keydown',e=>{if(!document.body.classList.contains('nav-open')||e.key!=='Tab')return;const items=drawerFocusables();if(!items.length){e.preventDefault();sidebar?.focus();return}const first=items[0],last=items.at(-1),active=document.activeElement;if(e.shiftKey&&(active===first||!sidebar?.contains(active))){e.preventDefault();last.focus()}else if(!e.shiftKey&&(active===last||!sidebar?.contains(active))){e.preventDefault();first.focus()}});
const syncSystemSubnav=()=>{if(!mobileNavQuery.matches)return;const active=document.querySelector('.system-subnav a.active');if(active)requestAnimationFrame(()=>active.scrollIntoView({block:'nearest',inline:'center'}))};
const syncNavMode=()=>{if(!mobileNavQuery.matches)setNav(false,null,false);else if(!document.body.classList.contains('nav-open')){sidebar?.setAttribute('aria-hidden','true');setBackgroundInert(false)}syncSystemSubnav()};
if(typeof mobileNavQuery.addEventListener==='function')mobileNavQuery.addEventListener('change',()=>{syncNavMode();syncSoftKeyboard();closeMobileSheets()});else mobileNavQuery.addListener?.(()=>{syncNavMode();syncSoftKeyboard();closeMobileSheets()});
/* A soft page replacement must close persistent shell surfaces BEFORE its body
 * classes are synchronized. Otherwise the new page can inherit an inert main,
 * hidden scrolling root and an open sheet with no matching body state. */
const preparePageSwap=()=>{
  setNav(false,null,false);
  closeMobileSheets();
  closePopovers();
  $$('[data-quick-search-shell]').forEach(clearSearch);
};
window.SoknaNavigation=Object.freeze({preparePageSwap});
syncNavMode();syncSoftKeyboard();
})();
