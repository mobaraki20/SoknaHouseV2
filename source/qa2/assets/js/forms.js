(()=>{
'use strict';
const {$$,fa,digits,signedDigits,formatMoney,formatSigned,money}=window.SoknaCore;
/* Money inputs */
function bindMoney(input){
  if(input.dataset.moneyBound)return;
  input.dataset.moneyBound='1';
  const signed=input.classList.contains('signed');
  const raw=()=>signed?signedDigits(input.value):digits(input.value);
  const isZero=()=>Number(raw()||0)===0;
  input.value=signed?formatSigned(input.value):formatMoney(input.value);
  input.addEventListener('focus',()=>{if(!isZero())return;requestAnimationFrame(()=>{try{input.select()}catch(_){}})});
  input.addEventListener('pointerup',e=>{if(document.activeElement===input&&isZero()){e.preventDefault();try{input.select()}catch(_){}}});
  input.addEventListener('input',()=>{const end=input.selectionStart===input.value.length;input.value=signed?formatSigned(input.value):formatMoney(input.value);if(end)try{input.setSelectionRange(input.value.length,input.value.length)}catch(_){}})
}
/* Shared bounded integer stepper: compact, one Tab stop, Persian display. */
function bindStepper(stepper){
  if(stepper.dataset.stepperBound)return;stepper.dataset.stepperBound='1';
  const input=stepper.querySelector('input'),buttons=$$('[data-number-step]',stepper);if(!input)return;
  buttons.forEach(button=>button.tabIndex=-1);
  const bounds=()=>({min:Number(digits(input.min||'0')||0),max:input.max!==''?Number(digits(input.max)||0):Number.MAX_SAFE_INTEGER});
  const clamp=value=>{const {min,max}=bounds();return Math.min(max,Math.max(min,Number.isFinite(value)?value:min))};
  const display=value=>{input.value=fa(String(clamp(value)))};
  const current=()=>clamp(Number(digits(input.value||'0')||0));
  const commit=(value,change=true)=>{display(value);input.dispatchEvent(new Event('input',{bubbles:true}));if(change)input.dispatchEvent(new Event('change',{bubbles:true}))};
  const normalizeTyping=()=>{const raw=digits(input.value);if(raw===''){input.value='';return}const end=input.selectionStart===input.value.length;input.value=fa(raw);if(end)try{input.setSelectionRange(input.value.length,input.value.length)}catch(_){}};
  buttons.forEach(button=>button.addEventListener('click',()=>commit(current()+(button.dataset.numberStep==='plus'?1:-1))));
  input.addEventListener('keydown',e=>{if(e.key!=='ArrowUp'&&e.key!=='ArrowDown')return;e.preventDefault();commit(current()+(e.key==='ArrowUp'?1:-1))});
  input.addEventListener('input',normalizeTyping);input.addEventListener('change',()=>commit(current(),false));input.addEventListener('blur',()=>{if(input.value==='')display(bounds().min);else display(current())});display(current());
}
function bindNumeric(input){
  if(input.dataset.numericBound)return;input.dataset.numericBound='1';
  const normalize=()=>{const raw=digits(input.value);if(raw===''){input.value='';return}const end=input.selectionStart===input.value.length;input.value=fa(raw);if(end)try{input.setSelectionRange(input.value.length,input.value.length)}catch(_){}};
  input.addEventListener('input',normalize);input.addEventListener('change',normalize);normalize();
}
function bindFile(input){
  if(input.dataset.fileBound)return;input.dataset.fileBound='1';
  const name=input.closest('.file-picker')?.querySelector('[data-file-picker-name]');
  const refresh=()=>{if(!name)return;const files=[...(input.files||[])];name.textContent=files.length?(files.length===1?files[0].name:`${files.length} فایل انتخاب شد`):'فایلی انتخاب نشده';};
  input.addEventListener('change',refresh);refresh();
}
function normalizeMobileLocal(value){
  let d=digits(value);if(d.startsWith('0098'))d=`0${d.slice(4)}`;else if(d.startsWith('98')&&d.length>=12)d=`0${d.slice(2)}`;return d;
}
function bindMobile(input){
  if(input.dataset.mobileBound)return;input.dataset.mobileBound='1';
  const validate=()=>{const d=normalizeMobileLocal(input.value);const ok=d===''||/^09\d{9}$/.test(d);input.setCustomValidity(ok?'':'شماره موبایل را به شکل 09xxxxxxxxx وارد کنید.');return ok};
  input.addEventListener('input',validate);input.addEventListener('change',()=>{const d=normalizeMobileLocal(input.value);if(/^09\d{9}$/.test(d))input.value=fa(d);validate()});input.addEventListener('blur',()=>{const d=normalizeMobileLocal(input.value);if(/^09\d{9}$/.test(d))input.value=fa(d);validate()});validate();
}
/* Safe Enter contract: Enter may navigate/search on GET forms, but never commits a POST form
   from an editable field unless that form explicitly opts in. Buttons keep native keyboard activation. */
document.addEventListener('keydown',e=>{
  if(e.key!=='Enter'||e.isComposing||e.defaultPrevented)return;
  const target=e.target;if(!(target instanceof Element))return;
  if(target.matches('textarea,[contenteditable="true"],button,input[type="submit"],input[type="button"],input[type="reset"]'))return;
  const form=target.closest('form');if(!(form instanceof HTMLFormElement))return;
  if((form.getAttribute('method')||'get').toLowerCase()==='get'||form.dataset.enterSubmit==='allow')return;
  e.preventDefault();
},true);

/* Normalize numeric values only in the outgoing FormData. Never mutate the visible Persian UI on submit. */
document.addEventListener('formdata',e=>{
  const form=e.target;if(!(form instanceof HTMLFormElement))return;
  const controls=[...form.querySelectorAll('.money-input,[data-number-stepper] input,input[inputmode="numeric"]:not(.money-input):not([data-number-stepper] input)')]
    .filter(input=>input instanceof HTMLInputElement&&input.name&&!input.disabled);
  const names=[...new Set(controls.map(input=>input.name))];
  names.forEach(name=>{
    const group=controls.filter(input=>input.name===name);if(!group.length)return;
    e.formData.delete(name);
    group.forEach(input=>{
      const raw=input.classList.contains('money-input')&&input.classList.contains('signed')?signedDigits(input.value):digits(input.value);
      e.formData.append(name,raw);
    });
  });
  [...form.querySelectorAll('[data-mobile-input]')].forEach(input=>{
    if(!(input instanceof HTMLInputElement)||!input.name||input.disabled)return;
    const normalized=normalizeMobileLocal(input.value);
    e.formData.set(input.name,normalized);
  });
});
document.addEventListener('submit',e=>{
  const form=e.target;if(!(form instanceof HTMLFormElement)||!form.matches('[data-sensitive-commit]'))return;
  if(form.dataset.submitting==='1'){e.preventDefault();return}
  form.dataset.submitting='1';const submit=e.submitter||form.querySelector('button[type="submit"],input[type="submit"]');
  if(submit){submit.disabled=true;submit.setAttribute('aria-busy','true')}
},true);

/* Financial actions: one open workflow at a time, live refund consequence preview. */
$$('[data-financial-actions]').forEach(group=>{
  const actions=$$('[data-financial-action]',group);
  actions.forEach(details=>details.addEventListener('toggle',()=>{if(details.open)actions.forEach(other=>{if(other!==details)other.open=false})}));
});
$$('[data-refund-form]').forEach(form=>{
  const amount=form.querySelector('[data-refund-amount]'),limitHelp=form.querySelector('[data-refund-limit-help]'),preview=form.querySelector('[data-refund-preview]'),after=form.querySelector('[data-refund-after]'),overrideNote=form.querySelector('[data-refund-override-note]'),extra=form.querySelector('[data-refund-extra]'),note=form.querySelector('[data-refund-note]'),noteLabel=form.querySelector('[data-refund-note-label]'),noteHelp=form.querySelector('[data-refund-note-help]');
  const credit=Number(form.dataset.refundCreditLimit||0),available=Number(form.dataset.refundAvailable||0),before=Number(form.dataset.refundBalanceBefore||0),admin=form.dataset.refundAdmin==='1',ceiling=admin?available:credit;
  const sync=()=>{
    const value=Number(digits(amount?.value||'')||0),overLimit=value>ceiling,override=admin&&!overLimit&&value>credit;
    if(amount)amount.setCustomValidity(overLimit?`مبلغ بازپرداخت نمی‌تواند از ${money(ceiling)} تومان بیشتر باشد.`:'');
    if(limitHelp){limitHelp.hidden=!overLimit;limitHelp.textContent=overLimit?`حداکثر مجاز برای این ثبت ${money(ceiling)} تومان است.`:''}
    if(preview)preview.hidden=value<=0;
    if(after&&value>0){const balance=before+value;after.textContent=balance<0?`بستانکاری مهمان ${money(Math.abs(balance))} تومان`:balance>0?`بدهی مهمان ${money(balance)} تومان`:'تسویه کامل';after.dataset.state=balance<0?'credit':balance>0?'due':'settled'}
    if(overrideNote)overrideNote.hidden=!override;
    if(note){note.required=override;if(override&&extra)extra.open=true}
    if(noteLabel)noteLabel.textContent=override?'دلیل مدیریتی *':'یادداشت';
    if(noteHelp)noteHelp.hidden=!override;
  };
  amount?.addEventListener('input',sync);sync();
});

/* Generic field shortcuts: business settings can set a known value without owning duplicate JS. */
$$('[data-set-field]').forEach(button=>button.addEventListener('click',()=>{const selector=button.dataset.setField||'',value=button.dataset.setValue||'';if(!selector)return;const input=document.querySelector(selector);if(!(input instanceof HTMLInputElement||input instanceof HTMLTextAreaElement||input instanceof HTMLSelectElement))return;input.value=value;input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));input.focus();}));

