(()=>{
'use strict';
try{window.__SoknaRoomsLifecycle?.abort();window.__SoknaRoomsRequestController?.abort()}catch(_){}
const lifecycle=new AbortController();window.__SoknaRoomsLifecycle=lifecycle;const signal=lifecycle.signal;
const core=window.SoknaCore||{},{$,$$,digits,money,fa}=core;if(!$||!$$)return;
const overlay=window.SoknaOverlay,ROOT_SELECTOR='[data-rooms-v3]';
let activeRoot=$(ROOT_SELECTOR),requestController=null,navSerial=0;
if(!activeRoot)return;

function snapshot(form){const out=[];for(const [key,value] of new FormData(form).entries()){if(key==='csrf'||value instanceof File)continue;out.push(`${key}=${value}`)}return out.sort().join('&')}
function roomsUrl(href){try{const url=new URL(href,location.href);if(url.origin!==location.origin)return null;const page=url.searchParams.get('page')||'';return page==='rooms'?url:null}catch(_){return null}}
function stateWithScroll(y=window.scrollY){const state=history.state&&typeof history.state==='object'?{...history.state}:{};state.soknaRooms={scrollY:Math.max(0,Math.round(y))};return state}
function rememberScroll(){try{history.replaceState(stateWithScroll(),'',location.href)}catch(_){}}
function importNode(doc,selector){const node=doc.querySelector(selector);return node?document.importNode(node,true):null}
function focusHash(url){if(!url.hash)return false;try{const id=decodeURIComponent(url.hash.slice(1)),target=document.getElementById(id)||document.getElementsByName(id)[0];if(!target)return false;target.scrollIntoView({block:'start'});return true}catch(_){return false}}

async function loadWorkspace(url,{push=true,restoreY=null}={}){
  const current=$(ROOT_SELECTOR);if(!current)return false;
  const sameDocument=url.pathname===location.pathname&&url.search===location.search;
  if(sameDocument&&url.hash){if(push)rememberScroll();focusHash(url);if(push)history.pushState(stateWithScroll(window.scrollY),'',url.href);return true}
  const serial=++navSerial;requestController?.abort();requestController=new AbortController();window.__SoknaRoomsRequestController=requestController;current.setAttribute('aria-busy','true');
  try{
    const response=await fetch(url.href,{method:'GET',credentials:'same-origin',signal:requestController.signal,headers:{Accept:'text/html','X-Sokna-Partial':'rooms-v3'}});
    if(!response.ok)throw new Error(`rooms_soft_nav_http_${response.status}`);
    const html=await response.text();if(serial!==navSerial)return true;
    const doc=new DOMParser().parseFromString(html,'text/html'),nextRoot=importNode(doc,ROOT_SELECTOR);if(!nextRoot)throw new Error('rooms_soft_nav_missing_workspace');
    const nextTitle=importNode(doc,'.page-title'),currentTitle=$('.page-title');
    /* Each new rooms view starts at its own top; the previous view's position
     * is preserved in its history entry for Back. */
    const targetY=restoreY===null?0:Math.max(0,restoreY);
    if(push){rememberScroll();history.pushState(stateWithScroll(targetY),'',url.href)}
    current.replaceWith(nextRoot);if(nextTitle&&currentTitle)currentTitle.replaceWith(nextTitle);if(doc.title)document.title=doc.title;
    const hasTarget=focusHash(url);
    if(!hasTarget)window.scrollTo({top:targetY,left:0,behavior:'instant'});
    activeRoot=nextRoot;window.SoknaForms?.refresh?.(nextRoot);bindWorkspace(nextRoot);
    await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
    if(url.hash)focusHash(url); // Targets can be initialized during workspace binding.
    else if(!push&&restoreY!==null&&Math.abs(window.scrollY-targetY)>1)
      window.scrollTo({top:targetY,left:0,behavior:'instant'});
    return true;
  }catch(error){
    if(error?.name==='AbortError')return true;
    current.removeAttribute('aria-busy');return false;
  }
}
async function follow(link,options={}){const url=roomsUrl(link.href);if(!url)return false;const ok=await loadWorkspace(url,options);if(!ok)location.href=link.href;return true}

function bindWorkspace(root){
  let profileDirty=()=>false,galleryDirty=()=>false,priceDirty=()=>false;
  const profileForm=$('[data-room-profile-form]',root),galleryForm=$('[data-room-gallery-form]',root);
  if(galleryForm){galleryForm.dataset.initialSnapshot=snapshot(galleryForm);galleryDirty=()=>galleryForm.dataset.mediaLocalDirty==='1'||snapshot(galleryForm)!==(galleryForm.dataset.initialSnapshot||'')}
  if(profileForm){
    profileForm.dataset.initialSnapshot=snapshot(profileForm);
    const standard=profileForm.querySelector('input[name="standard_capacity"]'),max=profileForm.querySelector('input[name="max_capacity"]'),double=profileForm.querySelector('input[name="double_beds"]'),single=profileForm.querySelector('input[name="single_beds"]'),profile=profileForm.querySelector('input[name="public_profile_enabled"]');
    const sync=()=>{if(!standard||!max||!double||!single)return;const base=Number(digits(standard.value)||0),maximum=Number(digits(max.value)||0),beds=Number(digits(double.value)||0)*2+Number(digits(single.value)||0),publicOn=profile?.checked===true;if(base>maximum){max.value=fa(String(base));max.dispatchEvent(new Event('input',{bubbles:true}))}standard.setCustomValidity(publicOn&&base>beds?'ظرفیت پایه نمی‌تواند از ظرفیت تخت‌های ثبت‌شده بیشتر باشد.':'');const bedError=publicOn&&beds<1?'برای پروفایل عمومی حداقل یک تخت ثبت کنید.':'';double.setCustomValidity(bedError);single.setCustomValidity(bedError)};
    [standard,max,double,single].forEach(input=>input?.addEventListener('input',sync));profile?.addEventListener('change',sync);sync();profileDirty=()=>snapshot(profileForm)!==(profileForm.dataset.initialSnapshot||'');
  }
  $$('[data-room-gallery-toggle]',root).forEach(button=>button.addEventListener('click',()=>{const item=button.closest('[data-room-gallery-item]');if(!item)return;const gallery=item.closest('[data-room-gallery]')||root,active=item.classList.contains('is-active');$$('[data-room-gallery-item]',gallery).forEach(x=>{x.classList.remove('is-active');x.querySelector('[data-room-gallery-toggle]')?.setAttribute('aria-expanded','false')});if(!active){item.classList.add('is-active');button.setAttribute('aria-expanded','true')}}));
  // رسانه اتاق: هر فایل مستقل بارگذاری می‌شود؛ شکست یک فایل، تصاویر موفق را برنمی‌گرداند.
  if(galleryForm){
    const dialog=$('[data-media-picker-dialog]',galleryForm),file=$('[data-file-picker]',galleryForm),fileName=$('[data-file-picker-name]',galleryForm),queue=$('[data-media-upload-queue]',galleryForm),queueSummary=$('[data-media-upload-summary]',galleryForm),queueState=$('[data-media-upload-state]',galleryForm),queueProgress=$('[data-media-upload-progress]',galleryForm),queueList=$('[data-media-upload-list]',galleryForm),apply=$('[data-media-picker-apply]',galleryForm),applyLabel=$('[data-media-picker-apply-label]',galleryForm),pickerNote=$('[data-media-picker-note]',galleryForm),uploadUrl=galleryForm.dataset.mediaUploadUrl||'',csrf=galleryForm.querySelector('input[name="csrf"]')?.value||'',currentCount=Number(galleryForm.dataset.currentCount||0),tasks=[];let uploadLoop=false;
    const libraryChecks=()=>$$('[data-media-library-check]',galleryForm),libraryCount=()=>libraryChecks().filter(input=>input.checked).length,deleteCount=()=>$$('input[name="delete_image_ids[]"]:checked',galleryForm).length,successCount=()=>tasks.filter(task=>task.state==='success').length,busyCount=()=>tasks.filter(task=>task.state==='pending'||task.state==='uploading'||task.state==='processing').length,failedCount=()=>tasks.filter(task=>task.state==='failed').length;
    const selectedAdditions=()=>libraryCount()+successCount();
    const reservedAdditions=()=>libraryCount()+tasks.length;
    const roomTotal=()=>Math.max(0,currentCount-deleteCount())+reservedAdditions();
    const taskKey=f=>`${f.name}|${f.size}|${f.lastModified}`;
    const uploadedHidden=id=>galleryForm.querySelector(`input[data-media-uploaded-id="${CSS.escape(String(id))}"]`);
    const addUploadedId=id=>{if(uploadedHidden(id))return;const input=document.createElement('input');input.type='hidden';input.name='library_media_ids[]';input.value=String(id);input.dataset.mediaUploadedId=String(id);galleryForm.append(input)};
    const removeUploadedId=id=>uploadedHidden(id)?.remove();
    const stateText=task=>task.state==='pending'?'در صف':task.state==='uploading'?`${fa(task.progress)}٪ در حال بارگذاری`:task.state==='processing'?'در حال پردازش':task.state==='success'?'آماده ✓':task.message||'بارگذاری ناموفق';
    const renderQueue=()=>{
      if(!queue||!queueList)return;queue.hidden=tasks.length===0;queueList.replaceChildren();
      for(const task of tasks){
        const row=document.createElement('li');row.className='media-upload-item';const visualState={success:'is-success',failed:'is-failed'}[task.state];if(visualState)row.classList.add(visualState);row.dataset.taskId=task.id;
        const thumb=document.createElement('img');thumb.src=task.preview;thumb.alt='';thumb.className='media-upload-thumb';
        const copy=document.createElement('div');copy.className='media-upload-copy';const name=document.createElement('b');name.textContent=task.file.name;const state=document.createElement('small');state.textContent=stateText(task);copy.append(name,state);
        const actions=document.createElement('div');actions.className='media-upload-row-actions';
        if(task.state==='failed'){const retry=document.createElement('button');retry.type='button';retry.className='btn ghost small';retry.dataset.mediaRetry=task.id;retry.textContent='تلاش دوباره';const discard=document.createElement('button');discard.type='button';discard.className='btn ghost small';discard.dataset.mediaDiscard=task.id;discard.textContent='کنار گذاشتن';actions.append(retry,discard)}
        row.append(thumb,copy,actions);queueList.append(row);
      }
      const total=tasks.length,ready=successCount(),failed=failedCount(),busy=busyCount(),progressTotal=total?Math.round(tasks.reduce((sum,task)=>sum+(task.state==='success'?100:task.progress||0),0)/total):0;
      if(queueSummary)queueSummary.textContent=total?`${fa(total)} تصویر • ${fa(ready)} آماده${failed?` • ${fa(failed)} ناموفق`:''}`:'تصویری در صف نیست.';
      if(queueState)queueState.textContent=busy?`${fa(progressTotal)}٪`:failed?'نیاز به بررسی':'آماده';
      if(queueProgress){queueProgress.hidden=!busy;queueProgress.value=progressTotal;queueProgress.textContent=`${fa(progressTotal)}٪`}
      const additions=selectedAdditions();if(apply){apply.disabled=busy>0||additions===0;apply.setAttribute('aria-label',additions?`افزودن ${fa(additions)} تصویر آماده به اتاق`:'تصویری برای افزودن آماده نیست')}
      if(applyLabel)applyLabel.textContent=additions?`افزودن ${fa(additions)} تصویر`:'افزودن تصاویر آماده';
      if(pickerNote)pickerNote.textContent=busy?'بارگذاری را کامل کنید؛ می‌توانید این پنجره را ببندید و فرایند ادامه پیدا می‌کند.':failed&&ready?`${fa(ready)} تصویر آماده است و ${fa(failed)} تصویر را می‌توانید دوباره تلاش کنید.`:failed?'تصویر ناموفق را دوباره تلاش کنید یا تصویر دیگری انتخاب کنید.':additions?`${fa(additions)} تصویر آماده افزودن به اتاق است.`:'تصاویر جدید پس از انتخاب، خودکار بارگذاری می‌شوند.';
      if(fileName){fileName.textContent=total?`${fa(total)} تصویر در این نوبت`:'تصویری انتخاب نشده'}
      galleryForm.dataset.mediaLocalDirty=tasks.some(task=>task.state!=='success')?'1':'';
    };
    const toast=(type,message)=>window.SoknaUI?.toast?.(type,message);
    const uploadOne=task=>new Promise(resolve=>{
      if(!uploadUrl){task.state='failed';task.message='مسیر بارگذاری در دسترس نیست.';renderQueue();resolve();return}
      task.state='uploading';task.progress=0;task.message='';renderQueue();
      const form=new FormData();form.append('csrf',csrf);form.append('media_file',task.file,task.file.name);
      const xhr=new XMLHttpRequest();task.xhr=xhr;xhr.open('POST',uploadUrl,true);xhr.responseType='json';xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
      xhr.upload.addEventListener('progress',event=>{if(event.lengthComputable){task.progress=Math.max(0,Math.min(99,Math.round((event.loaded/event.total)*100)));renderQueue()}});
      xhr.upload.addEventListener('load',()=>{task.state='processing';task.progress=100;renderQueue()});
      const fail=message=>{task.state='failed';task.progress=0;task.message=message||'بارگذاری انجام نشد.';task.xhr=null;renderQueue();resolve()};
      xhr.addEventListener('error',()=>fail('اتصال هنگام بارگذاری قطع شد.'));
      xhr.addEventListener('abort',()=>fail('بارگذاری متوقف شد.'));
      xhr.addEventListener('load',()=>{
        const payload=xhr.response&&typeof xhr.response==='object'?xhr.response:(()=>{try{return JSON.parse(xhr.responseText||'{}')}catch(_){return {}}})();
        if(xhr.status<200||xhr.status>=300||!payload?.ok||!payload?.asset?.id){fail(payload?.message||'بارگذاری تصویر انجام نشد.');return}
        task.state='success';task.progress=100;task.mediaId=Number(payload.asset.id);task.message='';task.xhr=null;addUploadedId(task.mediaId);galleryForm.dataset.mediaLocalDirty='';renderQueue();resolve();
      });
      xhr.send(form);
    });
    const uploadPending=async()=>{if(uploadLoop)return;uploadLoop=true;try{for(const task of tasks)if(task.state==='pending')await uploadOne(task)}finally{uploadLoop=false;renderQueue()}};
    const addFiles=selected=>{
      const known=new Set(tasks.map(task=>task.key));let available=Math.max(0,6-roomTotal()),added=0,skipped=0;
      for(const entry of selected){const key=taskKey(entry);if(known.has(key)){skipped++;continue}if(available<=0){skipped++;continue}const task={id:`m${Date.now().toString(36)}${Math.random().toString(36).slice(2,7)}`,key,file:entry,preview:URL.createObjectURL(entry),state:'pending',progress:0,message:'',mediaId:0,xhr:null};tasks.push(task);known.add(key);available--;added++}
      if(file)file.value='';if(skipped)toast('info','حداکثر ۶ تصویر برای هر اتاق نگه دارید؛ فایل‌های اضافه وارد صف نشدند.');if(added){galleryForm.dataset.mediaLocalDirty='1';renderQueue();uploadPending()}else renderQueue();
    };
    file?.addEventListener('change',()=>addFiles(Array.from(file.files||[])));
    queueList?.addEventListener('click',event=>{const retry=event.target.closest?.('[data-media-retry]'),discard=event.target.closest?.('[data-media-discard]');if(retry){const task=tasks.find(item=>item.id===retry.dataset.mediaRetry);if(!task||task.state!=='failed')return;task.state='pending';task.message='';galleryForm.dataset.mediaLocalDirty='1';renderQueue();uploadPending();return}if(discard){const index=tasks.findIndex(item=>item.id===discard.dataset.mediaDiscard);if(index<0)return;const [task]=tasks.splice(index,1);task.xhr?.abort?.();if(task.mediaId)removeUploadedId(task.mediaId);if(task.preview)URL.revokeObjectURL(task.preview);galleryForm.dataset.mediaLocalDirty=tasks.some(item=>item.state!=='success')?'1':'';renderQueue()}});
    galleryForm.addEventListener('change',event=>{
      if(event.target?.matches?.('[data-media-library-check]')&&event.target.checked&&roomTotal()>6){event.target.checked=false;toast('error','برای هر اتاق حداکثر ۶ تصویر نگه دارید.')}
      if(event.target?.matches?.('[data-media-library-check],input[name="delete_image_ids[]"],[data-media-library-cover]'))renderQueue();
    });
    galleryForm.addEventListener('submit',event=>{if(busyCount()>0){event.preventDefault();toast('info','بارگذاری تصاویر هنوز کامل نشده است.');renderQueue();return}for(const task of tasks)if(task.state==='failed'&&task.mediaId)removeUploadedId(task.mediaId)});
    renderQueue();
    if(dialog){
      const open=$(`[data-media-picker-open][aria-controls="${dialog.id}"]`,root),search=$('[data-media-search]',dialog),items=$$('[data-media-library-item]',dialog),empty=$('[data-media-search-empty]',dialog);let returnFocus=null;
      const close=()=>window.SoknaOverlay?.closeModal?.(dialog,'cancel');
      open?.addEventListener('click',()=>{returnFocus=open;if(!window.SoknaOverlay?.showModal?.(dialog))return;requestAnimationFrame(()=>file?.focus()||search?.focus())});
      $$('[data-media-picker-close]',dialog).forEach(button=>button.addEventListener('click',close));dialog.addEventListener('click',event=>{if(event.target===dialog)close()});dialog.addEventListener('cancel',event=>{event.preventDefault();close()});dialog.addEventListener('close',()=>returnFocus?.focus());
      search?.addEventListener('input',()=>{const query=search.value.trim().toLocaleLowerCase('fa-IR');let visible=0;items.forEach(item=>{const name=(item.dataset.mediaName||item.textContent||'').toLocaleLowerCase('fa-IR'),show=query===''||name.includes(query);item.hidden=!show;if(show)visible++});if(empty)empty.hidden=visible>0});
    }
  }
  const priceForm=$('[data-room-price-form]',root);if(priceForm){priceForm.dataset.initialSnapshot=snapshot(priceForm);priceDirty=()=>snapshot(priceForm)!==(priceForm.dataset.initialSnapshot||'')}
  const bulkForm=$('[data-room-bulk-form]',root);if(bulkForm){const checks=$$('[data-bulk-room]',bulkForm),copy=$('[data-bulk-selection-copy]',bulkForm),list=$('[data-bulk-preview-list]',bulkForm),empty=$('[data-bulk-preview-empty]',bulkForm),submit=$('[data-bulk-submit]',bulkForm);const calc=(current,amount,mode)=>mode==='increase'?current+amount:mode==='decrease'?Math.max(0,current-amount):amount;const sync=()=>{const selected=checks.filter(c=>c.checked),target=bulkForm.querySelector('input[name="target"]:checked')?.value||'base_rate',mode=bulkForm.querySelector('input[name="mode"]:checked')?.value||'fixed',amount=Number(digits(bulkForm.querySelector('input[name="amount"]')?.value||'0')||0);let applicable=0;if(copy)copy.textContent=selected.length?`${fa(selected.length)} اتاق انتخاب شده`:'اتاق‌ها را انتخاب کنید';if(list)list.replaceChildren();if(empty)empty.hidden=selected.length>0;selected.forEach(input=>{const name=input.dataset.roomName||'اتاق',hasExtra=input.dataset.hasExtra==='1',current=Number(target==='base_rate'?input.dataset.roomBase:input.dataset.roomExtra)||0,row=document.createElement('div');row.className='rate-preview-row';const body=document.createElement('span'),title=document.createElement('strong'),detail=document.createElement('small'),result=document.createElement('b');title.textContent=name;if(target==='extra_person_rate'&&!hasExtra){detail.textContent='ظرفیت اضافه ندارد';result.textContent='اعمال نمی‌شود';row.classList.add('not-applicable')}else{const next=calc(current,amount,mode);detail.textContent=`${money(current)} ← ${money(next)} تومان`;result.textContent=target==='base_rate'?'نرخ پایه':'نفر اضافه';applicable++}body.append(title,detail);row.append(body,result);list?.append(row)});if(submit){submit.disabled=applicable===0;submit.textContent=applicable?`اعمال روی ${fa(applicable)} اتاق`:'اعمال تغییر گروهی'}};checks.forEach(i=>i.addEventListener('change',sync));bulkForm.querySelectorAll('input[name="target"],input[name="mode"]').forEach(i=>i.addEventListener('change',sync));bulkForm.querySelector('input[name="amount"]')?.addEventListener('input',sync);sync()}
  root.addEventListener('click',async e=>{if(e.defaultPrevented||e.button!==0||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey)return;const link=e.target.closest?.('a[href]');if(!link||!root.contains(link)||link.target||link.hasAttribute('download'))return;const url=roomsUrl(link.href);if(!url)return;e.preventDefault();const dirty=profileDirty()||galleryDirty()||priceDirty();if(dirty&&overlay){const ok=await overlay.ask('تغییرات این بخش هنوز ذخیره نشده‌اند. بدون ذخیره ادامه دهیم؟',{title:'تغییر ذخیره‌نشده',trigger:link});if(!ok)return}await follow(link,{push:true})});
}

bindWorkspace(activeRoot);window.SoknaForms?.refresh?.(activeRoot);try{history.replaceState(stateWithScroll(),'',location.href)}catch(_){}
window.addEventListener('popstate',async e=>{const url=roomsUrl(location.href);if(!url||!$(ROOT_SELECTOR))return;const y=Number(e.state?.soknaRooms?.scrollY);const ok=await loadWorkspace(url,{push:false,restoreY:Number.isFinite(y)?y:0});if(!ok)location.reload()},{signal});
window.addEventListener('pagehide',rememberScroll,{signal});
})();
