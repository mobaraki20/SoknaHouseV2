(()=>{
'use strict';
const {$,$$,fa,en,iconSvg,lockScroll,unlockScroll}=window.SoknaCore;
const restoreFocus=el=>{if(!el?.isConnected)return;requestAnimationFrame(()=>{if(el.isConnected)el.focus({preventScroll:true})})};
const dialogOwners=new WeakMap();

/*
 * Shared modal lifecycle. Native <dialog> alone does not reliably stop the page
 * behind it from scrolling on mobile browsers, so every modal goes through here.
 */
function showModal(dialog){
  if(!dialog||typeof dialog.showModal!=='function'||dialog.open)return false;
  const owner={dialog};dialogOwners.set(dialog,owner);lockScroll(owner);
  const release=()=>{const current=dialogOwners.get(dialog);if(current){dialogOwners.delete(dialog);unlockScroll(current)}};
  dialog.addEventListener('close',release,{once:true});
  try{dialog.showModal();return true}catch(error){release();throw error}
}
function closeModal(dialog,value='cancel'){if(dialog?.open)dialog.close(value)}
const closeOnBackdrop=(dialog,e)=>{if(e.target===dialog)closeModal(dialog,'cancel')};

/* Confirmation / notice: one centered, shared system dialog. */
const systemDialog=$('#appDialog');let systemResolve=null;
function ask(message,{title='تأیید عملیات',confirmLabel='تأیید',cancelLabel='انصراف',danger=false,notice=false,trigger=null}={}){
  if(!systemDialog)return Promise.resolve(Boolean(notice));const returnFocus=trigger instanceof HTMLElement?trigger:(document.activeElement instanceof HTMLElement?document.activeElement:null);
  if(systemResolve){systemResolve(false);systemResolve=null}
  $('[data-dialog-title]',systemDialog).textContent=title;$('[data-dialog-message]',systemDialog).textContent=message;
  const yes=$('[data-dialog-confirm]',systemDialog),no=$('[data-dialog-cancel]',systemDialog),actions=$('.dialog-actions',systemDialog);
  yes.textContent=confirmLabel;no.textContent=cancelLabel;no.hidden=notice;actions?.classList.toggle('is-single',notice);yes.classList.toggle('danger',danger);yes.classList.toggle('primary',!danger);
  return new Promise(resolve=>{let done=false;const finish=v=>{if(done)return;done=true;systemResolve=null;restoreFocus(returnFocus);resolve(v)};systemResolve=finish;const closed=()=>{systemDialog.removeEventListener('close',closed);finish(systemDialog.returnValue==='confirm')};systemDialog.addEventListener('close',closed);showModal(systemDialog);requestAnimationFrame(()=>yes.focus())});
}
systemDialog?.addEventListener('cancel',e=>{e.preventDefault();closeModal(systemDialog,'cancel')});

/* Choice overlay: native select remains the source of truth; this is presentation only. */
let choiceDialog=null,choiceSelect=null,choiceTrigger=null;
function cleanupChoice(trigger=choiceTrigger){
  if(trigger?.isConnected)trigger.setAttribute('aria-expanded','false');if(trigger===choiceTrigger){choiceSelect=null;choiceTrigger=null}choiceDialog?.classList.remove('is-bottom-sheet');restoreFocus(trigger);
}
function closeChoice(value='cancel'){
  const d=ensureChoiceDialog(),trigger=choiceTrigger;if(trigger?.isConnected)trigger.setAttribute('aria-expanded','false');if(trigger===choiceTrigger){choiceSelect=null;choiceTrigger=null}d.classList.remove('is-bottom-sheet');closeModal(d,value);restoreFocus(trigger);
}
function ensureChoiceDialog(){
  if(choiceDialog)return choiceDialog;choiceDialog=document.createElement('dialog');choiceDialog.className='choice-dialog';choiceDialog.setAttribute('aria-labelledby','choiceDialogTitle');choiceDialog.innerHTML=`<section class="overlay-card choice-card"><header class="overlay-head"><div><span class="eyebrow">انتخاب</span><h2 id="choiceDialogTitle" data-choice-title>انتخاب گزینه</h2></div><button class="icon-btn dialog-close" type="button" data-choice-close aria-label="بستن پنجره انتخاب">${iconSvg('close')}</button></header><div class="choice-list" data-choice-list></div></section>`;document.body.append(choiceDialog);
  $('[data-choice-close]',choiceDialog).onclick=()=>closeChoice('cancel');choiceDialog.addEventListener('cancel',e=>{e.preventDefault();closeChoice('cancel')});choiceDialog.addEventListener('click',e=>{if(e.target===choiceDialog)closeChoice('cancel')});choiceDialog.addEventListener('close',()=>{if(choiceDialog.open)return;cleanupChoice()});return choiceDialog;
}
function choiceLabel(select){const field=select.closest('.field');return field?.querySelector(':scope > span,:scope > legend')?.textContent?.trim()||select.getAttribute('aria-label')||'انتخاب گزینه'}
function renderChoice(){
  const d=ensureChoiceDialog(),host=$('[data-choice-list]',d);if(!choiceSelect||!host)return;host.innerHTML='';$('[data-choice-title]',d).textContent=choiceLabel(choiceSelect);
  [...choiceSelect.options].forEach(opt=>{
    if(choiceSelect.required&&opt.value==='')return;
    const b=document.createElement('button');b.type='button';b.className='choice-option';b.disabled=opt.disabled;b.dataset.value=opt.value;
    const copy=document.createElement('span');copy.className='choice-option-copy';
    const primary=document.createElement('b');primary.className='choice-option-primary';primary.textContent=opt.dataset.choicePrimary||opt.textContent||'';copy.append(primary);
    const secondary=opt.dataset.choiceSecondary||'';if(secondary){const small=document.createElement('small');small.className='choice-option-secondary';small.textContent=secondary;copy.append(small)}
    const technical=opt.dataset.choiceTechnical||'';if(technical){const code=document.createElement('bdi');code.dir='ltr';code.className='choice-option-technical technical';code.textContent=technical;copy.append(code)}
    const mark=document.createElement('i');mark.setAttribute('aria-hidden','true');mark.textContent='✓';b.append(copy,mark);
    b.classList.toggle('selected',opt.selected);b.onclick=()=>{if(!choiceSelect)return;choiceSelect.value=opt.value;choiceSelect.dispatchEvent(new Event('change',{bubbles:true}));syncChoiceTrigger(choiceSelect);closeChoice('selected')};host.append(b)
  });
}

function openChoice(select,trigger){
  choiceSelect=select;choiceTrigger=trigger;trigger.setAttribute('aria-expanded','true');const d=ensureChoiceDialog(),mode=select.dataset.choiceMode||([...select.options].filter(x=>!x.disabled).length>5?'browse':'compact');d.classList.toggle('is-bottom-sheet',mode==='browse'&&matchMedia('(max-width: 560px)').matches);renderChoice();showModal(d);requestAnimationFrame(()=>$('.choice-option.selected',d)?.focus()||$('.choice-option:not(:disabled)',d)?.focus());
}
function syncChoiceTrigger(select){const trigger=select.parentElement?.querySelector(':scope > .choice-trigger');if(trigger){{const opt=select.selectedOptions[0];trigger.querySelector(':scope > span').textContent=opt?.dataset.choicePrimary||opt?.textContent?.trim()||'انتخاب کنید'};trigger.disabled=select.disabled;trigger.setAttribute('aria-invalid',select.matches(':invalid')?'true':'false')}}
function enhanceSelect(select){
  if(select.dataset.choiceBound||select.multiple||select.dataset.nativeSelect!==undefined)return;select.dataset.choiceBound='1';select.classList.add('choice-native');select.tabIndex=-1;
  const trigger=document.createElement('button');trigger.type='button';trigger.className='choice-trigger';trigger.setAttribute('aria-haspopup','dialog');trigger.setAttribute('aria-expanded','false');trigger.innerHTML=`<span></span><span class="choice-trigger-icon" aria-hidden="true">${iconSvg('chevronDown')}</span>`;select.insertAdjacentElement('afterend',trigger);syncChoiceTrigger(select);
  trigger.addEventListener('click',()=>openChoice(select,trigger));select.addEventListener('change',()=>syncChoiceTrigger(select));select.addEventListener('invalid',e=>{e.preventDefault();syncChoiceTrigger(select);trigger.focus()});
  new MutationObserver(()=>syncChoiceTrigger(select)).observe(select,{childList:true,subtree:true,attributes:true,attributeFilter:['disabled','selected']});
}
function refresh(scope=document){$$('select',scope).forEach(enhanceSelect);$$('input[data-time-picker],input[name="check_in_time"],input[name="check_out_time"],input[name="service_time"],input[name="due_time"]',scope).forEach(enhanceTime)}
$$('select').forEach(enhanceSelect);

/* Time picker: centered modal; the text input remains the submitted source of truth. */
let timeDialog=null,timeInput=null,timeHour=null,timeMinute=null;
function ensureTimeDialog(){
  if(timeDialog)return timeDialog;timeDialog=document.createElement('dialog');timeDialog.className='time-dialog';timeDialog.setAttribute('aria-labelledby','timeDialogTitle');timeDialog.innerHTML=`<section class="overlay-card time-card"><header class="overlay-head"><div><span class="eyebrow">زمان</span><h2 id="timeDialogTitle" data-time-title>انتخاب ساعت</h2></div><button class="icon-btn dialog-close" type="button" data-time-close aria-label="بستن انتخاب ساعت">${iconSvg('close')}</button></header><div class="time-fields"><label><span>ساعت</span><div class="stepper time-stepper"><button type="button" tabindex="-1" data-time-hour-plus aria-label="افزایش ساعت">+</button><input inputmode="numeric" maxlength="2" data-time-hour aria-label="ساعت"><button type="button" tabindex="-1" data-time-hour-minus aria-label="کاهش ساعت">−</button></div></label><span class="time-separator">:</span><label><span>دقیقه</span><div class="stepper time-stepper"><button type="button" tabindex="-1" data-time-minute-plus aria-label="افزایش دقیقه">+</button><input inputmode="numeric" maxlength="2" data-time-minute aria-label="دقیقه"><button type="button" tabindex="-1" data-time-minute-minus aria-label="کاهش دقیقه">−</button></div></label></div><div class="dialog-actions"><button class="btn primary" type="button" data-time-confirm>تأیید</button><button class="btn ghost" type="button" data-time-clear hidden>بدون ساعت</button><button class="btn" type="button" data-time-cancel>انصراف</button></div></section>`;document.body.append(timeDialog);
  timeHour=$('[data-time-hour]',timeDialog);timeMinute=$('[data-time-minute]',timeDialog);
  const clamp=(v,max)=>Math.min(max,Math.max(0,Number(en(v))||0)),sync=()=>{timeHour.value=fa(String(clamp(timeHour.value,23)).padStart(2,'0'));timeMinute.value=fa(String(clamp(timeMinute.value,59)).padStart(2,'0'))};
  $('[data-time-hour-plus]',timeDialog).onclick=()=>{timeHour.value=fa(String((clamp(timeHour.value,23)+1)%24).padStart(2,'0'))};$('[data-time-hour-minus]',timeDialog).onclick=()=>{timeHour.value=fa(String((clamp(timeHour.value,23)+23)%24).padStart(2,'0'))};
  $('[data-time-minute-plus]',timeDialog).onclick=()=>{timeMinute.value=fa(String((clamp(timeMinute.value,59)+5)%60).padStart(2,'0'))};$('[data-time-minute-minus]',timeDialog).onclick=()=>{timeMinute.value=fa(String((clamp(timeMinute.value,59)+55)%60).padStart(2,'0'))};
  timeHour.addEventListener('keydown',e=>{if(e.key!=='ArrowUp'&&e.key!=='ArrowDown')return;e.preventDefault();timeHour.value=fa(String((clamp(timeHour.value,23)+(e.key==='ArrowUp'?1:23))%24).padStart(2,'0'))});
  timeMinute.addEventListener('keydown',e=>{if(e.key!=='ArrowUp'&&e.key!=='ArrowDown')return;e.preventDefault();timeMinute.value=fa(String((clamp(timeMinute.value,59)+(e.key==='ArrowUp'?5:55))%60).padStart(2,'0'))});
  timeHour.addEventListener('change',sync);timeMinute.addEventListener('change',sync);$('[data-time-close]',timeDialog).onclick=()=>closeModal(timeDialog,'cancel');$('[data-time-cancel]',timeDialog).onclick=()=>closeModal(timeDialog,'cancel');
  $('[data-time-clear]',timeDialog).onclick=()=>{if(!timeInput)return;timeInput.value='';timeInput.dispatchEvent(new Event('input',{bubbles:true}));timeInput.dispatchEvent(new Event('change',{bubbles:true}));closeModal(timeDialog,'cleared')};
  $('[data-time-confirm]',timeDialog).onclick=()=>{if(!timeInput)return;sync();timeInput.value=`${timeHour.value}:${timeMinute.value}`;timeInput.dispatchEvent(new Event('input',{bubbles:true}));timeInput.dispatchEvent(new Event('change',{bubbles:true}));closeModal(timeDialog,'confirm')};
  timeDialog.addEventListener('cancel',e=>{e.preventDefault();closeModal(timeDialog,'cancel')});timeDialog.addEventListener('click',e=>closeOnBackdrop(timeDialog,e));timeDialog.addEventListener('close',()=>{const input=timeInput;timeInput=null;restoreFocus(input)});return timeDialog;
}
function openTime(input){timeInput=input;const raw=en(input.value||'').match(/^(\d{1,2}):(\d{2})$/);const h=raw?Math.min(23,+raw[1]):12,m=raw?Math.min(59,+raw[2]):0;const d=ensureTimeDialog();$('[data-time-title]',d).textContent=input.closest('.field')?.querySelector(':scope > span')?.textContent?.trim()||'انتخاب ساعت';const clear=$('[data-time-clear]',d);if(clear)clear.hidden=input.dataset.timeOptional===undefined;timeHour.value=fa(String(h).padStart(2,'0'));timeMinute.value=fa(String(m).padStart(2,'0'));showModal(d);requestAnimationFrame(()=>timeHour.focus())}
function enhanceTime(input){if(input.dataset.timeBound)return;input.dataset.timeBound='1';input.classList.add('time-picker-input','active-picker-control');const raw=en(input.value||'');if(/^\d{1,2}:\d{2}$/.test(raw)){const [h,m]=raw.split(':');input.value=`${fa(String(Number(h)).padStart(2,'0'))}:${fa(String(Number(m)).padStart(2,'0'))}`}input.readOnly=true;input.setAttribute('aria-haspopup','dialog');input.addEventListener('click',e=>{e.preventDefault();openTime(input)});input.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();openTime(input)}})}
$$('input[data-time-picker],input[name="check_in_time"],input[name="check_out_time"],input[name="service_time"],input[name="due_time"]').forEach(enhanceTime);

/* A native dialog can outlive a soft page replacement because it lives outside
 * .content-wrap. Close it and synchronously release our scroll owner before
 * replacing the page; the native close event fires later and becomes a no-op. */
const preparePageSwap=()=>{
  for(const dialog of document.querySelectorAll('dialog[open]')){
    try{dialog.close('cancel')}catch(_){ }
    const owner=dialogOwners.get(dialog);
    if(owner){dialogOwners.delete(dialog);unlockScroll(owner)}
  }
};
window.SoknaOverlay=Object.freeze({showModal,closeModal,ask,refresh,preparePageSwap,notice:(message,options={})=>ask(message,{...options,notice:true,confirmLabel:options.confirmLabel||'متوجه شدم'})});
})();
