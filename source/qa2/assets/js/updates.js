(()=>{
'use strict';
try{window.__SoknaUpdatesLifecycle?.abort()}catch(_){}
const lifecycle=new AbortController();window.__SoknaUpdatesLifecycle=lifecycle;const signal=lifecycle.signal;
const {$}=window.SoknaCore;
const runner=$('[data-update-runner]');
if(!runner)return;
const progress=$('[data-update-progress]',runner),percentBadge=$('.panel-head .badge',runner),message=$('[data-update-message]',runner),stateText=$('[data-update-state]',runner),manual=$('[data-update-manual]',runner);
let busy=false,stopped=runner.dataset.state==='rollback_failed';
const faDigits=value=>String(value).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
const clampProgress=value=>Math.max(0,Math.min(100,Number(value)||0));
const setStatus=u=>{
  runner.dataset.state=u.state||'';
  const percent=clampProgress(u.progress);
  if(progress){progress.value=percent;progress.textContent=`${percent}%`;progress.setAttribute('aria-valuenow',String(percent))}
  if(percentBadge)percentBadge.textContent=`${faDigits(percent)}٪`;
  if(message)message.textContent=u.message||'';
  if(stateText)stateText.textContent=(u.state==='success'?'به‌روزرسانی کامل شد':u.state==='rolled_back'?'نسخه قبلی بازیابی شد':u.state==='rollback_failed'?'بازیابی نیازمند تلاش دوباره است':'در حال اجرا');
  stopped=u.state==='rollback_failed';if(manual)manual.hidden=!stopped;
  if(u.state==='success'||u.state==='rolled_back')setTimeout(()=>{if(signal.aborted)return;/* A release changes the JS/CSS contract and VERSION; use a full navigation instead of reusing an old SPA shell. */const href=`${window.SOKNA.baseUrl}/index.php?page=updates`;location.assign(href)},650);
};
async function step(){
  if(signal.aborted||busy||stopped)return;busy=true;
  try{const body=new FormData();body.set('csrf',runner.dataset.csrf||'');body.set('operation',runner.dataset.operation||'');const res=await fetch(runner.dataset.action,{method:'POST',body,credentials:'same-origin',headers:{Accept:'application/json'},signal});const json=await res.json();if(!res.ok||!json.ok)throw new Error(json.message||'update');setStatus(json.update);if(!signal.aborted&&!['success','rolled_back','rollback_failed'].includes(json.update.state))setTimeout(step,180)}catch(_){stopped=true;if(stateText)stateText.textContent='ارتباط قطع شد؛ برای ادامه دکمه زیر را بزنید.';if(manual)manual.hidden=false}finally{busy=false}
}
manual?.addEventListener('click',()=>{stopped=false;manual.hidden=true;step()});
if(!stopped&&!signal.aborted)setTimeout(step,120);
})();