/* Service pricing UI: one business pricing mode, one relevant amount/count surface. */
function syncServicePricing(select,resetPrice=false){
  const form=select.closest('[data-service-pricing-form]')||select.closest('form'),opt=select.selectedOptions[0];if(!form||!opt)return;
  const pricing=opt.dataset.pricing||'fixed',input=form.querySelector('[data-service-price]'),label=form.querySelector('[data-service-amount-label]'),quantity=form.querySelector('[data-service-quantity-field]'),people=form.querySelector('[data-service-people-field]');
  if(resetPrice&&input)input.value=formatMoney(opt.dataset.price||0);
  if(label)label.textContent=opt.dataset.amountLabel||(pricing==='per_person'?'مبلغ هر نفر':pricing==='quantity'?'مبلغ هر واحد':'مبلغ خدمت');
  if(quantity)quantity.hidden=pricing!=='quantity';
  if(people)people.hidden=pricing!=='per_person';
  form.dataset.servicePricing=pricing;
  syncServiceScheduleUi(form);
}
$$('[data-service-select]').forEach(select=>{select.addEventListener('change',()=>syncServicePricing(select,true));syncServicePricing(select,false)});

/* Service schedule UI: selected stay owns the allowed execution range; time stays optional but never exists without a date. */
function syncServiceScheduleUi(form){
  const date=form.querySelector('[data-service-date]'),hint=form.querySelector('[data-service-date-hint]'),dateLabel=form.querySelector('[data-service-date-label]'),service=form.querySelector('[data-service-select]');if(!date)return;
  const timed=service?service.selectedOptions?.[0]?.dataset.isTimed==='1':form.dataset.serviceTimed==='1',range=String(hint?.dataset.serviceRangeLabel||'').trim();
  date.required=timed;
  date.dataset.serviceTimed=timed?'1':'0';
  if(dateLabel)dateLabel.textContent=timed?'تاریخ اجرا *':'تاریخ اجرا';
  if(hint){
    if(date.disabled)hint.textContent=range||'ابتدا اقامت مهمان را انتخاب کنید.';
    else hint.textContent=`${range}${range?' · ':''}${timed?'تاریخ اجرا برای این خدمت الزامی است.':'اگر هنوز مشخص نیست، خالی بگذارید.'}`;
  }
}
function syncServiceSchedule(form){
  const date=form.querySelector('[data-service-date]'),time=form.querySelector('[data-service-time]');if(!date||!time)return;
  syncServiceScheduleUi(form);
  const invalid=time.value.trim()!==''&&date.value.trim()==='';
  time.setCustomValidity(invalid?'برای ثبت ساعت، ابتدا تاریخ اجرا را انتخاب کنید.':'');
}
function syncServiceReservationDate(select,resetDate=false){
  const form=select.closest('[data-service-schedule-form]'),date=form?.querySelector('[data-service-date]'),hint=form?.querySelector('[data-service-date-hint]'),opt=select.selectedOptions[0];if(!form||!date)return;
  const min=opt?.value?String(opt.dataset.minIso||''):'',max=opt?.value?String(opt.dataset.maxIso||''):'',range=opt?.value?String(opt.dataset.dateRangeLabel||''):'';
  if(min)date.dataset.minIso=min;else delete date.dataset.minIso;if(max)date.dataset.maxIso=max;else delete date.dataset.maxIso;
  date.disabled=!(min&&max);
  if(hint)hint.dataset.serviceRangeLabel=range||'ابتدا اقامت مهمان را انتخاب کنید.';
  if(resetDate){date.value='';date.dispatchEvent(new Event('change',{bubbles:true}))}
  syncServiceSchedule(form);
}
$$('[data-service-schedule-form]').forEach(form=>{
  const date=form.querySelector('[data-service-date]'),time=form.querySelector('[data-service-time]'),reservation=form.querySelector('[data-service-reservation-select]');
  reservation?.addEventListener('change',()=>syncServiceReservationDate(reservation,true));
  date?.addEventListener('change',()=>syncServiceSchedule(form));time?.addEventListener('input',()=>syncServiceSchedule(form));
  if(reservation)syncServiceReservationDate(reservation,false);else syncServiceSchedule(form);
});

