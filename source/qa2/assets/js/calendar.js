(()=>{
'use strict';
try{window.__SoknaCalendarLifecycle?.abort()}catch(_){}
const lifecycle=new AbortController();window.__SoknaCalendarLifecycle=lifecycle;
const host=document.querySelector('[data-calendar-swipe]');
const desktop=document.querySelector('[data-calendar-wheel]');
if(!host&&!desktop)return;
const media=window.matchMedia('(max-width: 860px)');
const holidayProvider=window.SoknaIranHolidays||null;
const calendarToday=(host?.dataset.calendarToday||desktop?.dataset.calendarToday||'').trim();
let startX=0,startY=0,tracking=false;

function wheelDeltaPx(event,el){
  if(event.deltaMode===1)return event.deltaY*16;
  if(event.deltaMode===2)return event.deltaY*Math.max(el.clientWidth*.8,240);
  return event.deltaY;
}
function bindDesktopWheel(){
  if(!desktop)return;
  desktop.addEventListener('wheel',event=>{
    if(media.matches||event.ctrlKey||event.metaKey||event.shiftKey)return;
    if(Math.abs(event.deltaX)>Math.abs(event.deltaY)*.55)return; // native trackpad/shift-horizontal behavior wins
    const delta=wheelDeltaPx(event,desktop);if(Math.abs(delta)<1)return;
    if(desktop.scrollWidth<=desktop.clientWidth+1)return;
    const before=desktop.scrollLeft,rtl=getComputedStyle(desktop).direction==='rtl';
    desktop.scrollLeft=before+(rtl?-delta:delta);
    // Prevent vertical page scrolling only when the timeline actually moved. At either horizontal edge
    // scrollLeft remains unchanged, so the wheel is released to the page instead of trapping the pointer.
    if(Math.abs(desktop.scrollLeft-before)>.5)event.preventDefault();
  },{passive:false});
}
function isFriday(iso){
  if(!/^\d{4}-\d{2}-\d{2}$/.test(String(iso||'')))return false;
  return new Date(`${iso}T00:00:00Z`).getUTCDay()===5;
}
function ensureHolidayDot(el){
  if(el.querySelector(':scope > .calendar-holiday-dot'))return;
  const dot=document.createElement('i');dot.className='calendar-holiday-dot';dot.setAttribute('aria-hidden','true');el.append(dot);
}
function enrichLabel(el,official,friday,past=false){
  const visible=String(el.textContent||'').trim().replace(/\s+/g,' ');
  const base=el.dataset.calendarBaseLabel||el.getAttribute('aria-label')||el.getAttribute('title')||visible;
  if(!el.dataset.calendarBaseLabel)el.dataset.calendarBaseLabel=base;
  const extras=[];if(past)extras.push('تاریخ گذشته');if(friday&&!base.includes('جمعه'))extras.push('جمعه');if(official)extras.push(`تعطیل رسمی: ${official.label}`);
  if(extras.length){
    const label=[base,...extras].filter(Boolean).join(' · ');
    el.setAttribute('title',label);el.setAttribute('aria-label',label);
  }
}
function renderMobileHolidayContext(){
  const context=document.querySelector('[data-calendar-holiday-context]');if(!context)return;
  const selected=document.querySelector('.mobile-date-strip .active[data-calendar-jalali]');
  const official=selected?holidayProvider?.info?.(selected.dataset.calendarJalali||''):null;
  context.replaceChildren();context.hidden=!official;if(!official)return;
  const dot=document.createElement('i');dot.className='calendar-holiday-context-dot';dot.setAttribute('aria-hidden','true');
  const copy=document.createElement('span');const strong=document.createElement('b');strong.textContent='تعطیل رسمی';const small=document.createElement('small');small.textContent=official.label;copy.append(strong,small);context.append(dot,copy);
  context.setAttribute('aria-label',`تعطیل رسمی: ${official.label}`);
}
function applyHolidayContext(){
  const dayHeads=[...document.querySelectorAll('.calendar-date[data-calendar-iso],.mobile-date-strip a[data-calendar-iso]')];
  let hasSupportedYear=false;
  dayHeads.forEach(el=>{
    const iso=el.dataset.calendarIso||'',jalali=el.dataset.calendarJalali||'',friday=isFriday(iso),official=holidayProvider?.info?.(jalali)||null,past=Boolean(calendarToday&&iso<calendarToday);
    const year=Number((jalali.match(/^(\d{4})/)||[])[1]||0);if(year&&holidayProvider?.supportsYear?.(year))hasSupportedYear=true;
    el.classList.toggle('is-friday',friday);el.classList.toggle('is-official-holiday',Boolean(official));el.classList.toggle('is-past',past);
    if(official)ensureHolidayDot(el);enrichLabel(el,official,friday,past);
  });
  document.querySelectorAll('.calendar-cell[data-calendar-iso]').forEach(el=>{
    const iso=el.dataset.calendarIso||'',jalali=el.dataset.calendarJalali||'',friday=isFriday(iso),official=holidayProvider?.info?.(jalali)||null,past=Boolean(calendarToday&&iso<calendarToday);
    el.classList.toggle('is-friday',friday);el.classList.toggle('is-official-holiday',Boolean(official));el.classList.toggle('is-past',past);enrichLabel(el,official,friday,past);
  });
  const legend=document.querySelector('[data-calendar-holiday-legend]');if(legend)legend.hidden=!hasSupportedYear;
  renderMobileHolidayContext();
}

if(host&&media.matches){
  requestAnimationFrame(()=>host.querySelector('.mobile-date-strip .active')?.scrollIntoView({behavior:'instant',block:'nearest',inline:'center'}));
}
if(host){
  const dateInput=host.querySelector('[data-calendar-mobile-date]');host.querySelector('[data-open-date-picker]')?.addEventListener('click',()=>dateInput?.click());dateInput?.addEventListener('change',()=>{const value=window.SoknaDate?.jToIso?.(dateInput.value)||'';if(!value)return;const url=new URL(location.href);url.searchParams.set('page','calendar');url.searchParams.set('day',value);if(value<url.searchParams.get('start')||!url.searchParams.get('start'))url.searchParams.set('start',value);window.SoknaSoftNavigation?.navigate?.(url.toString()).then(ok=>{if(!ok)location.assign(url.toString())})});
  const go=(url,direction)=>{if(!url)return;const event=new CustomEvent('sokna:calendar-swipe',{bubbles:true,cancelable:true,detail:{url,direction}});if(host.dispatchEvent(event)){const soft=window.SoknaSoftNavigation?.navigate?.(url);if(soft)soft.then(ok=>{if(!ok)window.location.assign(url)});else window.location.assign(url)}};
  host.addEventListener('touchstart',e=>{if(!media.matches||e.touches.length!==1)return;if(e.target instanceof Element&&e.target.closest('.mobile-date-strip')){tracking=false;return}const t=e.touches[0];startX=t.clientX;startY=t.clientY;tracking=true},{passive:true});
  host.addEventListener('touchcancel',()=>{tracking=false},{passive:true});
  host.addEventListener('touchend',e=>{if(!tracking||!media.matches||e.changedTouches.length!==1){tracking=false;return}const t=e.changedTouches[0],dx=t.clientX-startX,dy=t.clientY-startY;tracking=false;if(Math.abs(dx)<56||Math.abs(dx)<=Math.abs(dy)*1.25)return;if(dx<0)go(host.dataset.nextUrl,'next');else go(host.dataset.prevUrl,'prev')},{passive:true});
}
bindDesktopWheel();applyHolidayContext();
})();

