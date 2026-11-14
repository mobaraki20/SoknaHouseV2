(()=>{
'use strict';
const {$,$$}=window.SoknaCore;
const overlay=window.SoknaOverlay;
function toast(type,message,delay=4500){let stack=$('.toast-stack');if(!stack){stack=document.createElement('div');stack.className='toast-stack';stack.setAttribute('aria-live','polite');document.body.append(stack)}const el=document.createElement('div');el.className=`toast ${type}`;el.textContent=message;stack.append(el);setTimeout(()=>{el.classList.add('leaving');setTimeout(()=>el.remove(),220)},delay)}
function refresh(scope=document){
  $$('form[data-confirm]',scope).forEach(form=>{if(form.dataset.confirmBound)return;form.dataset.confirmBound='1';form.addEventListener('submit',async e=>{if(form.dataset.confirmed==='1'){delete form.dataset.confirmed;return}e.preventDefault();const ok=await overlay.ask(form.dataset.confirm||'این عملیات انجام شود؟',{danger:!!form.querySelector('.danger'),trigger:e.submitter||form});if(ok){form.dataset.confirmed='1';form.requestSubmit(e.submitter||undefined)}})});
  $$('.flash-stack .flash',scope).forEach(el=>{const type=el.classList.contains('error')?'error':el.classList.contains('success')?'success':'info';toast(type,el.textContent||'');el.remove()});
  $$('[data-ui-dialog-preview]',scope).forEach(btn=>{if(btn.dataset.previewBound)return;btn.dataset.previewBound='1';btn.addEventListener('click',()=>overlay.ask('این نمونه قرارداد پنجره مشترک سکناست.',{title:'پنجره استاندارد',trigger:btn}))});
  $$('[data-ui-toast-preview]',scope).forEach(btn=>{if(btn.dataset.previewBound)return;btn.dataset.previewBound='1';btn.addEventListener('click',()=>toast('success','نمونه اعلان کوتاه.'))});
  $$('[data-password-toggle]',scope).forEach(btn=>{if(btn.dataset.passwordBound)return;btn.dataset.passwordBound='1';btn.addEventListener('click',()=>{const input=btn.closest('.password-wrap')?.querySelector('[data-password]');if(!input)return;const showing=input.type==='text';input.type=showing?'password':'text';btn.textContent=showing?'نمایش':'پنهان'})});
  $$('[data-copy-text]',scope).forEach(btn=>{if(btn.dataset.copyBound)return;btn.dataset.copyBound='1';btn.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(btn.dataset.copyText||'');toast('success','کپی شد.')}catch(_){toast('error','کپی خودکار انجام نشد. متن را دستی کپی کنید.')}})});
}
refresh();
window.SoknaUI=Object.freeze({toast,refresh});
})();