/* Rate override: labels and per-room result preview share the same pricing semantics as the server. */
function bindRateForm(form){
  if(form.dataset.rateBound)return;form.dataset.rateBound='1';
  const labels={room:form.querySelector('[data-rate-amount-label="room"]'),extra:form.querySelector('[data-rate-amount-label="extra"]')};
  const baseInput=form.querySelector('input[name="base_rate"]'),extraInput=form.querySelector('input[name="extra_person_rate"]');
  const preview=form.querySelector('[data-rate-preview]'),previewList=form.querySelector('[data-rate-preview-list]'),previewEmpty=form.querySelector('[data-rate-preview-empty]');
  const proposed=(current,value,mode)=>mode==='increase'?current+value:mode==='decrease'?Math.max(0,current-value):value;
  const sync=()=>{
    const mode=form.querySelector('input[name="rate_mode"]:checked')?.value||'fixed';
    const prefix=mode==='increase'?'میزان افزایش':mode==='decrease'?'میزان کاهش':'قیمت نهایی';
    if(labels.room)labels.room.textContent=`${prefix} اتاق`;
    if(labels.extra)labels.extra.textContent=`${prefix} نفر اضافه`;
    if(!preview||!previewList)return;
    const roomValue=Number(digits(baseInput?.value||'')||0),extraValue=Number(digits(extraInput?.value||'')||0);
    const selected=$$('[data-rate-room]:checked',form);
    previewList.replaceChildren();
    if(previewEmpty)previewEmpty.hidden=selected.length>0;
    selected.forEach(input=>{
      const name=input.dataset.roomName||'اتاق',currentBase=Number(input.dataset.roomBase||0),currentExtra=Number(input.dataset.roomExtra||0);
      const nextBase=proposed(currentBase,roomValue,mode),nextExtra=proposed(currentExtra,extraValue,mode);
      const row=document.createElement('div');row.className='rate-preview-row';
      const copy=document.createElement('span'),title=document.createElement('strong'),detail=document.createElement('small'),amount=document.createElement('b');
      title.textContent=name;
      detail.textContent=`نرخ پایه ${money(currentBase)} → ${money(nextBase)} تومان`;
      amount.textContent=`نفر اضافه ${money(nextExtra)} تومان`;
      copy.append(title,detail);row.append(copy,amount);previewList.append(row);
    });
  };
  form.querySelectorAll('input[name="rate_mode"],[data-rate-room]').forEach(input=>input.addEventListener('change',sync));
  [baseInput,extraInput].forEach(input=>input?.addEventListener('input',sync));
  sync();
}
function refresh(scope=document){
  $$('.money-input',scope).forEach(bindMoney);
  $$('[data-number-stepper]',scope).forEach(bindStepper);
  $$('input[inputmode="numeric"]:not(.money-input):not([data-number-stepper] input)',scope).forEach(bindNumeric);
  $$('[data-file-picker]',scope).forEach(bindFile);
  $$('[data-mobile-input]',scope).forEach(bindMobile);
  $$('.rate-form',scope).forEach(bindRateForm);
}
refresh();
window.SoknaForms=Object.freeze({bindMoney,refresh});
})();

(()=>{
'use strict';
/* Shared media library relationship rules use delegation so dynamically refreshed room workspaces stay bound. */
document.addEventListener('change',e=>{
  const target=e.target;if(!(target instanceof HTMLInputElement))return;
  const item=target.closest('[data-media-library-item]');if(!item)return;
  const check=item.querySelector('[data-media-library-check]'),cover=item.querySelector('[data-media-library-cover]');if(!check||!cover)return;
  if(target.matches('[data-media-library-cover]')&&cover.checked&&!check.checked){check.checked=true;check.dispatchEvent(new Event('change',{bubbles:true}));return}
  if(target.matches('[data-media-library-check]')&&!check.checked&&cover.checked)cover.checked=false;
});
})();