// dev6 — calendar reservation summary owner: desktop popover + mobile drawer.
(()=>{
  const signal=window.__SoknaCalendarLifecycle?.signal;
  const bookings=[...document.querySelectorAll('[data-calendar-booking]')];
  const pop=document.getElementById('calendarReservationPopover');
  const drawer=document.getElementById('calendarReservationDrawer');
  if(!bookings.length||(!pop&&!drawer))return;
  const desktop=window.matchMedia('(min-width:861px)');
  let active=null;
  const val=(el,key)=>el?.dataset?.[key]||'';
  const fill=(root,prefix,el)=>{
    if(!root||!el)return;
    const set=(suffix,value)=>{const node=root.querySelector(`[data-cal-${prefix}-${suffix}]`);if(node)node.textContent=value;};
    set('state',val(el,'state'));set('guest',val(el,'guest'));set('room',val(el,'room'));
    set('in',val(el,'checkin'));set('out',val(el,'checkout'));set('guests',`${val(el,'guests')} نفر`);
    const balance=val(el,'balance');set('balance',balance&&balance!=='۰'&&balance!=='0'?`${balance} تومان`:'تسویه');
    const note=root.querySelector(`[data-cal-${prefix}-note]`);if(note){note.textContent=val(el,'note');note.hidden=!val(el,'note');}
    const open=root.querySelector(`[data-cal-${prefix}-open]`);if(open)open.href=val(el,'href')||el.href||'#';
  };
  const closePop=()=>{if(!pop||pop.hidden)return;pop.hidden=true;active?.focus?.({preventScroll:true});active=null;};
  const positionPop=el=>{
    if(!pop||pop.hidden||!el)return;
    const r=el.getBoundingClientRect(),p=pop.getBoundingClientRect(),gap=8,pad=10;
    let top=r.bottom+gap,left=Math.min(Math.max(pad,r.right-p.width),window.innerWidth-p.width-pad);
    if(top+p.height>window.innerHeight-pad)top=Math.max(pad,r.top-p.height-gap);
    pop.style.top=`${Math.round(top)}px`;pop.style.left=`${Math.round(left)}px`;
  };
  const openPop=el=>{if(!pop)return;active=el;fill(pop,'pop',el);pop.hidden=false;requestAnimationFrame(()=>{positionPop(el);pop.querySelector('[data-cal-pop-close]')?.focus({preventScroll:true});});};
  const closeDrawer=()=>{if(drawer?.open)drawer.close();};
  const openDrawer=el=>{if(!drawer)return;active=el;fill(drawer,'drawer',el);if(typeof drawer.showModal==='function')drawer.showModal();else drawer.setAttribute('open','');};
  bookings.forEach(el=>el.addEventListener('click',event=>{
    if(event.defaultPrevented||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;
    event.preventDefault();desktop.matches?openPop(el):openDrawer(el);
  }));
  pop?.querySelector('[data-cal-pop-close]')?.addEventListener('click',closePop);
  drawer?.querySelector('[data-cal-drawer-close]')?.addEventListener('click',closeDrawer);
  drawer?.addEventListener('click',event=>{if(event.target===drawer)closeDrawer();});
  drawer?.addEventListener('close',()=>{active?.focus?.({preventScroll:true});active=null;});
  document.addEventListener('pointerdown',event=>{if(pop?.hidden!==false)return;if(event.target instanceof Element&&(pop.contains(event.target)||event.target.closest('[data-calendar-booking]')))return;closePop();},{signal});
  document.addEventListener('keydown',event=>{if(event.key!=='Escape')return;if(pop?.hidden===false){event.preventDefault();closePop();}else if(drawer?.open)closeDrawer();},{signal});
  window.addEventListener('resize',()=>{if(pop?.hidden===false&&active){if(desktop.matches)positionPop(active);else closePop();}if(drawer?.open&&desktop.matches)closeDrawer();},{passive:true,signal});
  window.addEventListener('scroll',()=>{if(pop?.hidden===false&&active)positionPop(active);},{passive:true,capture:true,signal});
})();
