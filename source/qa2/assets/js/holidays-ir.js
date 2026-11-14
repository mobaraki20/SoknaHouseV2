(()=>{
'use strict';
/*
 * Iran official-holiday dataset for operational calendar hints.
 * Source of truth is versioned by Jalali year. The application never fetches
 * holiday data at runtime so the local/offline PMS stays deterministic.
 * 1405 entries are limited to national official-calendar holidays; temporary
 * emergency/provincial closures are intentionally excluded.
 */
const YEARS=Object.freeze({
  1405:Object.freeze({
    source:'تقویم رسمی کشور ۱۴۰۵ — مرکز تقویم مؤسسه ژئوفیزیک دانشگاه تهران / مناسبت‌های مصوب شورای فرهنگ عمومی کشور',
    holidays:Object.freeze({
      '1405/01/01':'نوروز · تعطیل عید فطر',
      '1405/01/02':'عید نوروز',
      '1405/01/03':'عید نوروز',
      '1405/01/04':'عید نوروز',
      '1405/01/12':'روز جمهوری اسلامی ایران',
      '1405/01/13':'روز طبیعت',
      '1405/01/24':'شهادت امام جعفر صادق (ع)',
      '1405/03/03':'شهادت امام محمد باقر (ع)',
      '1405/03/06':'عید قربان',
      '1405/03/14':'رحلت امام خمینی · عید غدیر',
      '1405/03/15':'قیام ۱۵ خرداد',
      '1405/04/03':'تاسوعا',
      '1405/04/04':'عاشورا',
      '1405/05/13':'اربعین',
      '1405/05/21':'رحلت پیامبر و شهادت امام حسن مجتبی (ع)',
      '1405/05/22':'شهادت امام رضا (ع)',
      '1405/05/30':'شهادت امام حسن عسکری (ع)',
      '1405/06/08':'ولادت پیامبر و امام جعفر صادق (ع)',
      '1405/08/22':'شهادت حضرت فاطمه زهرا (س)',
      '1405/10/02':'ولادت امام علی (ع) · روز پدر',
      '1405/10/16':'مبعث پیامبر',
      '1405/11/04':'نیمه شعبان',
      '1405/11/22':'پیروزی انقلاب اسلامی',
      '1405/12/09':'شهادت امام علی (ع)',
      '1405/12/19':'عید فطر',
      '1405/12/20':'تعطیل عید فطر',
      '1405/12/29':'ملی شدن صنعت نفت'
    })
  })
});
function normalize(value){
  const m=String(value||'').match(/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/);
  return m?`${m[1]}/${String(Number(m[2])).padStart(2,'0')}/${String(Number(m[3])).padStart(2,'0')}`:'';
}
function info(value){
  const key=normalize(value);if(!key)return null;
  const year=Number(key.slice(0,4)),pack=YEARS[year],label=pack?.holidays?.[key];
  return label?Object.freeze({key,year,label,source:pack.source}):null;
}
function supportsYear(year){return Boolean(YEARS[Number(year)]);}
window.SoknaIranHolidays=Object.freeze({info,supportsYear});
})();
