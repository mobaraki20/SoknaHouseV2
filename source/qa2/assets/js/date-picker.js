(()=>{
'use strict';
const {data,$,fa,en,iconSvg}=window.SoknaCore;
const holidayProvider=window.SoknaIranHolidays||null;
/* Jalali date conversion and central date dialog. */
function div(a,b){return Math.trunc(a/b)}function mod(a,b){return a-div(a,b)*b}
function jalCal(jy){const breaks=[-61,9,38,199,426,686,756,818,1111,1181,1210,1635,2060,2097,2192,2262,2324,2394,2456,3178];let gy=jy+621,leapJ=-14,jp=breaks[0],jump=0,jm=0;if(jy<jp||jy>=breaks[breaks.length-1])throw Error('year');for(let i=1;i<breaks.length;i++){jm=breaks[i];jump=jm-jp;if(jy<jm)break;leapJ+=div(jump,33)*8+div(mod(jump,33),4);jp=jm}let n=jy-jp;leapJ+=div(n,33)*8+div(mod(n,33)+3,4);if(mod(jump,33)===4&&jump-n===4)leapJ++;const leapG=div(gy,4)-div((div(gy,100)+1)*3,4)-150,march=20+leapJ-leapG;if(jump-n<6)n=n-jump+div(jump+4,33)*33;let leap=mod(mod(n+1,33)-1,4);if(leap===-1)leap=4;return{leap,gy,march}}
function g2d(gy,gm,gd){let d=div((gy+div(gm-8,6)+100100)*1461,4)+div(153*mod(gm+9,12)+2,5)+gd-34840408;return d-div(div(gy+100100+div(gm-8,6),100)*3,4)+752}
function d2g(jdn){let j=4*jdn+139361631;j=j+div(div(4*jdn+183187720,146097)*3,4)*4-3908;const i=div(mod(j,1461),4)*5+308,gd=div(mod(i,153),5)+1,gm=mod(div(i,153),12)+1,gy=div(j,1461)-100100+div(8-gm,6);return{gy,gm,gd}}
function j2g(jy,jm,jd){const r=jalCal(jy),jdn=g2d(r.gy,3,r.march)+((jm-1)*31)-div(jm,7)*(jm-7)+jd-1;return d2g(jdn)}
function d2j(jdn){const gy=d2g(jdn).gy;let jy=gy-621,r=jalCal(jy),jdn1f=g2d(gy,3,r.march),k=jdn-jdn1f;if(k>=0){if(k<=185)return{jy,jm:1+div(k,31),jd:mod(k,31)+1};k-=186}else{jy--;k+=179;if(r.leap===1)k++}return{jy,jm:7+div(k,30),jd:mod(k,30)+1}}
function g2j(gy,gm,gd){return d2j(g2d(gy,gm,gd))}
function parseJ(s){const m=en(s).trim().match(/^(\d{4})[\-/](\d{1,2})[\-/](\d{1,2})$/);return m?{jy:+m[1],jm:+m[2],jd:+m[3]}:null}
function formatJ(j){return `${j.jy}/${String(j.jm).padStart(2,'0')}/${String(j.jd).padStart(2,'0')}`}
function jToIso(s){const j=parseJ(s);if(!j)return'';try{const g=j2g(j.jy,j.jm,j.jd);return `${g.gy}-${String(g.gm).padStart(2,'0')}-${String(g.gd).padStart(2,'0')}`}catch(_){return''}}
function isoToJ(iso){const m=String(iso).match(/^(\d{4})-(\d{2})-(\d{2})$/);if(!m)return'';return formatJ(g2j(+m[1],+m[2],+m[3]))}
function todayJ(){const configured=isoToJ(String(data.today||''));if(configured){const j=parseJ(configured);if(j)return j}const n=new Date();return g2j(n.getFullYear(),n.getMonth()+1,n.getDate())}
function monthLen(y,m){if(m<=6)return 31;if(m<=11)return 30;return jalCal(y).leap===0?30:29}
function addDaysJ(value,n){const iso=jToIso(value);if(!iso)return value;const d=new Date(`${iso}T00:00:00Z`);d.setUTCDate(d.getUTCDate()+n);return isoToJ(d.toISOString().slice(0,10))}
const months=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
const week=[['ش','شنبه'],['ی','یکشنبه'],['د','دوشنبه'],['س','سه‌شنبه'],['چ','چهارشنبه'],['پ','پنجشنبه'],['ج','جمعه']];
let dateDialog=null,dateInput=null,dateYear=0,dateMonth=0,swipeStartX=null;
const closeDate=(value='cancel')=>window.SoknaOverlay?.closeModal?.(dateDialog,value);
const chevronRight='<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7"/></svg>';
const chevronLeft='<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7"/></svg>';
function weekdayIndex(j){const g=j2g(j.jy,j.jm,j.jd);return new Date(Date.UTC(g.gy,g.gm-1,g.gd)).getUTCDay()}
function holidayInfo(j){const raw=formatJ(j),official=holidayProvider?.info?.(raw)||null,isFriday=weekdayIndex(j)===5;return {official,isFriday}}
function isoForJ(j){const g=j2g(j.jy,j.jm,j.jd);return `${g.gy}-${String(g.gm).padStart(2,'0')}-${String(g.gd).padStart(2,'0')}`}
function shortJalali(iso){const j=parseJ(isoToJ(String(iso||'')));return j?`${fa(j.jd)} ${months[j.jm-1]}`:''}

function ensureDateDialog(){
  if(dateDialog)return dateDialog;
  dateDialog=document.createElement('dialog');
  dateDialog.className='date-dialog';
  dateDialog.setAttribute('aria-labelledby','dateDialogTitle');
  dateDialog.innerHTML=`<div class="date-picker"><header class="overlay-head date-picker-dialog-head"><div><span class="eyebrow">تاریخ</span><h2 id="dateDialogTitle">انتخاب تاریخ</h2></div><button class="icon-btn dialog-close date-dialog-close" type="button" data-date-close aria-label="بستن انتخاب تاریخ">${iconSvg('close')}</button></header><div class="date-picker-body"><div class="date-picker-head"><button class="icon-btn date-nav date-nav-prev" type="button" data-date-prev aria-label="ماه قبل">${chevronRight}</button><strong id="dateMonthTitle" data-date-title aria-live="polite"></strong><button class="icon-btn date-nav date-nav-next" type="button" data-date-next aria-label="ماه بعد">${chevronLeft}</button></div><div class="date-picker-week">${week.map(([x,label],index)=>`<span class="${index===6?'is-friday':''}" aria-label="${label}">${x}</span>`).join('')}</div><div class="date-picker-days" data-date-days role="grid" aria-labelledby="dateMonthTitle"></div><div class="date-picker-detail" data-date-detail hidden role="status" aria-live="polite"><b data-date-detail-title></b><span data-date-detail-copy></span><small data-date-detail-meta></small></div><div class="date-picker-footer"><div class="date-picker-legends"><span class="date-picker-legend" data-date-holiday-legend aria-label="راهنمای تقویم"><i aria-hidden="true"></i> تعطیل رسمی</span><span class="date-picker-legend availability" data-date-availability-legend hidden><i aria-hidden="true"></i> رزرو / خارج از سرویس</span></div><div class="date-picker-footer-actions"><button class="btn small date-today" type="button" data-date-today>امروز</button></div></div></div></div>`;
  document.body.append(dateDialog);
  $('[data-date-prev]',dateDialog).onclick=()=>shiftMonth(-1,true);
  $('[data-date-next]',dateDialog).onclick=()=>shiftMonth(1,true);
  $('[data-date-close]',dateDialog).onclick=()=>closeDate('cancel');
  $('[data-date-today]',dateDialog).onclick=()=>{const j=todayJ();dateYear=j.jy;dateMonth=j.jm;activateDate(j.jd)};
  const days=$('[data-date-days]',dateDialog);
  days.addEventListener('keydown',handleGridKeydown);
  days.addEventListener('pointerdown',e=>{if(e.pointerType==='mouse')return;swipeStartX=e.clientX});
  days.addEventListener('pointerup',e=>{if(swipeStartX===null)return;const dx=e.clientX-swipeStartX;swipeStartX=null;if(Math.abs(dx)<42)return;shiftMonth(dx<0?1:-1,true)});
  days.addEventListener('pointercancel',()=>{swipeStartX=null});
  dateDialog.addEventListener('click',e=>{if(e.target===dateDialog)(closeDate('cancel'))});
  dateDialog.addEventListener('cancel',e=>{e.preventDefault();closeDate('cancel')});
  dateDialog.addEventListener('close',()=>{const input=dateInput;dateInput=null;if(input?.isConnected){input.setAttribute('aria-expanded','false');requestAnimationFrame(()=>input.focus({preventScroll:true}))}});
  return dateDialog;
}
function visibleFocusDay(){const active=dateDialog?.querySelector('[data-date-day]:focus');return active?Number(active.dataset.dateDay):null}
function shiftMonth(n,focus=false){const preserve=visibleFocusDay()||parseJ(dateInput?.value||'')?.jd||1;dateMonth+=n;if(dateMonth<1){dateMonth=12;dateYear--}if(dateMonth>12){dateMonth=1;dateYear++}const day=Math.min(preserve,monthLen(dateYear,dateMonth));renderDate(day);if(focus)focusDay(day)}
function operationalMeta(j){
  const iso=isoForJ(j),meta=dateInput?._soknaAvailability||null;if(!meta||!Array.isArray(meta.ranges))return null;
  const matches=meta.ranges.filter(r=>String(r.start||'')<=iso&&iso<String(r.end||''));if(!matches.length)return null;
  const rank={reservation:3,block:2,lifecycle:1};matches.sort((a,b)=>(rank[b.type]||0)-(rank[a.type]||0));const range=matches[0],mode=meta.mode||'checkin';
  const boundaryAllowed=mode==='checkout'&&iso===String(range.start||'');
  return {iso,range,boundaryAllowed,disabled:!boundaryAllowed};
}
function baseDisabled(j){const iso=isoForJ(j);return Boolean((dateInput?.dataset.minIso&&iso<dateInput.dataset.minIso)||(dateInput?.dataset.maxIso&&iso>dateInput.dataset.maxIso))}
function dateIsDisabled(j){if(baseDisabled(j))return true;return operationalMeta(j)?.disabled===true}
function hideDetail(){const panel=$('[data-date-detail]',ensureDateDialog());if(panel)panel.hidden=true}
function showDetail(j,op=null){
  const d=ensureDateDialog(),panel=$('[data-date-detail]',d),title=$('[data-date-detail-title]',d),copy=$('[data-date-detail-copy]',d),meta=$('[data-date-detail-meta]',d);if(!panel||!title||!copy||!meta)return;
  panel.hidden=false;const dayLabel=`${fa(j.jd)} ${months[j.jm-1]} ${fa(j.jy)}`;
  if(baseDisabled(j)){title.textContent=dayLabel;copy.textContent='این تاریخ خارج از بازه قابل انتخاب است.';meta.textContent='';return}
  if(!op){title.textContent=dayLabel;copy.textContent='این تاریخ قابل انتخاب است.';meta.textContent='';return}
  const r=op.range||{},rangeLabel=[shortJalali(r.start),shortJalali(r.end)].filter(Boolean).join(' تا ');
  if(r.type==='reservation'){
    title.textContent=r.guest_name?`${r.label} — ${r.guest_name}`:r.label;
    copy.textContent=rangeLabel?`اقامت: ${rangeLabel}`:dayLabel;
    meta.textContent=[r.reservation_code?`رزرو ${fa(r.reservation_code)}`:'',op.boundaryAllowed?'این تاریخ برای خروج در مرز رزرو قابل انتخاب است.':'این روز برای رزرو جدید قابل انتخاب نیست.'].filter(Boolean).join(' · ');
  }else if(r.type==='block'){
    title.textContent='اتاق خارج از سرویس است';copy.textContent=r.label||'خارج از سرویس';meta.textContent=rangeLabel?`بازه: ${rangeLabel}`:'';
  }else{
    title.textContent='اتاق در این تاریخ قابل رزرو نیست';copy.textContent=r.label||'خارج از چرخه فعالیت';meta.textContent=rangeLabel?`بازه: ${rangeLabel}`:'';
  }
}
function renderDate(preferredDay=null){
  const d=ensureDateDialog(),selected=parseJ(dateInput?.value||''),today=todayJ(),todayIso=isoForJ(today);hideDetail();
  $('[data-date-title]',d).textContent=`${months[dateMonth-1]} ${fa(dateYear)}`;
  const legend=$('[data-date-holiday-legend]',d);if(legend)legend.hidden=!(holidayProvider?.supportsYear?.(dateYear));const availabilityLegend=$('[data-date-availability-legend]',d);if(availabilityLegend)availabilityLegend.hidden=!(dateInput?._soknaAvailability?.ranges?.length);
  const host=$('[data-date-days]',d);host.innerHTML='';
  const fg=j2g(dateYear,dateMonth,1),first=(new Date(Date.UTC(fg.gy,fg.gm-1,fg.gd)).getUTCDay()+1)%7;
  for(let i=0;i<first;i++){const x=document.createElement('span');x.setAttribute('aria-hidden','true');host.append(x)}
  let fallback=null;
  for(let day=1;day<=monthLen(dateYear,dateMonth);day++){
    const j={jy:dateYear,jm:dateMonth,jd:day},meta=holidayInfo(j),b=document.createElement('button'),iso=isoForJ(j),isPast=iso<todayIso,op=operationalMeta(j),disabled=dateIsDisabled(j);
    b.type='button';b.dataset.dateDay=String(day);b.setAttribute('role','gridcell');b.tabIndex=-1;
    const number=document.createElement('span');number.className='date-day-number';number.textContent=fa(day);b.append(number);
    if(isPast)b.classList.add('is-past');if(meta.isFriday)b.classList.add('is-friday');
    if(meta.official){b.classList.add('is-official-holiday');b.dataset.holiday=meta.official.label;b.title=meta.official.label;const dot=document.createElement('i');dot.className='date-holiday-dot';dot.setAttribute('aria-hidden','true');b.append(dot)}
    if(op){const stateClass={reservation:'is-reservation',block:'is-block',lifecycle:'is-lifecycle'}[op.range.type]||'is-unavailable';b.classList.add('is-room-unavailable',stateClass);if(op.boundaryAllowed)b.classList.add('is-boundary-allowed');const dot=document.createElement('i');dot.className='date-availability-dot';dot.setAttribute('aria-hidden','true');b.append(dot)}
    const labelParts=[`${fa(day)} ${months[dateMonth-1]} ${fa(dateYear)}`];if(isPast)labelParts.push('تاریخ گذشته');if(meta.isFriday)labelParts.push('جمعه');if(meta.official)labelParts.push(`تعطیل رسمی: ${meta.official.label}`);if(op){labelParts.push(op.range.label);if(op.range.guest_name)labelParts.push(`مهمان: ${op.range.guest_name}`);if(op.boundaryAllowed)labelParts.push('خروج در شروع این بازه مجاز است')};b.setAttribute('aria-label',labelParts.join('، '));
    if(selected&&selected.jy===dateYear&&selected.jm===dateMonth&&selected.jd===day)b.classList.add('selected');if(today.jy===dateYear&&today.jm===dateMonth&&today.jd===day)b.classList.add('today');
    if(disabled){b.classList.add('is-disabled');b.setAttribute('aria-disabled','true');b.onclick=()=>showDetail(j,op)}else{b.onclick=()=>chooseDate(day);if(!fallback)fallback=b}
    host.append(b);
  }
  while(host.children.length<42){const x=document.createElement('span');x.setAttribute('aria-hidden','true');host.append(x)}
  const target=host.querySelector(`[data-date-day="${preferredDay||selected?.jd||today.jd}"]`)||host.querySelector('.selected')||host.querySelector('.today')||fallback||host.querySelector('button');if(target)target.tabIndex=0;
}
function focusDay(day){requestAnimationFrame(()=>{const host=$('[data-date-days]',ensureDateDialog()),b=host?.querySelector(`[data-date-day="${day}"]`);(b||host?.querySelector('button'))?.focus()})}
function moveFocusByDays(delta){const active=dateDialog?.querySelector('[data-date-day]:focus');if(!active)return;const raw=formatJ({jy:dateYear,jm:dateMonth,jd:Number(active.dataset.dateDay)}),next=parseJ(addDaysJ(raw,delta));if(!next)return;dateYear=next.jy;dateMonth=next.jm;renderDate(next.jd);focusDay(next.jd)}
function activateDate(day){const j={jy:dateYear,jm:dateMonth,jd:day},op=operationalMeta(j);if(dateIsDisabled(j)){showDetail(j,op);return}chooseDate(day)}
function handleGridKeydown(e){
  const active=e.target.closest?.('[data-date-day]');if(!active)return;let handled=true;
  if(e.key==='ArrowLeft')moveFocusByDays(1);else if(e.key==='ArrowRight')moveFocusByDays(-1);else if(e.key==='ArrowUp')moveFocusByDays(-7);else if(e.key==='ArrowDown')moveFocusByDays(7);
  else if(e.key==='Home'){const cells=[...e.currentTarget.children],index=cells.indexOf(active);moveFocusByDays(-(index%7))}
  else if(e.key==='End'){const cells=[...e.currentTarget.children],index=cells.indexOf(active);moveFocusByDays(6-(index%7))}
  else if(e.key==='PageUp'){if(e.shiftKey){dateYear--;const day=Math.min(Number(active.dataset.dateDay),monthLen(dateYear,dateMonth));renderDate(day);focusDay(day)}else shiftMonth(-1,true)}
  else if(e.key==='PageDown'){if(e.shiftKey){dateYear++;const day=Math.min(Number(active.dataset.dateDay),monthLen(dateYear,dateMonth));renderDate(day);focusDay(day)}else shiftMonth(1,true)}
  else if(e.key==='Enter'||e.key===' ')activateDate(Number(active.dataset.dateDay));else handled=false;if(handled)e.preventDefault();
}
function chooseDate(day){if(!dateInput)return;const raw=formatJ({jy:dateYear,jm:dateMonth,jd:day});dateInput.value=fa(raw);dateInput.dispatchEvent(new Event('change',{bubbles:true}));if(dateInput.classList.contains('check-in')){const row=dateInput.closest('[data-allocation]'),out=$('.check-out',row);if(out&&(!jToIso(out.value)||jToIso(out.value)<=jToIso(dateInput.value))){out.value=fa(addDaysJ(raw,1));out.dispatchEvent(new Event('change',{bubbles:true}))}}closeDate('selected')}
function openDate(input){dateInput=input;input.setAttribute('aria-haspopup','dialog');const j=parseJ(input.value)||todayJ();dateYear=j.jy;dateMonth=j.jm;renderDate(j.jd);const d=ensureDateDialog();if(!window.SoknaOverlay?.showModal?.(d))return;focusDay(j.jd)}
function prepareDateInput(input){if(!input)return;input.setAttribute('aria-haspopup','dialog');input.setAttribute('aria-expanded','false')}
function refresh(scope=document){scope.querySelectorAll('.jalali-date').forEach(prepareDateInput)}
refresh();
document.addEventListener('click',e=>{const input=e.target.closest?.('.jalali-date');if(input){e.preventDefault();prepareDateInput(input);openDate(input);input.setAttribute('aria-expanded','true')}});
document.addEventListener('keydown',e=>{const input=e.target.closest?.('.jalali-date');if(!input||!['Enter',' '].includes(e.key))return;e.preventDefault();prepareDateInput(input);openDate(input);input.setAttribute('aria-expanded','true')});
document.addEventListener('focusin',e=>{const input=e.target.closest?.('.jalali-date');if(input)prepareDateInput(input)});
document.addEventListener('sokna:date-availability-change',e=>{if(e.target===dateInput)renderDate(visibleFocusDay())});
window.SoknaDate=Object.freeze({jToIso,refresh});
})();
