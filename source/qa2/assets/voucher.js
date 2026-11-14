(()=>{
'use strict';
const root=document.querySelector('[data-voucher-document]');
if(!root)return;
const body=document.body;
const status=document.querySelector('[data-voucher-status]');
const mode=body?.dataset.voucherMode==='receipt'?'receipt':'a5';
const baseName=(body?.dataset.pdfName||'Sokna-Voucher.pdf').replace(/[\\/:*?"<>|]+/g,'-');
const pdfName=mode==='receipt'?baseName.replace(/\.pdf$/i,'-80mm.pdf'):baseName;
const exportButtons=[...document.querySelectorAll('[data-download-pdf],[data-share-pdf]')];
const moreTrigger=document.querySelector('[data-voucher-more-trigger]');
const moreMenu=document.getElementById('voucherMoreMenu');
let timer=0,cachedPdf=null,pdfPromise=null,exportFontPromise=null;

const tell=message=>{
  if(!status)return;
  status.textContent=message;
  status.classList.add('show');
  clearTimeout(timer);
  timer=window.setTimeout(()=>status.classList.remove('show'),3200);
};
const setBusy=busy=>exportButtons.forEach(button=>{button.disabled=busy;button.setAttribute('aria-busy',busy?'true':'false');});
const nextFrame=()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
const blobToDataUrl=blob=>new Promise((resolve,reject)=>{const reader=new FileReader();reader.onload=()=>resolve(String(reader.result));reader.onerror=()=>reject(reader.error);reader.readAsDataURL(blob);});

const exportFontDataUrl=()=>{
  if(exportFontPromise)return exportFontPromise;
  exportFontPromise=(async()=>{
    try{
      const url=new URL(body?.dataset.voucherFontUrl||'font.php?v=33.003',document.baseURI);
      const response=await fetch(url,{credentials:'same-origin',cache:'force-cache'});
      if(!response.ok)throw new Error('voucher_font_fetch_failed');
      return await blobToDataUrl(await response.blob());
    }catch(error){
      console.warn('Voucher export font could not be embedded; browser fallback will be used.',error);
      return '';
    }
  })();
  return exportFontPromise;
};

const positionMoreMenu=()=>{
  if(!moreTrigger||!moreMenu||!moreMenu.matches(':popover-open'))return;
  const trigger=moreTrigger.getBoundingClientRect();
  const menu=moreMenu.getBoundingClientRect();
  const edge=8,gap=8;
  let left=trigger.left;
  left=Math.max(edge,Math.min(left,window.innerWidth-menu.width-edge));
  let top=trigger.top-menu.height-gap;
  if(top<edge)top=Math.min(window.innerHeight-menu.height-edge,trigger.bottom+gap);
  top=Math.max(edge,top);
  moreMenu.style.left=`${Math.round(left)}px`;
  moreMenu.style.top=`${Math.round(top)}px`;
};

if(moreTrigger&&moreMenu){
  moreMenu.addEventListener('toggle',event=>{
    const open=event.newState==='open';
    moreTrigger.setAttribute('aria-expanded',open?'true':'false');
    if(open)requestAnimationFrame(positionMoreMenu);
  });
  window.addEventListener('resize',positionMoreMenu,{passive:true});
  window.addEventListener('scroll',positionMoreMenu,{passive:true});
}

const inlineImageSources=async(source,clone)=>{
  const originals=[...source.querySelectorAll('img')],copies=[...clone.querySelectorAll('img')];
  await Promise.all(originals.map(async(img,index)=>{
    const copy=copies[index];if(!copy)return;
    const src=img.currentSrc||img.src||'';if(!src||src.startsWith('data:')){if(src)copy.src=src;return;}
    try{
      const response=await fetch(src,{credentials:'same-origin',cache:'force-cache'});
      if(!response.ok)return;
      copy.src=await blobToDataUrl(await response.blob());
    }catch(_error){/* Keep original src/alt as a graceful fallback. */}
  }));
};

const copyComputedStyles=(source,clone)=>{
  const originals=[source,...source.querySelectorAll('*')],copies=[clone,...clone.querySelectorAll('*')];
  originals.forEach((element,index)=>{
    const target=copies[index];if(!target)return;
    const style=getComputedStyle(element);let css='';
    for(let i=0;i<style.length;i++){
      const name=style.item(i);if(!name)continue;
      const value=style.getPropertyValue(name);if(value)css+=`${name}:${value};`;
    }
    target.setAttribute('style',css);
  });
};

// PDF export is a paper document, not a snapshot of the responsive preview.
// Render A5 in an isolated 900 CSS-pixel viewport so mobile media queries cannot
// change the layout or turn one ordinary A5 document into two pages.
const createA5ExportSource=async()=>{
  const stage=document.createElement('iframe');
  stage.className='voucher-export-iframe';
  stage.setAttribute('title','ساخت ووچر PDF');
  stage.setAttribute('aria-hidden','true');
  stage.tabIndex=-1;
  stage.style.cssText='position:fixed;left:-12000px;top:0;width:900px;height:1200px;border:0;pointer-events:none;z-index:-1;';
  // Reuse the actual release stylesheets (and any inline styles), including
  // the fixed light export palette, in the independent document viewport.
  const headStyles=[...document.head.querySelectorAll('link[rel="stylesheet"],style')].map(item=>item.outerHTML).join('');
  const base=String(document.baseURI).replaceAll('&','&amp;').replaceAll('"','&quot;');
  const html=`<!doctype html><html lang="fa-IR" dir="rtl"><head><meta charset="utf-8"><base href="${base}">${headStyles}</head><body class="voucher voucher-a5 voucher-export-light" style="margin:0;padding:0;background:#fff;color-scheme:light"></body></html>`;
  try{
    const loaded=new Promise((resolve,reject)=>{
      stage.addEventListener('load',resolve,{once:true});
      stage.addEventListener('error',()=>reject(new Error('voucher_export_frame_failed')),{once:true});
    });
    stage.srcdoc=html;
    body.append(stage);
    await loaded;
    const doc=stage.contentDocument;
    if(!doc?.body)throw new Error('voucher_export_frame_unavailable');
    const source=doc.importNode(root,true);
    source.removeAttribute('data-voucher-document');
    source.style.margin='0';
    doc.body.append(source);
    if(doc.fonts?.ready)await doc.fonts.ready;
    await Promise.all([...source.querySelectorAll('img')].map(img=>img.decode?.().catch(()=>{})||Promise.resolve()));
    await nextFrame();
    const rect=source.getBoundingClientRect();
    if(!rect.width||!rect.height)throw new Error('voucher_export_layout_unavailable');
    stage.style.height=`${Math.ceil(rect.height+5)}px`;
    return {stage,source};
  }catch(error){stage.remove();throw error;}
};

const createExportSource=async()=>{
  if(mode==='a5')return createA5ExportSource();
  if(document.fonts?.ready)await document.fonts.ready;
  await nextFrame();
  const liveRect=root.getBoundingClientRect();
  if(!liveRect.width||!liveRect.height)throw new Error('voucher_export_layout_unavailable');
  const stage=document.createElement('div');
  stage.className='voucher-export-source voucher-export-light';
  stage.setAttribute('aria-hidden','true');
  stage.style.cssText=`position:fixed;left:-10000px;top:0;width:${liveRect.width}px;height:${liveRect.height}px;margin:0;padding:0;z-index:-1;pointer-events:none;background:#fff;direction:rtl;text-align:start;overflow:hidden;`;
  const source=root.cloneNode(true);
  source.removeAttribute('data-voucher-document');
  // Screen may be dark, but the guest document contract for PDF/print is always light.
  // Put the same layout under the fixed export palette first, then freeze those
  // computed light styles. This keeps layout parity without leaking dark colors.
  source.style.margin='0';
  source.style.position='relative';
  source.style.inset='auto';
  source.style.width=`${liveRect.width}px`;
  source.style.height=`${liveRect.height}px`;
  stage.append(source);body.append(stage);
  try{
    await nextFrame();
    await inlineImageSources(root,source);
    await Promise.all([...source.querySelectorAll('img')].map(img=>img.decode?.().catch(()=>{})||Promise.resolve()));
    await nextFrame();
    return {stage,source};
  }catch(error){stage.remove();throw error;}
};

const renderDocumentCanvas=async()=>{
  const {stage,source}=await createExportSource();
  try{
    const rect=source.getBoundingClientRect();
    if(!rect.width||!rect.height)throw new Error('voucher_export_layout_unavailable');
    const clone=source.cloneNode(true);
    copyComputedStyles(source,clone);
    await inlineImageSources(source,clone);
    const wrapper=document.createElement('div');
    wrapper.setAttribute('xmlns','http://www.w3.org/1999/xhtml');
    wrapper.setAttribute('style',`width:${rect.width}px;height:${rect.height}px;margin:0;padding:0;background:#fff;direction:rtl;text-align:start;overflow:hidden;font-family:Vazirmatn,sans-serif;`);
    const fontData=await exportFontDataUrl();
    if(fontData){const style=document.createElement('style');style.textContent=`@font-face{font-family:"Vazirmatn";src:url("${fontData}") format("woff2");font-style:normal;font-weight:100 900;font-display:block;}`;wrapper.append(style);}
    wrapper.append(clone);
    const xml=new XMLSerializer().serializeToString(wrapper);
    const svg=`<svg xmlns="http://www.w3.org/2000/svg" width="${rect.width}" height="${rect.height}" viewBox="0 0 ${rect.width} ${rect.height}"><foreignObject x="0" y="0" width="100%" height="100%">${xml}</foreignObject></svg>`;
    const svgUrl='data:image/svg+xml;charset=utf-8,'+encodeURIComponent(svg);
    const image=await new Promise((resolve,reject)=>{const candidate=new Image();candidate.onload=()=>resolve(candidate);candidate.onerror=()=>reject(new Error('voucher_export_snapshot_failed'));candidate.src=svgUrl;});
    const scale=mode==='receipt'?2.25:2;
    const canvas=document.createElement('canvas');canvas.width=Math.ceil(rect.width*scale);canvas.height=Math.ceil(rect.height*scale);
    const ctx=canvas.getContext('2d',{alpha:false});if(!ctx)throw new Error('canvas_unavailable');
    ctx.scale(scale,scale);ctx.fillStyle='#fff';ctx.fillRect(0,0,rect.width,rect.height);ctx.drawImage(image,0,0,rect.width,rect.height);
    // Semantic boundaries are measured before the export DOM is removed.  A raster
    // page must not cut across a room, service or financial row when a safe cut
    // is available near the physical A5 bottom edge.
    const boundaries=mode==='a5'?[...source.querySelectorAll('.voucher-table tbody tr,.voucher-finance>div,.voucher-section,.voucher-note,.voucher-policy,.voucher-foot')]
      .map(element=>Math.round((element.getBoundingClientRect().top-rect.top)*scale))
      .filter(position=>position>0&&position<canvas.height):[];
    return {canvas,boundaries};
  }finally{stage.remove();}
};

const canvasToJpegBytes=async(canvas,quality=.95)=>{const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/jpeg',quality));if(!blob)throw new Error('jpeg_failed');return new Uint8Array(await blob.arrayBuffer());};
const ascii=value=>new TextEncoder().encode(value);
const joinBytes=parts=>{const size=parts.reduce((sum,part)=>sum+part.length,0),out=new Uint8Array(size);let offset=0;for(const part of parts){out.set(part,offset);offset+=part.length;}return out;};
const buildPdf=pages=>{const objectCount=2+pages.length*3,objects=new Array(objectCount+1),pageRefs=[];pages.forEach((_,index)=>pageRefs.push(`${3+index*3} 0 R`));objects[1]=ascii('<< /Type /Catalog /Pages 2 0 R >>');objects[2]=ascii(`<< /Type /Pages /Count ${pages.length} /Kids [${pageRefs.join(' ')}] >>`);pages.forEach((page,index)=>{const pageId=3+index*3,contentId=pageId+1,imageId=pageId+2,draw=ascii(`q\n${page.pageWidth.toFixed(3)} 0 0 ${page.pageHeight.toFixed(3)} 0 0 cm\n/Im${index+1} Do\nQ\n`);objects[pageId]=ascii(`<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${page.pageWidth.toFixed(3)} ${page.pageHeight.toFixed(3)}] /Resources << /XObject << /Im${index+1} ${imageId} 0 R >> >> /Contents ${contentId} 0 R >>`);objects[contentId]=joinBytes([ascii(`<< /Length ${draw.length} >>\nstream\n`),draw,ascii('endstream')]);objects[imageId]=joinBytes([ascii(`<< /Type /XObject /Subtype /Image /Width ${page.pixelWidth} /Height ${page.pixelHeight} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${page.jpeg.length} >>\nstream\n`),page.jpeg,ascii('\nendstream')]);});const parts=[ascii('%PDF-1.4\n%SOKNA\n')],offsets=new Array(objectCount+1).fill(0);let offset=parts[0].length;for(let id=1;id<=objectCount;id++){offsets[id]=offset;const part=joinBytes([ascii(`${id} 0 obj\n`),objects[id],ascii('\nendobj\n')]);parts.push(part);offset+=part.length;}const xrefOffset=offset;let xref=`xref\n0 ${objectCount+1}\n0000000000 65535 f \n`;for(let id=1;id<=objectCount;id++)xref+=`${String(offsets[id]).padStart(10,'0')} 00000 n \n`;xref+=`trailer\n<< /Size ${objectCount+1} /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF`;parts.push(ascii(xref));return new Blob([joinBytes(parts)],{type:'application/pdf'});};
const sliceHasContent=(canvas,top,height)=>{const ctx=canvas.getContext('2d',{willReadFrequently:true});if(!ctx||height<=0)return false;const data=ctx.getImageData(0,top,canvas.width,height).data,step=10;for(let py=0;py<height;py+=step){const row=py*canvas.width*4;for(let px=0;px<canvas.width;px+=step){const i=row+px*4;if(data[i]<247||data[i+1]<247||data[i+2]<247)return true;}}return false;};
const makeA5Pages=async(canvas,boundaries=[])=>{
  const pageWidth=148*72/25.4,pageHeight=210*72/25.4,sliceHeight=Math.round(canvas.width*(210/148));
  const overflow=Math.max(0,canvas.height-sliceHeight),nearPageTolerance=Math.round(sliceHeight*.025);
  // Avoid phantom blank pages caused by sub-millimetre screen rounding.
  if(overflow>0&&overflow<=nearPageTolerance){
    const page=document.createElement('canvas');page.width=canvas.width;page.height=sliceHeight;
    const ctx=page.getContext('2d',{alpha:false});if(!ctx)throw new Error('canvas_unavailable');
    ctx.fillStyle='#fff';ctx.fillRect(0,0,page.width,page.height);
    const fit=Math.min(1,sliceHeight/canvas.height),drawWidth=Math.round(canvas.width*fit),drawHeight=Math.round(canvas.height*fit),x=Math.round((page.width-drawWidth)/2);
    ctx.drawImage(canvas,0,0,canvas.width,canvas.height,x,0,drawWidth,drawHeight);
    return [{jpeg:await canvasToJpegBytes(page),pixelWidth:page.width,pixelHeight:page.height,pageWidth,pageHeight}];
  }
  const pages=[];let top=0;
  while(top<canvas.height){
    const remaining=canvas.height-top;let sourceHeight=Math.min(sliceHeight,remaining);
    if(remaining>sliceHeight){
      // Prefer the last block/row boundary within the bottom 12% of a page.
      // This retains all pixels, leaving a short white tail instead of cutting text.
      const minCut=top+Math.round(sliceHeight*.88),maxCut=top+sliceHeight-8;
      const near=boundaries.filter(cut=>cut>=minCut&&cut<=maxCut);
      if(near.length)sourceHeight=near[near.length-1]-top;
    }
    if(top>0&&!sliceHasContent(canvas,top,sourceHeight)){top+=sourceHeight;continue;}
    const slice=document.createElement('canvas');slice.width=canvas.width;slice.height=sliceHeight;
    const ctx=slice.getContext('2d',{alpha:false});if(!ctx)throw new Error('canvas_unavailable');
    ctx.fillStyle='#fff';ctx.fillRect(0,0,slice.width,slice.height);
    ctx.drawImage(canvas,0,top,canvas.width,sourceHeight,0,0,canvas.width,sourceHeight);
    pages.push({jpeg:await canvasToJpegBytes(slice),pixelWidth:slice.width,pixelHeight:slice.height,pageWidth,pageHeight});
    top+=sourceHeight;
  }
  return pages;
};
const prepareForPrint=async()=>{if(document.fonts?.ready)await document.fonts.ready;await Promise.all([...root.querySelectorAll('img')].map(img=>img.decode?.().catch(()=>{})||Promise.resolve()));await nextFrame();};
const makePdf=async()=>{
  if(cachedPdf)return cachedPdf;if(pdfPromise)return pdfPromise;
  pdfPromise=(async()=>{const {canvas,boundaries}=await renderDocumentCanvas();let pages=[];if(mode==='receipt'){const jpeg=await canvasToJpegBytes(canvas),pageWidth=80*72/25.4,pageHeight=pageWidth*(canvas.height/canvas.width);pages=[{jpeg,pixelWidth:canvas.width,pixelHeight:canvas.height,pageWidth,pageHeight}];}else pages=await makeA5Pages(canvas,boundaries);if(!pages.length)throw new Error('voucher_export_empty');cachedPdf=buildPdf(pages);return cachedPdf;})().finally(()=>{pdfPromise=null;});return pdfPromise;
};
const downloadBlob=(blob,name)=>{const url=URL.createObjectURL(blob),anchor=document.createElement('a');anchor.href=url;anchor.download=name;anchor.hidden=true;document.body.appendChild(anchor);anchor.click();anchor.remove();window.setTimeout(()=>URL.revokeObjectURL(url),2500);};
const downloadPdf=async()=>{setBusy(true);tell('در حال ساخت فایل PDF…');try{const blob=await makePdf();downloadBlob(blob,pdfName);tell('فایل PDF آماده شد.');}catch(error){console.error(error);tell('ساخت PDF انجام نشد؛ از گزینه چاپ استفاده کنید.');}finally{setBusy(false);}};
const printVoucher=async()=>{if(typeof window.print!=='function'){tell('چاپ در این مرورگر در دسترس نیست.');return;}setBusy(true);try{await prepareForPrint();tell(mode==='receipt'?'چاپ برای فیش حرارتی ۸۰ میلی‌متری آماده است.':'چاپ سند برای کاغذ A5 تنظیم شده است.');window.print();}catch(error){console.error(error);tell('آماده‌سازی چاپ کامل نشد؛ دوباره تلاش کنید.');}finally{setBusy(false);}};
const sharePdf=async()=>{setBusy(true);tell('در حال آماده‌سازی PDF برای اشتراک…');try{const blob=await makePdf(),file=new File([blob],pdfName,{type:'application/pdf'});if(typeof navigator.share==='function'&&(!navigator.canShare||navigator.canShare({files:[file]}))){await navigator.share({files:[file],title:document.title,text:mode==='receipt'?'سند رزرو و پرداخت سکنا':'تأییدیه رزرو سکنا'});tell('فایل PDF برای اشتراک ارسال شد.');}else{downloadBlob(blob,pdfName);tell('اشتراک فایل در این مرورگر پشتیبانی نمی‌شود؛ PDF دانلود شد.');}}catch(error){if(error?.name!=='AbortError'){console.error(error);tell('اشتراک PDF انجام نشد؛ دوباره تلاش کنید.');}}finally{setBusy(false);}};

document.addEventListener('click',event=>{const target=event.target instanceof Element?event.target.closest('[data-download-pdf],[data-print],[data-share-pdf]'):null;if(!target)return;if(target.matches('[data-download-pdf]')){event.preventDefault();void downloadPdf();return;}if(target.matches('[data-print]')){event.preventDefault();printVoucher();return;}if(target.matches('[data-share-pdf]')){event.preventDefault();void sharePdf();}});
const warm=()=>{void makePdf().catch(()=>{});};window.setTimeout(warm,700);
body?.setAttribute('data-voucher-actions-ready','1');
})();
