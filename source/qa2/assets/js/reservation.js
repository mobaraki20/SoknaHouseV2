(()=>{
'use strict';
try{window.__SoknaReservationLifecycle?.abort()}catch(_){}
const lifecycle=new AbortController();window.__SoknaReservationLifecycle=lifecycle;const signal=lifecycle.signal;
const {data,$,$$,fa,en,esc,digits,signedDigits,parseMoney,money,formatMoney}=window.SoknaCore;
const {bindMoney}=window.SoknaForms;
const {jToIso}=window.SoknaDate;
const {toast}=window.SoknaUI;
const draftStorageKey=String(data.reservationDraftKey||'');
const committedDraftToken=String(data.reservationDraftCommittedToken||'');
const draftStorageGet=key=>{try{return window.localStorage?.getItem(key)||null}catch(_){return null}};
const draftStorageSet=(key,value)=>{try{window.localStorage?.setItem(key,value);return true}catch(_){return false}};
const draftStorageRemove=key=>{try{window.localStorage?.removeItem(key)}catch(_){}};
if(draftStorageKey&&committedDraftToken){
  try{const saved=JSON.parse(draftStorageGet(draftStorageKey)||'null');if(saved&&String(saved.clientToken||'')===committedDraftToken)draftStorageRemove(draftStorageKey)}catch(_){draftStorageRemove(draftStorageKey)}
}
const reservationForm=$('[data-reservation-form]');
if(!reservationForm)return;

const rooms=new Map((data.rooms||[]).map(r=>[String(r.id),r]));
const overrides=(data.rateOverrides||[]).map(r=>({...r,room_id:String(r.room_id)}));
const rowsHost=$('#allocationRows',reservationForm),template=$('#allocationTemplate');
const finalTotal=$('[data-final-lodging-total]',reservationForm),deposit=$('[data-deposit-amount]',reservationForm),paymentMethodWrap=$('[data-payment-method]',reservationForm),paymentMethod=paymentMethodWrap?.querySelector('select'),alertBox=$('[data-reservation-alert]',reservationForm),submit=reservationForm.querySelector('button[type="submit"]');
const addAllocation=$('[data-add-allocation]',reservationForm);
const draftKey=reservationForm.dataset.reservationDraftKey||'';
const draftBanner=$('[data-reservation-draft-banner]',reservationForm),draftRestore=$('[data-reservation-draft-restore]',reservationForm),draftDiscard=$('[data-reservation-draft-discard]',reservationForm);
const draftEnabled=reservationForm.dataset.reservationMode==='create'&&draftKey!=='';
const DRAFT_VERSION=2,DRAFT_TTL_MS=24*60*60*1000;
let availabilityTimer=0,availabilityGeneration=0,draftTimer=0,draftReady=false,draftRestoring=false,initialDraftSignature='';
const calendarAvailabilityCache=new Map();
const setDateAvailability=(input,payload,mode)=>{if(!input)return;input._soknaAvailability={ranges:Array.isArray(payload?.ranges)?payload.ranges:[],mode};input.dispatchEvent(new CustomEvent('sokna:date-availability-change',{bubbles:true}))};
const clearDateAvailability=input=>{if(!input)return;delete input._soknaAvailability;input.dispatchEvent(new CustomEvent('sokna:date-availability-change',{bubbles:true}))};
const isoShift=(iso,days)=>{const d=new Date(`${iso}T00:00:00Z`);d.setUTCDate(d.getUTCDate()+days);return d.toISOString().slice(0,10)};
async function loadCalendarAvailability(row){
  const room=roomFor(row),inInput=$('.check-in',row),outInput=$('.check-out',row);if(!inInput||!outInput)return;
  if(!room){clearDateAvailability(inInput);clearDateAvailability(outInput);return}
  const exclude=Number(reservationForm.querySelector('input[name="id"]')?.value||0),inIso=jToIso(inInput.value)||String(data.today||''),from=inIso&&String(data.today||'')&&inIso<String(data.today)?isoShift(inIso,-31):String(data.today||inIso),to=isoShift(from,1090),key=`${room.id}|${exclude}|${from}|${to}`;
  let payload=calendarAvailabilityCache.get(key);
  if(!payload){
    const u=new URL(`${data.baseUrl}/index.php`);u.searchParams.set('page','reservation_calendar_availability');u.searchParams.set('room_id',String(room.id));u.searchParams.set('from',from);u.searchParams.set('to',to);if(exclude)u.searchParams.set('exclude',String(exclude));
    try{const res=await fetch(u,{headers:{Accept:'application/json'},credentials:'same-origin',signal});payload=await res.json();if(!res.ok||payload.ok!==true)throw new Error(payload.error||'availability_calendar_failed');calendarAvailabilityCache.set(key,payload)}catch(_){payload={ranges:[]}}
  }
  setDateAvailability(inInput,payload,'checkin');setDateAvailability(outInput,payload,'checkout');
}

const roomFor=row=>rooms.get($('.room-select',row)?.value||'')||null;
const rateAt=(room,iso)=>{
  const base={base:Number(room.base_rate||0),extra:Number(room.extra_person_rate||0)};
  const o=overrides.find(x=>x.room_id===String(room.id)&&String(x.start_date)<=iso&&String(x.end_date)>iso);
  if(!o)return base;
  const mode=String(o.rate_mode||'fixed'),b=Number(o.base_rate||0),e=Number(o.extra_person_rate||0);
  if(mode==='increase')return{base:base.base+b,extra:base.extra+e};
  if(mode==='decrease')return{base:Math.max(0,base.base-b),extra:Math.max(0,base.extra-e)};
  return{base:b,extra:e};
};
const acceptsStay=(room,inIso,outIso)=>!!room&&(!room.available_from||inIso>=String(room.available_from))&&(!room.retired_from||outIso<=String(room.retired_from));
const setPricingMode=(row,mode)=>{
  row.dataset.pricingMode=mode;
  const hidden=$('.pricing-mode',row);if(hidden)hidden.value=mode;
  const manual=$('[data-manual-price]',row),edit=$('[data-edit-price]',row),source=$('[data-rate-source]',row);
  if(manual)manual.hidden=mode!=='manual';
  if(edit)edit.hidden=mode==='manual';
  if(source)source.textContent=mode==='manual'?'قیمت توافقی هر شب':'نرخ سیستم';
};
function calcRow(row){
  const room=roomFor(row),inIso=jToIso($('.check-in',row)?.value||''),outIso=jToIso($('.check-out',row)?.value||''),guests=Math.max(1,Number(en($('.guest-count',row)?.value||1)));
  let total=0,nights=0;
  if(room&&inIso&&outIso&&outIso>inIso){
    let d=new Date(`${inIso}T00:00:00Z`),end=new Date(`${outIso}T00:00:00Z`);
    while(d<end){const iso=d.toISOString().slice(0,10),r=rateAt(room,iso),extra=Math.max(0,guests-Number(room.standard_capacity||1));total+=r.base+extra*r.extra;nights++;d.setUTCDate(d.getUTCDate()+1)}
  }
  const calc=$('[data-calculated]',row);if(calc)calc.textContent=`${money(total)} تومان`;
  const agreedNightly=$('.agreed-nightly-rate',row),mode=row.dataset.pricingMode==='manual'?'manual':'auto';
  const systemAverage=nights>0?Math.floor(total/nights):0;
  if(agreedNightly&&mode==='auto')agreedNightly.value=formatMoney(systemAverage);
  const nightly=mode==='auto'?systemAverage:Math.max(0,parseMoney(agreedNightly?.value||0));
  const agreed=mode==='auto'?total:nightly*nights;
  const current=$('[data-price-current]',row);if(current)current.textContent=`${money(agreed)} تومان`;
  const help=$('[data-agreed-nightly-help]',row);if(help)help.textContent=`${fa(nights)} شب × ${money(nightly)} تومان = ${money(agreed)} تومان`;
  const title=$('[data-allocation-title]',row);if(title)title.textContent=room?.name||'انتخاب اتاق';
  return{room,total,agreed,agreedNightly:nightly,nights,inIso,outIso,guests,mode};
}
function rowMessage(row,text='',tone=''){
  const error=$('[data-allocation-error]',row),status=$('[data-allocation-status]',row);
  row.classList.toggle('has-error',Boolean(text));
  if(error){error.hidden=!text;error.textContent=text;}
  if(status){status.textContent=text?'نیاز به اصلاح':(tone==='ok'?'آماده':'');status.className=`allocation-status ${text?'danger':tone}`;}
  row.dataset.rowInvalid=text?'1':'0';
}
function localConflicts(rows){
  const parsed=rows.map(row=>({row,x:calcRow(row)}));
  let any=false;
  parsed.forEach(({row,x})=>{
    let msg='';
    if(!x.room)msg='یک اتاق انتخاب کنید.';
    else if(!x.inIso||!x.outIso||x.outIso<=x.inIso)msg='ورود و خروج را بررسی کنید.';
    else if(x.guests>Number(x.room.max_capacity||1))msg=`حداکثر ظرفیت ${x.room.name}، ${fa(x.room.max_capacity||1)} مهمان است.`;
    else if(!acceptsStay(x.room,x.inIso,x.outIso))msg=`${x.room.name} در این بازه داخل چرخه فعالیت نیست.`;
    if(!msg){
      const duplicate=parsed.find(other=>other.row!==row&&other.x.room&&other.x.room.id===x.room.id&&x.inIso<other.x.outIso&&x.outIso>other.x.inIso);
      if(duplicate)msg=`${x.room.name} برای همین بازه قبلاً در این رزرو انتخاب شده است.`;
    }
    if(msg){rowMessage(row,msg);any=true;}else if(row.dataset.serverInvalid!=='1')rowMessage(row,'','');
  });
  return any;
}
function updateAllocationIndexes(){
  $$('[data-allocation]',reservationForm).forEach((row,i)=>{const label=$('.allocation-index',row);if(label)label.textContent=`اتاق ${fa(i+1)}`;});
}
function syncSummary(){
  let calculated=0,agreed=0,totalGuests=0;const lines=[],stays=[];const rows=$$('[data-allocation]',reservationForm);
  rows.forEach(row=>{const x=calcRow(row);calculated+=x.total;if(x.room){
    agreed+=x.agreed;totalGuests+=x.guests;
    const inLabel=$('.check-in',row)?.value||'',outLabel=$('.check-out',row)?.value||'';
    lines.push(`${x.room.name} · ${inLabel} تا ${outLabel} · ${money(x.agreed)} تومان`);stays.push({name:x.room.name,inIso:x.inIso,outIso:x.outIso,inLabel,outLabel});
  }});
  if(finalTotal&&finalTotal.dataset.finalTotalTouched!=='1')finalTotal.value=formatMoney(agreed);
  const total=Math.max(0,parseMoney(finalTotal?.value||agreed)),delta=total-agreed,totalNights=rows.reduce((sum,row)=>sum+calcRow(row).nights,0),effectiveNightly=totalNights>0?Math.floor(total/totalNights):0,paid=Math.max(0,parseMoney(deposit?.value||0)),balance=Math.max(0,total-paid);
  const set=(sel,val)=>{const el=$(sel,reservationForm);if(el)el.textContent=val};
  set('[data-summary-calculated]',`${money(calculated)} تومان`);set('[data-summary-agreed]',`${money(agreed)} تومان`);const systemRateRow=$('[data-summary-system-rate]',reservationForm);if(systemRateRow)systemRateRow.hidden=calculated===agreed;
  const deltaRow=$('[data-summary-delta-row]',reservationForm),deltaLabel=$('[data-summary-delta-label]',reservationForm);if(deltaRow)deltaRow.hidden=delta===0;if(deltaLabel)deltaLabel.textContent=delta<0?'تخفیف ثبت‌شونده':'افزایش نسبت به توافق';set('[data-summary-delta]',`${delta<0?'− ':'+ '}${money(Math.abs(delta))} تومان`);
  const effectiveRow=$('[data-summary-effective-rate-row]',reservationForm);if(effectiveRow)effectiveRow.hidden=delta===0;set('[data-summary-effective-rate]',`${money(effectiveNightly)} تومان`);set('[data-summary-total]',`${money(total)} تومان`);
  set('[data-summary-guest-count]',`${fa(totalGuests)} نفر`);set('[data-summary-deposit]',`${money(paid)} تومان`);set('[data-summary-balance]',`${money(balance)} تومان`);set('[data-submit-total]',`${money(total)} تومان`);
  const derived=$('[data-derived-status]',reservationForm),derivedValue=$('[data-derived-status-value]',reservationForm),derivedHelp=$('[data-derived-status-help]',reservationForm);if(derived&&deposit){const confirmed=paid>0;if(derivedValue)derivedValue.innerHTML=`<span class="badge ${confirmed?'success':'warning'}">${confirmed?'قطعی':'در انتظار'}</span>`;if(derivedHelp)derivedHelp.textContent=confirmed?'به‌دلیل ثبت واریزی، رزرو هنگام ثبت قطعی خواهد شد.':'بدون واریزی، رزرو در انتظار ثبت می‌شود و همچنان ظرفیت اتاق را اشغال می‌کند.';}
  const valid=stays.filter(x=>x.inIso&&x.outIso).sort((a,b)=>a.inIso.localeCompare(b.inIso));
  const first=valid[0],last=valid.slice().sort((a,b)=>b.outIso.localeCompare(a.outIso))[0];
  set('[data-summary-date-range]',first&&last?`${first.inLabel} تا ${last.outLabel}`:'—');
  set('[data-summary-room-names]',stays.length?[...new Set(stays.map(x=>x.name))].join('، '):'—');
  const list=$('[data-summary-rooms]',reservationForm);if(list){list.hidden=lines.length<=1;list.innerHTML=lines.map(x=>`<span>${esc(x)}</span>`).join('');}
  if(paymentMethodWrap&&paymentMethod){const hasPayment=paid>0;paymentMethodWrap.hidden=!hasPayment;paymentMethod.disabled=!hasPayment;}
  updateAllocationIndexes();localConflicts(rows);scheduleAvailability();
}
function bindRow(row){
  const guest=$('.guest-count',row),roomSel=$('.room-select',row),agreed=$('.agreed-nightly-rate',row);
  bindMoney(agreed);
  const syncCapacity=()=>{const room=roomFor(row);if(!room||!guest)return;let n=Math.max(1,Number(en(guest.value||1)));n=Math.min(n,Number(room.max_capacity||1));guest.max=String(room.max_capacity||1);guest.value=fa(n)};
  row.addEventListener('click',e=>{
    if(e.target.closest('[data-edit-price]')){const x=calcRow(row);if(agreed)agreed.value=formatMoney(x.nights>0?Math.floor(x.total/x.nights):0);setPricingMode(row,'manual');agreed?.focus();syncSummary();}
    if(e.target.closest('[data-use-calculated]')){setPricingMode(row,'auto');syncSummary();}
    if(e.target.closest('[data-remove-allocation]')){if($$('[data-allocation]',reservationForm).length<=1){toast('info','حداقل یک اتاق باید در رزرو باقی بماند.');return}row.remove();syncSummary();}
  });
  roomSel?.addEventListener('change',()=>{syncCapacity();loadCalendarAvailability(row);if(row.dataset.pricingMode==='manual'&&agreed){const x=calcRow(row);const source=$('[data-rate-source]',row);if(source)source.textContent=`قیمت توافقی هر شب · نرخ سیستم ${money(x.nights>0?Math.floor(x.total/x.nights):0)} تومان`;}syncSummary();});
  guest?.addEventListener('input',()=>{syncCapacity();syncSummary()});
  agreed?.addEventListener('input',()=>{setPricingMode(row,'manual');syncSummary()});
  $$('.jalali-date',row).forEach(i=>i.addEventListener('change',()=>{if(row.dataset.pricingMode==='manual'){const x=calcRow(row),source=$('[data-rate-source]',row);if(source)source.textContent=`مبلغ نهایی · نرخ جدید ${money(x.total)} تومان`;}syncSummary()}));
  syncCapacity();setPricingMode(row,row.dataset.pricingMode==='manual'?'manual':'auto');loadCalendarAvailability(row);
}
$$('[data-allocation]',reservationForm).forEach(bindRow);
addAllocation?.addEventListener('click',()=>{
  const existing=$$('[data-allocation]',reservationForm),last=existing.at(-1);
  if(localConflicts(existing)||existing.some(row=>row.dataset.serverInvalid==='1')){toast('warning','ابتدا اطلاعات اتاق فعلی را کامل کنید.');const bad=existing.find(r=>r.dataset.rowInvalid==='1');bad?.scrollIntoView({behavior:'smooth',block:'center'});return;}
  const frag=template.content.cloneNode(true),newRow=frag.querySelector('[data-allocation]');
  const inValue=$('.check-in',last)?.value||'',outValue=$('.check-out',last)?.value||'';
  const inInput=$('.check-in',newRow),outInput=$('.check-out',newRow),guest=$('.guest-count',newRow),room=$('.room-select',newRow),agreed=$('.agreed-nightly-rate',newRow);
  if(inInput)inInput.value=inValue;if(outInput)outInput.value=outValue;if(guest)guest.value=fa(1);if(room)room.value='';if(agreed)agreed.value='0';
  newRow.dataset.pricingMode='auto';rowsHost.append(frag);const added=rowsHost.lastElementChild;bindRow(added);syncSummary();added.scrollIntoView({behavior:'smooth',block:'center'});room?.focus();
});
finalTotal?.addEventListener('input',()=>{finalTotal.dataset.finalTotalTouched='1';syncSummary()});deposit?.addEventListener('input',syncSummary);

async function checkAvailability(){
  const generation=++availabilityGeneration,rows=$$('[data-allocation]',reservationForm),exclude=Number(reservationForm.querySelector('input[name="id"]')?.value||0);
  if(localConflicts(rows)){if(alertBox){alertBox.hidden=true;alertBox.textContent='';}return;}
  const checks=rows.map(async(row,index)=>{
    const x=calcRow(row);if(!x.room||!x.inIso||!x.outIso||x.outIso<=x.inIso)return{index,free:false,message:'اطلاعات این اتاق کامل نیست.'};
    const u=new URL(`${data.baseUrl}/index.php`);u.searchParams.set('page','availability_check');u.searchParams.set('room_id',String(x.room.id));u.searchParams.set('check_in',x.inIso);u.searchParams.set('check_out',x.outIso);if(exclude)u.searchParams.set('exclude',String(exclude));
    try{const res=await fetch(u,{headers:{Accept:'application/json'},credentials:'same-origin',signal});const payload=await res.json();if(payload.free===false)payload.message=`${x.room.name} از ${$('.check-in',row)?.value||''} تا ${$('.check-out',row)?.value||''} آزاد نیست.`;return{index,...payload}}catch(_){return{index,free:null,message:'بررسی آنلاین انجام نشد؛ هنگام ثبت دوباره کنترل می‌شود.'}}
  });
  const results=await Promise.all(checks);if(generation!==availabilityGeneration)return;
  let conflict=false,unknown=false;
  results.forEach(r=>{const row=rows[r.index];if(!row)return;if(r.free===false){row.dataset.serverInvalid='1';rowMessage(row,r.message||'این اتاق در این بازه آزاد نیست.');conflict=true;}else{row.dataset.serverInvalid='0';if(row.dataset.rowInvalid!=='1')rowMessage(row,'',r.free===null?'':'ok');if(r.free===null)unknown=true;}});
  if(alertBox){alertBox.hidden=!(unknown&&!conflict);alertBox.textContent=unknown&&!conflict?'بررسی آنلاین برخی اتاق‌ها انجام نشد؛ هنگام ثبت دوباره کنترل می‌شود.':'';}
  reservationForm.dataset.availabilityConflict=conflict?'1':'0';
}

function scheduleAvailability(){clearTimeout(availabilityTimer);if(signal.aborted)return;availabilityTimer=setTimeout(()=>{if(!signal.aborted)checkAvailability()},280)}

reservationForm.addEventListener('submit',e=>{
  if(reservationForm.dataset.submitting==='1'){e.preventDefault();return}
  const rows=$$('[data-allocation]',reservationForm);const invalid=localConflicts(rows)||rows.some(row=>{const x=calcRow(row);return !x.room||!x.inIso||!x.outIso||x.outIso<=x.inIso||x.guests>Number(x.room?.max_capacity||1)||!acceptsStay(x.room,x.inIso,x.outIso)});const serverInvalid=rows.some(row=>row.dataset.serverInvalid==='1');
  if(invalid||serverInvalid){e.preventDefault();const bad=rows.find(r=>r.dataset.rowInvalid==='1'||r.dataset.serverInvalid==='1');bad?.scrollIntoView({behavior:'smooth',block:'center'});toast('warning',serverInvalid?'یکی از اتاق‌ها در بازه انتخاب‌شده آزاد نیست؛ بازه یا اتاق را اصلاح کنید.':'اطلاعات اتاق‌ها را بررسی کنید.');return}
  const creatingNew=newFields&&!newFields.hidden;
  if(!guestId?.value&&(!creatingNew||!String(name?.value||'').trim())){e.preventDefault();const step=$('[data-form-step="2"]',reservationForm);step?.scrollIntoView({behavior:'smooth',block:'center'});toast('warning','مهمان را انتخاب کنید یا مهمان جدید را ثبت کنید.');return}
  $$('.money-input',reservationForm).forEach(input=>input.value=input.classList.contains('signed')?signedDigits(input.value):digits(input.value));
  reservationForm.dataset.submitting='1';if(submit){submit.disabled=true;submit.setAttribute('aria-busy','true');submit.textContent='در حال ثبت…'}
});

/* Guest state */
const search=$('[data-guest-search]',reservationForm),results=$('[data-guest-results]',reservationForm),guestId=$('#guestId',reservationForm),name=$('[data-guest-name]',reservationForm),phone=$('[data-guest-phone]',reservationForm);
const lookup=$('[data-guest-lookup]',reservationForm),selected=$('[data-guest-selected]',reservationForm),newFields=$('[data-new-guest-fields]',reservationForm),selectedName=$('[data-selected-guest-name]',reservationForm),selectedPhone=$('[data-selected-guest-phone]',reservationForm);
let searchTimer=0,searchSeq=0,selectedSignature='';
const guestSignature=()=>`${String(name?.value||'').trim()}|${digits(phone?.value||'')}`;
const rememberGuestSelection=()=>{selectedSignature=guestSignature()};
function setGuestMode(mode){if(lookup)lookup.hidden=mode!=='search';if(selected)selected.hidden=mode!=='selected';if(newFields)newFields.hidden=mode!=='new';if(name){name.disabled=mode!=='new';name.required=mode==='new';}if(phone)phone.disabled=mode!=='new';}
function chooseGuest(g){guestId.value=g.id;name.value=g.name||'';phone.value=fa(g.phone||'');if(selectedName)selectedName.textContent=g.name||'';if(selectedPhone)selectedPhone.textContent=fa(g.phone||'بدون شماره');results.hidden=true;if(search)search.value='';rememberGuestSelection();setGuestMode('selected')}
const detachGuestIfEdited=()=>{if(!guestId?.value||!selectedSignature)return;if(guestSignature()!==selectedSignature){guestId.value='';selectedSignature='';setGuestMode('new')}};
search?.addEventListener('input',()=>{clearTimeout(searchTimer);const q=search.value.trim();if(q.length<2){results.hidden=true;return}searchTimer=setTimeout(async()=>{if(signal.aborted)return;const seq=++searchSeq,u=new URL(`${data.baseUrl}/index.php`);u.searchParams.set('page','guest_search');u.searchParams.set('q',q);try{const res=await fetch(u,{headers:{Accept:'application/json'},credentials:'same-origin',signal}),json=await res.json();if(seq!==searchSeq)return;results.innerHTML=(json.items||[]).map(g=>`<button type="button" data-guest-id="${g.id}" data-name="${esc(g.name)}" data-phone="${esc(g.phone||'')}"><b>${esc(g.name)}</b><small>${esc(fa(g.phone||'بدون شماره'))}</small></button>`).join('')||'<div class="quiet guest-result-empty">مهمان مشابهی پیدا نشد.</div>';results.hidden=false;$$('button[data-guest-id]',results).forEach(b=>b.onclick=()=>chooseGuest({id:b.dataset.guestId,name:b.dataset.name,phone:b.dataset.phone}))}catch(_){results.hidden=true}},250)});
search?.addEventListener('keydown',e=>{if(e.key!=='Enter')return;e.preventDefault();e.stopPropagation();search.dispatchEvent(new Event('input',{bubbles:true}));});
$('[data-start-new-guest]',reservationForm)?.addEventListener('click',()=>{guestId.value='';selectedSignature='';if(name)name.value='';if(phone)phone.value='';setGuestMode('new');name?.focus()});
$('[data-change-guest]',reservationForm)?.addEventListener('click',()=>{guestId.value='';selectedSignature='';if(search)search.value='';if(name)name.value='';if(phone)phone.value='';setGuestMode('search');search?.focus()});
$('[data-back-to-guest-search]',reservationForm)?.addEventListener('click',()=>{guestId.value='';selectedSignature='';if(name)name.value='';if(phone)phone.value='';setGuestMode('search');search?.focus()});
if(guestId?.value){rememberGuestSelection();setGuestMode('selected')}else if(!newFields?.hidden)setGuestMode('new');else setGuestMode('search');
name?.addEventListener('input',detachGuestIfEdited);phone?.addEventListener('input',detachGuestIfEdited);

/* Local create-only draft. It never creates inventory or a database reservation state. */
function draftSnapshot(){
  const guestMode=guestId?.value?'selected':(!newFields?.hidden?'new':'search');
  return {
    version:DRAFT_VERSION,savedAt:Date.now(),clientToken:String(reservationForm.querySelector('input[name="client_token"]')?.value||''),
    allocations:$$('[data-allocation]',reservationForm).map(row=>({roomId:String($('.room-select',row)?.value||''),checkIn:String($('.check-in',row)?.value||''),checkOut:String($('.check-out',row)?.value||''),guests:digits($('.guest-count',row)?.value||'1')||'1',pricingMode:row.dataset.pricingMode==='manual'?'manual':'auto',agreedNightlyRate:digits($('.agreed-nightly-rate',row)?.value||'0')||'0'})),
    guest:{mode:guestMode,id:String(guestId?.value||''),name:String(name?.value||''),phone:String(phone?.value||'')},
    status:String(reservationForm.querySelector('input[name="status"]:checked')?.value||'hold'),depositAmount:digits(deposit?.value||'0')||'0',paymentMethod:String(paymentMethod?.value||'transfer'),
    finalLodgingTotal:digits(finalTotal?.value||'0')||'0',notes:String(reservationForm.querySelector('textarea[name="notes"]')?.value||'')
  };
}
function draftSignature(snapshot){
  if(!snapshot)return'';const x={...snapshot};delete x.savedAt;delete x.clientToken;return JSON.stringify(x);
}
function readDraft(){
  if(!draftEnabled)return null;let saved=null;try{saved=JSON.parse(draftStorageGet(draftKey)||'null')}catch(_){draftStorageRemove(draftKey);return null}
  if(!saved||saved.version!==DRAFT_VERSION||!Number(saved.savedAt)||Date.now()-Number(saved.savedAt)>DRAFT_TTL_MS){draftStorageRemove(draftKey);return null}
  return saved;
}
function persistDraft(){
  if(!draftEnabled||!draftReady||draftRestoring)return;const snapshot=draftSnapshot();
  if(draftSignature(snapshot)===initialDraftSignature){draftStorageRemove(draftKey);return}
  draftStorageSet(draftKey,JSON.stringify(snapshot));
}
function scheduleDraftSave(){if(signal.aborted||!draftEnabled||!draftReady||draftRestoring)return;clearTimeout(draftTimer);draftTimer=setTimeout(()=>{if(!signal.aborted)persistDraft()},180)}
function ensureDraftRows(count){
  const target=Math.max(1,Math.min(8,Number(count)||1));let rows=$$('[data-allocation]',reservationForm);
  while(rows.length<target){const frag=template.content.cloneNode(true);rowsHost.append(frag);const row=rowsHost.lastElementChild;bindRow(row);rows=$$('[data-allocation]',reservationForm)}
  while(rows.length>target){rows.at(-1)?.remove();rows=$$('[data-allocation]',reservationForm)}
  return rows;
}
function applyDraft(saved){
  if(!saved||!Array.isArray(saved.allocations)||!saved.allocations.length)return;draftRestoring=true;
  const rows=ensureDraftRows(saved.allocations.length);
  rows.forEach((row,index)=>{
    const item=saved.allocations[index]||{},room=$('.room-select',row),checkIn=$('.check-in',row),checkOut=$('.check-out',row),guests=$('.guest-count',row),agreed=$('.agreed-nightly-rate',row);
    if(room)room.value=rooms.has(String(item.roomId||''))?String(item.roomId):'';if(checkIn&&item.checkIn)checkIn.value=String(item.checkIn);if(checkOut&&item.checkOut)checkOut.value=String(item.checkOut);if(guests)guests.value=fa(digits(item.guests||'1')||'1');if(agreed)agreed.value=formatMoney(item.agreedNightlyRate||'0');setPricingMode(row,item.pricingMode==='manual'?'manual':'auto');room?.dispatchEvent(new Event('change',{bubbles:true}));
  });
  const token=reservationForm.querySelector('input[name="client_token"]');if(token&&/^[a-f0-9]{32,64}$/.test(String(saved.clientToken||'')))token.value=String(saved.clientToken);
  const guest=saved.guest&&typeof saved.guest==='object'?saved.guest:{};if(name)name.value=String(guest.name||'');if(phone)phone.value=String(guest.phone||'');
  if(guest.mode==='selected'&&String(guest.id||'')){if(guestId)guestId.value=String(guest.id);if(selectedName)selectedName.textContent=String(guest.name||'');if(selectedPhone)selectedPhone.textContent=String(guest.phone||'بدون شماره');rememberGuestSelection();setGuestMode('selected')}else{if(guestId)guestId.value='';selectedSignature='';setGuestMode(guest.mode==='new'?'new':'search')}
  const status=reservationForm.querySelector(`input[name="status"][value="${saved.status==='confirmed'?'confirmed':'hold'}"]`);if(status&&!status.disabled)status.checked=true;
  if(deposit)deposit.value=formatMoney(saved.depositAmount||'0');if(paymentMethod&&['transfer','card','cash','other'].includes(String(saved.paymentMethod||'')))paymentMethod.value=String(saved.paymentMethod);if(finalTotal){finalTotal.value=formatMoney(saved.finalLodgingTotal||'0');finalTotal.dataset.finalTotalTouched='1'}const notes=reservationForm.querySelector('textarea[name="notes"]');if(notes)notes.value=String(saved.notes||'');
  const notesDisclosure=$('.reservation-notes-disclosure',reservationForm);if(notesDisclosure)notesDisclosure.open=String(notes?.value||'').trim()!=='';
  updateAllocationIndexes();syncSummary();localConflicts(rows);scheduleAvailability();draftRestoring=false;draftReady=true;if(draftBanner)draftBanner.hidden=true;toast('success','فرم نیمه‌تمام بازیابی شد.');
}
function enableFreshDraft(){draftReady=true;if(draftBanner)draftBanner.hidden=true}
if(draftEnabled){
  initialDraftSignature=draftSignature(draftSnapshot());
  const saved=readDraft();
  if(saved&&draftBanner){draftBanner.hidden=false;draftRestore?.addEventListener('click',()=>applyDraft(saved));draftDiscard?.addEventListener('click',()=>{draftStorageRemove(draftKey);enableFreshDraft();toast('info','پیش‌نویس محلی حذف شد.')})}
  else enableFreshDraft();
  reservationForm.addEventListener('input',scheduleDraftSave);reservationForm.addEventListener('change',scheduleDraftSave);reservationForm.addEventListener('click',e=>{if(e.target.closest('[data-add-allocation],[data-remove-allocation],[data-edit-price],[data-use-calculated],[data-start-new-guest],[data-change-guest],[data-back-to-guest-search],[data-guest-id]'))setTimeout(scheduleDraftSave,0)});
}

syncSummary();
})();
