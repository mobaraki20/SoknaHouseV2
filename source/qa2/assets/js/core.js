(()=>{
'use strict';
const data=window.SOKNA||{};
const $=(s,c=document)=>c.querySelector(s), $$=(s,c=document)=>[...c.querySelectorAll(s)];
const fa=v=>String(v??'').replace(/[0-9]/g,d=>'۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
const en=v=>String(v??'').replace(/[۰-۹٠-٩]/g,d=>({'۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9','٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9'}[d]||d));
const esc=s=>String(s??'').replace(/[&<>'"]/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
const digits=v=>en(v).replace(/[^0-9]/g,'');
const signedDigits=v=>{const n=en(v).replace(/[−–—]/g,'-').trim();return (n.startsWith('-')?'-':'')+n.replace(/[^0-9]/g,'')};
const parseMoney=v=>Number(digits(v)||0);
const money=v=>new Intl.NumberFormat('fa-IR').format(Number(v||0));
const formatMoney=v=>{const d=digits(v).replace(/^0+(?=\d)/,'')||'0';return fa(d.replace(/\B(?=(\d{3})+(?!\d))/g,'٬'))};
const formatSigned=v=>{const s=signedDigits(v),neg=s.startsWith('-'),d=s.replace('-','').replace(/^0+(?=\d)/,'')||'0';return `${neg?'− ':''}${fa(d.replace(/\B(?=(\d{3})+(?!\d))/g,'٬'))}`};
const iconPaths=Object.freeze({
  close:'<path d="M6 6l12 12M18 6 6 18"/>',
  chevronDown:'<path d="m6 9 6 6 6-6"/>',
  chevronLeft:'<path d="m15 5-7 7 7 7"/>',
  chevronRight:'<path d="m9 5 7 7-7 7"/>'
});
const iconSvg=name=>`<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${iconPaths[name]||''}</svg>`;

/*
 * Shared page scroll lock.
 * Dialogs, drawers and any future overlay must use this owner-based primitive instead
 * of writing documentElement/body overflow directly. Multiple owners can coexist.
 */
const scrollOwners=new Set();
let scrollSnapshot=null;
/*
 * Freeze the scrolling root without turning body into a fixed-position layer.
 * Setting body.position=fixed removes sticky headers from the viewport's scroll
 * context and causes the entire background to jump when a dialog opens.
 * Using overflow on the scrolling root preserves scrollY and sticky geometry.
 */
const lockScroll=(owner='global')=>{
  if(scrollOwners.has(owner))return;
  if(scrollOwners.size===0){
    const body=document.body,html=document.documentElement;
    scrollSnapshot={
      y:Math.max(0,Math.round(window.scrollY||0)),
      htmlOverflow:html.style.overflow,
      bodyOverflow:body.style.overflow
    };
    html.classList.add('page-scroll-locked');
    html.style.overflow='hidden';
    // Body stays in normal flow so sticky descendants retain the viewport scroll context.
  }
  scrollOwners.add(owner);
};
const unlockScroll=(owner='global')=>{
  if(!scrollOwners.has(owner))return;
  scrollOwners.delete(owner);
  if(scrollOwners.size!==0)return;
  const snapshot=scrollSnapshot;scrollSnapshot=null;
  const body=document.body,html=document.documentElement;
  html.classList.remove('page-scroll-locked');
  html.style.overflow=snapshot?.htmlOverflow||'';
  body.style.overflow=snapshot?.bodyOverflow||'';
  /* Restore only if a browser moved the viewport despite the scroll lock. */
  if(snapshot&&Math.abs(window.scrollY-snapshot.y)>1)
    window.scrollTo({top:snapshot.y,left:0,behavior:'instant'});
};
const isScrollLocked=()=>scrollOwners.size>0;

window.SoknaCore=Object.freeze({data,$,$$,fa,en,esc,digits,signedDigits,parseMoney,money,formatMoney,formatSigned,iconSvg,lockScroll,unlockScroll,isScrollLocked});
})();
