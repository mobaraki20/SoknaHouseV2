<?php
return [
  'db'=>['host'=>'localhost','port'=>3306,'name'=>'','user'=>'','pass'=>'','prefix'=>'sokna_'],
  'timezone'=>'Asia/Tehran',
  'session_name'=>'sokna_session',
  'app_key'=>'CHANGE_ME',
  'cafe_api'=>[
    // فقط SHA-256 توکن در این فایل نگهداری می‌شود؛ خود Token را از مسیر امن جداگانه تحویل دهید.
    'token_hash'=>'',
    'allow_http_localhost'=>true,
    // فقط اگر TLS روی Reverse Proxy پایان می‌یابد فعال شود و IP پروکسی دقیقاً محدود گردد.
    'trust_proxy_https'=>false,
    'trusted_proxy_ips'=>[],
    // محدودسازی سبک API بر پایه Token fingerprint + IP + action در پنجره ۶۰ ثانیه‌ای.
    'rate_limits'=>['read'=>120,'search'=>90,'write'=>30],
  ],
];
