(()=>{
'use strict';
const faToEn=(value='')=>String(value).replace(/[۰-۹]/g,d=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g,d=>'٠١٢٣٤٥٦٧٨٩'.indexOf(d));
const holidays=window.SoknaIranHolidays;
document.querySelectorAll('[data-public-jalali]').forEach(cell=>{
  if(!holidays?.info)return;
  const info=holidays.info(faToEn(cell.dataset.publicJalali||''));
  if(!info)return;
  cell.dataset.holiday='1';
  const current=cell.getAttribute('aria-label')||'';
  cell.setAttribute('aria-label',current?`${current}، ${info.label}`:info.label);
  cell.title=info.label;
});

const calendar=document.querySelector('[data-public-calendar]');
if(!calendar)return;
const months=[...calendar.querySelectorAll('[data-public-month-index]')];
const prev=calendar.querySelector('[data-public-month-prev]');
const next=calendar.querySelector('[data-public-month-next]');
const title=calendar.querySelector('[data-public-month-title]');
const swipeArea=calendar.querySelector('[data-public-months]')||calendar;
let active=0;
const setMonth=index=>{
  if(!months.length)return;
  active=Math.max(0,Math.min(months.length-1,index));
  months.forEach((month,i)=>{
    const on=i===active;
    month.classList.toggle('is-active',on);
    month.setAttribute('aria-hidden',on?'false':'true');
  });
  if(title)title.textContent=months[active]?.dataset.publicMonthLabel||'';
  if(prev)prev.disabled=active<=0;
  if(next)next.disabled=active>=months.length-1;
};
prev?.addEventListener('click',()=>setMonth(active-1));
next?.addEventListener('click',()=>setMonth(active+1));
let startX=0,startY=0,tracking=false;
swipeArea?.addEventListener('touchstart',event=>{
  const touch=event.touches?.[0];if(!touch)return;startX=touch.clientX;startY=touch.clientY;tracking=true;
},{passive:true});
swipeArea?.addEventListener('touchend',event=>{
  if(!tracking)return;tracking=false;const touch=event.changedTouches?.[0];if(!touch)return;
  const dx=touch.clientX-startX,dy=touch.clientY-startY;
  if(Math.abs(dx)<45||Math.abs(dx)<=Math.abs(dy)*1.2)return;
  if(dx<0)setMonth(active+1);else setMonth(active-1);
},{passive:true});
setMonth(0);
})();
