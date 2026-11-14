<?php
declare(strict_types=1);

/**
 * Clean Sokna House -> Sokna Core integration.
 *
 * Ownership boundary:
 * - House owns local users/login and only proves local identity.
 * - Core owns HR/Payroll and every HR authorization decision.
 * - No network call from this client is performed during normal House boot/login.
 */
final class SoknaCoreClient
{
    private const ISS='house';
    private const CONTEXT='HOUSE';
    private const PAIR_PREFIX='SC1.';
    private const HANDOFF_TYP='SOKNA-HANDOFF';
    private const S2S_TYP='SOKNA-S2S';
    private const TOKEN_VERSION=1;
    private const TOKEN_TTL=60;
    private const SECRET_AAD='sokna-core-house-v1';
    private const PAIR_PATH='/auth/pair.php';
    private const PROBE_PATH='/auth/probe.php';
    private const HANDOFF_PATH='/auth/handoff.php';

    public static function connected(): bool
    {
        return (string)setting('sokna_core_connection_enabled','0')==='1'
            && self::baseUrl()!==''
            && self::sharedSecret()!=='';
    }

    public static function baseUrl(): string { return (string)setting('sokna_core_base_url',''); }
    public static function fingerprint(): string { return (string)setting('sokna_core_secret_fingerprint',''); }
    public static function lastSuccessAt(): string { return (string)setting('sokna_core_last_success_at',''); }
    public static function lastErrorCode(): string { return (string)setting('sokna_core_last_error_code',''); }
    public static function lastErrorAt(): string { return (string)setting('sokna_core_last_error_at',''); }
    public static function lastHttpStatus(): int { return (int)setting('sokna_core_last_http_status',0); }
    public static function lastPurpose(): string { return (string)setting('sokna_core_last_purpose',''); }

    /** Explicit Pair/Re-pair. Existing active connection is not replaced before Core accepts Pair. */
    public static function pairWithKey(string $pairingKey,int $actorUserId): array
    {
        $candidate=self::parsePairingKey($pairingKey);
        $center=(string)$candidate['center'];
        $secret=(string)$candidate['secret'];

        // Validate exact-secret storage round-trip before this candidate is ever used remotely.
        $encrypted=self::encryptSecret($secret);
        $reloaded=self::decryptSecret($encrypted);
        if(!hash_equals($secret,$reloaded))throw new RuntimeException('نگهداری امن کلید اتصال تأیید نشد.');

        $token=self::buildHandoffToken('pair',(string)$actorUserId,$center,$reloaded);
        self::verifyLocalHandoffToken($token,$reloaded,'pair',(string)$actorUserId,$center);

        $response=self::postToken(self::endpoint(self::PAIR_PATH,$center),$token,7);
        if((int)$response['http_status']!==200||($response['ok']??false)!==true){
            $code=(string)($response['error_code']??'CORE_PAIR_REJECTED');
            self::recordDiagnostics(false,'pair',(int)$response['http_status'],$code);
            audit('core_pair_failed','integration',null,[
                'fingerprint'=>self::secretFingerprint($secret),
                'http_status'=>(int)$response['http_status'],
                'error_code'=>$code,
            ]);
            throw new RuntimeException(self::friendlyError($code,'pair'));
        }

        self::saveConnection($center,$encrypted,self::secretFingerprint($secret));
        self::recordDiagnostics(true,'pair',200,'');
        audit('core_pair_succeeded','integration',null,[
            'fingerprint'=>self::secretFingerprint($secret),
            'http_status'=>200,
            'context'=>self::CONTEXT,
        ]);
        return ['ok'=>true,'http_status'=>200];
    }

    /** Diagnostics only. Probe never writes trust metadata or changes the connection secret. */
    public static function probe(int $actorUserId): array
    {
        if(!self::connected())throw new RuntimeException('ابتدا اتصال به مرکز سکنا را برقرار کنید.');
        $center=self::baseUrl();$secret=self::sharedSecret();
        $token=self::buildHandoffToken('probe',(string)$actorUserId,$center,$secret);
        self::verifyLocalHandoffToken($token,$secret,'probe',(string)$actorUserId,$center);
        $response=self::postToken(self::endpoint(self::PROBE_PATH,$center),$token,7);
        if((int)$response['http_status']!==200||($response['ok']??false)!==true){
            $code=(string)($response['error_code']??'CORE_PROBE_FAILED');
            self::recordDiagnostics(false,'probe',(int)$response['http_status'],$code);
            audit('core_probe_failed','integration',null,['http_status'=>(int)$response['http_status'],'error_code'=>$code]);
            throw new RuntimeException(self::friendlyError($code,'probe'));
        }
        self::recordDiagnostics(true,'probe',200,'');
        audit('core_probe_succeeded','integration',null,['http_status'=>200,'context'=>self::CONTEXT]);
        return ['ok'=>true,'http_status'=>200];
    }

    /** Build a browser POST handoff after local authentication. Authorization remains Core-owned. */
    public static function handoff(array $user): array
    {
        if(!self::connected())throw new RuntimeException('اتصال به مرکز سکنا برقرار نشده است.');
        $id=(int)($user['id']??0);if($id<1)throw new RuntimeException('حساب کاربری اقامتگاه معتبر نیست.');
        $center=self::baseUrl();$secret=self::sharedSecret();
        $token=self::buildHandoffToken('handoff',(string)$id,$center,$secret);
        self::verifyLocalHandoffToken($token,$secret,'handoff',(string)$id,$center);
        return ['target'=>self::endpoint(self::HANDOFF_PATH,$center),'token'=>$token];
    }

    /** Strict SC1 parser. The decoded secret is returned byte-for-byte; it is never trimmed/normalized. */
    public static function parsePairingKey(string $key): array
    {
        if($key===''||$key!==trim($key)||!str_starts_with($key,self::PAIR_PREFIX))throw new InvalidArgumentException('کلید اتصال مرکز سکنا معتبر نیست.');
        $encoded=substr($key,strlen(self::PAIR_PREFIX));if($encoded==='')throw new InvalidArgumentException('کلید اتصال مرکز سکنا معتبر نیست.');
        $raw=self::base64urlDecode($encoded);if($raw===false)throw new InvalidArgumentException('کلید اتصال مرکز سکنا معتبر نیست.');
        try{$data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(JsonException){throw new InvalidArgumentException('کلید اتصال مرکز سکنا معتبر نیست.');}
        if(!is_array($data)||(int)($data['v']??0)!==1||(string)($data['issuer']??'')!==self::ISS)throw new InvalidArgumentException('این کلید برای اقامتگاه صادر نشده است.');
        if(!array_key_exists('center',$data)||!array_key_exists('secret',$data)||!is_string($data['center'])||!is_string($data['secret'])||$data['secret']==='')throw new InvalidArgumentException('کلید اتصال مرکز سکنا ناقص است.');
        $center=self::validateCenterUrl($data['center']);
        return ['center'=>$center,'secret'=>$data['secret']];
    }

    /** Pair/Probe/Handoff tokens use canonical claims only. */
    public static function buildHandoffToken(string $purpose,string $localUserId,string $center,string $secret,?int $now=null,?string $nonce=null): string
    {
        if(!in_array($purpose,['pair','probe','handoff'],true))throw new InvalidArgumentException('Purpose اتصال معتبر نیست.');
        if($localUserId===''||!ctype_digit($localUserId)||(int)$localUserId<1)throw new InvalidArgumentException('شناسه کاربر محلی معتبر نیست.');
        $center=self::validateCenterUrl($center);if($secret==='')throw new RuntimeException('Secret اتصال تنظیم نشده است.');
        $iat=$now??time();$payload=[
            'iss'=>self::ISS,
            'sub'=>$localUserId,
            'context'=>self::CONTEXT,
            'aud'=>$center,
            'purpose'=>$purpose,
            'iat'=>$iat,
            'exp'=>$iat+self::TOKEN_TTL,
            'nonce'=>$nonce??bin2hex(random_bytes(24)),
            'origin'=>self::applicationOrigin(),
            'return_url'=>self::returnUrl(),
        ];
        return self::signCompact(['alg'=>'HS256','typ'=>self::HANDOFF_TYP,'v'=>1],$payload,$secret);
    }

    /** Local signature/claim verification before any Pair/Probe/Handoff token leaves House. */
    public static function verifyLocalHandoffToken(string $token,string $secret,string $purpose,string $sub,string $aud): array
    {
        [$header,$payload]=self::verifyCompact($token,$secret,self::HANDOFF_TYP,'HANDOFF_SIGNATURE');
        $expectedHeader=['alg'=>'HS256','typ'=>self::HANDOFF_TYP,'v'=>1];
        if($header!==$expectedHeader)throw new RuntimeException('Header توکن اتصال معتبر نیست.');
        $allowed=['iss','sub','context','aud','purpose','iat','exp','nonce','origin','return_url'];
        if(array_keys($payload)!==$allowed)throw new RuntimeException('Claimهای توکن اتصال canonical نیستند.');
        if(($payload['iss']??null)!==self::ISS||($payload['sub']??null)!==$sub||($payload['context']??null)!==self::CONTEXT||($payload['aud']??null)!==$aud||($payload['purpose']??null)!==$purpose)throw new RuntimeException('Claimهای توکن اتصال معتبر نیستند.');
        $iat=$payload['iat']??null;$exp=$payload['exp']??null;
        if(!is_int($iat)||!is_int($exp)||$exp<=$iat||($exp-$iat)>self::TOKEN_TTL)throw new RuntimeException('TTL توکن اتصال معتبر نیست.');
        if(!is_string($payload['nonce']??null)||$payload['nonce']==='')throw new RuntimeException('Nonce توکن اتصال معتبر نیست.');
        if(($payload['origin']??null)!==self::applicationOrigin()||($payload['return_url']??null)!==self::returnUrl())throw new RuntimeException('Trust context توکن اتصال معتبر نیست.');
        return $payload;
    }

    /** Verify the current Core -> House read-only User Directory S2S contract. */
    public static function verifyDirectoryAuthorization(string $authorization,int $page,int $perPage,?int $now=null): array
    {
        if(!self::connected())throw new RuntimeException('CORE_NOT_PAIRED');
        if(!str_starts_with($authorization,'Sokna-HMAC '))throw new RuntimeException('DIRECTORY_AUTH');
        $token=substr($authorization,11);if($token==='')throw new RuntimeException('DIRECTORY_AUTH');
        $secret=self::sharedSecret();[$header,$payload]=self::verifyCompact($token,$secret,self::S2S_TYP,'DIRECTORY_SIGNATURE');
        if($header!==['alg'=>'HS256','typ'=>self::S2S_TYP,'v'=>1])throw new RuntimeException('DIRECTORY_HEADER');
        $keys=['audience','context','expires_at','issuer','nonce','page','per_page','purpose','timestamp'];
        $actualKeys=array_keys($payload);sort($actualKeys);
        if($actualKeys!==$keys)throw new RuntimeException('DIRECTORY_CLAIMS');
        if(($payload['issuer']??null)!=='center'||($payload['audience']??null)!=='house'||($payload['purpose']??null)!=='user_directory'||($payload['context']??null)!==self::CONTEXT)throw new RuntimeException('DIRECTORY_SCOPE');
        if(($payload['page']??null)!==$page||($payload['per_page']??null)!==$perPage)throw new RuntimeException('DIRECTORY_PAGINATION');
        $ts=$payload['timestamp']??null;$exp=$payload['expires_at']??null;$clock=$now??time();
        if(!is_int($ts)||!is_int($exp)||$exp<=$ts||($exp-$ts)>self::TOKEN_TTL||$ts>$clock+30||$exp<$clock)throw new RuntimeException('DIRECTORY_EXPIRED');
        $nonce=$payload['nonce']??null;if(!is_string($nonce)||$nonce===''||strlen($nonce)>256)throw new RuntimeException('DIRECTORY_NONCE');
        self::consumeDirectoryNonce($nonce,$exp,$clock);
        return $payload;
    }

    public static function secretFingerprint(string $secret): string
    {
        return $secret===''?'':substr(hash('sha256',$secret),0,12);
    }

    public static function encryptSecret(string $plain): string
    {
        if($plain==='')return '';
        if(!function_exists('openssl_encrypt'))throw new RuntimeException('افزونه OpenSSL برای اتصال به مرکز سکنا لازم است.');
        $iv=random_bytes(12);$tag='';
        $cipher=openssl_encrypt($plain,'aes-256-gcm',self::storageKey(),OPENSSL_RAW_DATA,$iv,$tag,self::SECRET_AAD);
        if($cipher===false||strlen($tag)!==16)throw new RuntimeException('نگهداری امن Secret انجام نشد.');
        return 'core:v1:'.base64_encode($iv.$tag.$cipher);
    }

    public static function decryptSecret(string $stored): string
    {
        if($stored===''||!str_starts_with($stored,'core:v1:')||!function_exists('openssl_decrypt'))return '';
        $raw=base64_decode(substr($stored,8),true);if($raw===false||strlen($raw)<29)return '';
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',self::storageKey(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),self::SECRET_AAD);
        return is_string($plain)?$plain:'';
    }

    public static function friendlyError(string $code,string $purpose='probe'): string
    {
        $map=[
            'HANDOFF_SIGNATURE'=>'امضای اتصال تأیید نشد. کلید اتصال را دوباره بررسی کنید.',
            'HANDOFF_AUDIENCE'=>'آدرس مرکز با کلید اتصال تطابق ندارد.',
            'HANDOFF_ORIGIN'=>'مبدأ اقامتگاه با Trust ثبت‌شده در مرکز تطابق ندارد. برای تغییر، Re-pair انجام دهید.',
            'HANDOFF_RETURN_URL'=>'نشانی بازگشت با Trust ثبت‌شده در مرکز تطابق ندارد. برای تغییر، Re-pair انجام دهید.',
            'HANDOFF_EXPIRED'=>'اعتبار درخواست اتصال تمام شده است. دوباره تلاش کنید.',
            'CORE_UNREACHABLE'=>'مرکز سکنا موقتاً در دسترس نیست. سایر بخش‌های اقامتگاه بدون اختلال قابل استفاده هستند.',
        ];
        return $map[$code]??($purpose==='pair'?'مرکز سکنا کلید اتصال را نپذیرفت.':'مرکز سکنا موقتاً در دسترس نیست. سایر بخش‌های اقامتگاه بدون اختلال قابل استفاده هستند.');
    }

    private static function sharedSecret(): string
    {
        $stored=(string)setting('sokna_core_secret_encrypted','');
        return self::decryptSecret($stored);
    }

    private static function saveConnection(string $center,string $encrypted,string $fingerprint): void
    {
        db()->beginTransaction();
        try{
            self::writeSetting('sokna_core_connection_enabled','1');
            self::writeSetting('sokna_core_base_url',$center);
            self::writeSetting('sokna_core_secret_encrypted',$encrypted);
            self::writeSetting('sokna_core_secret_fingerprint',$fingerprint);
            db()->commit();
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    }

    private static function recordDiagnostics(bool $ok,string $purpose,int $httpStatus,string $errorCode): void
    {
        // Diagnostics must never turn an accepted Pair/Probe result into an operational failure.
        try{
            self::writeSetting('sokna_core_last_purpose',$purpose);
            self::writeSetting('sokna_core_last_http_status',(string)$httpStatus);
            if($ok){
                self::writeSetting('sokna_core_last_success_at',date('Y-m-d H:i:s'));
                self::writeSetting('sokna_core_last_error_code','');
                self::writeSetting('sokna_core_last_error_at','');
            }else{
                self::writeSetting('sokna_core_last_error_code',preg_match('/^[A-Z0-9_:-]{1,80}$/',$errorCode)?$errorCode:'CORE_ERROR');
                self::writeSetting('sokna_core_last_error_at',date('Y-m-d H:i:s'));
            }
        }catch(Throwable $ignored){}
    }

    private static function writeSetting(string $key,string $value): void
    {
        db()->prepare('INSERT INTO '.table('settings').' (`key`,`value`,`updated_at`) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE value=VALUES(value),updated_at=NOW()')->execute([$key,$value]);
    }

    private static function postToken(string $url,string $token,int $timeout): array
    {
        $body=http_build_query(['token'=>$token],'','&',PHP_QUERY_RFC3986);
        $raw='';$status=0;$transport=false;
        if(function_exists('curl_init')){
            $ch=curl_init($url);if($ch===false)return ['ok'=>false,'http_status'=>0,'error_code'=>'CORE_UNREACHABLE'];
            $protocols=CURLPROTO_HTTPS;if(self::isLocalHttp($url))$protocols|=CURLPROTO_HTTP;
            curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>$timeout,CURLOPT_TIMEOUT=>$timeout,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','Accept: application/json'],CURLOPT_PROTOCOLS=>$protocols]);
            $result=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$transport=$result!==false;$raw=$transport?(string)$result:'';curl_close($ch);
        }elseif(filter_var((string)ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN)){
            $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\nConnection: close\r\n",'content'=>$body,'timeout'=>$timeout,'ignore_errors'=>true,'follow_location'=>0],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
            $result=@file_get_contents($url,false,$ctx);$transport=$result!==false;$raw=$transport?(string)$result:'';$headers=$http_response_header??[];if(isset($headers[0])&&preg_match('/\s(\d{3})\s/',(string)$headers[0],$m))$status=(int)$m[1];
        }
        if(!$transport)return ['ok'=>false,'http_status'=>0,'error_code'=>'CORE_UNREACHABLE'];
        $payload=json_decode($raw,true);$ok=is_array($payload)&&($payload['ok']??null)===true;
        $code='';if(is_array($payload)){$err=$payload['error']??null;if(is_array($err))$code=(string)($err['code']??'');elseif(is_string($payload['code']??null))$code=(string)$payload['code'];}
        return ['ok'=>$ok,'http_status'=>$status,'error_code'=>$code!==''?$code:($ok?'':'CORE_REJECTED')];
    }

    private static function signCompact(array $header,array $payload,string $secret): string
    {
        $h=self::base64urlEncode((string)json_encode($header,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        $p=self::base64urlEncode((string)json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        $signature=hash_hmac('sha256',$h.'.'.$p,$secret,true);
        return $h.'.'.$p.'.'.self::base64urlEncode($signature);
    }

    private static function verifyCompact(string $token,string $secret,string $expectedTyp,string $signatureError): array
    {
        if($secret==='')throw new RuntimeException('Secret اتصال موجود نیست.');
        $parts=explode('.',$token);if(count($parts)!==3)throw new RuntimeException('TOKEN_FORMAT');
        [$h,$p,$s]=$parts;$rawHeader=self::base64urlDecode($h);$rawPayload=self::base64urlDecode($p);$rawSig=self::base64urlDecode($s);
        if($rawHeader===false||$rawPayload===false||$rawSig===false)throw new RuntimeException('TOKEN_FORMAT');
        $expected=hash_hmac('sha256',$h.'.'.$p,$secret,true);if(!hash_equals($expected,$rawSig))throw new RuntimeException($signatureError);
        try{$header=json_decode($rawHeader,true,16,JSON_THROW_ON_ERROR);$payload=json_decode($rawPayload,true,32,JSON_THROW_ON_ERROR);}catch(JsonException){throw new RuntimeException('TOKEN_JSON');}
        if(!is_array($header)||!is_array($payload)||($header['typ']??null)!==$expectedTyp)throw new RuntimeException('TOKEN_HEADER');
        return [$header,$payload];
    }

    private static function consumeDirectoryNonce(string $nonce,int $expiresAt,int $now): void
    {
        $dir=dirname(__DIR__).'/storage';if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('DIRECTORY_REPLAY_STORE');
        $path=$dir.'/core-directory-nonce-replay.json';$handle=@fopen($path,'c+');if(!$handle)throw new RuntimeException('DIRECTORY_REPLAY_STORE');
        try{
            if(!flock($handle,LOCK_EX))throw new RuntimeException('DIRECTORY_REPLAY_STORE');
            rewind($handle);$raw=stream_get_contents($handle);$items=json_decode($raw?:'{}',true);if(!is_array($items))$items=[];
            foreach($items as $hash=>$expiry)if((int)$expiry<$now)unset($items[$hash]);
            $hash=hash('sha256',$nonce);if(isset($items[$hash]))throw new RuntimeException('DIRECTORY_REPLAY');
            $items[$hash]=$expiresAt;rewind($handle);ftruncate($handle,0);$encoded=json_encode($items,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);if(fwrite($handle,$encoded)===false)throw new RuntimeException('DIRECTORY_REPLAY_STORE');fflush($handle);
        }finally{flock($handle,LOCK_UN);fclose($handle);@chmod($path,0640);}
    }

    private static function validateCenterUrl(string $url): string
    {
        if($url===''||$url!==trim($url)||filter_var($url,FILTER_VALIDATE_URL)===false)throw new InvalidArgumentException('آدرس مرکز داخل کلید اتصال معتبر نیست.');
        $parts=parse_url($url);$scheme=strtolower((string)($parts['scheme']??''));$host=strtolower((string)($parts['host']??''));
        if($host===''||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment']))throw new InvalidArgumentException('آدرس مرکز داخل کلید اتصال معتبر نیست.');
        $local=in_array($host,['localhost','127.0.0.1','::1'],true);if($scheme!=='https'&&!($local&&$scheme==='http'))throw new InvalidArgumentException('اتصال مرکز سکنا باید روی HTTPS باشد.');
        return $url;
    }

    private static function endpoint(string $path,string $center): string { return rtrim($center,'/').'/'.ltrim($path,'/'); }
    private static function applicationOrigin(): string { return runtime_base_url(); }
    private static function returnUrl(): string { return url('today'); }
    private static function storageKey(): string { $appKey=(string)(app_config()['app_key']??'');if($appKey===''||str_starts_with($appKey,'CHANGE_ME'))throw new RuntimeException('کلید داخلی سامانه برای نگهداری امن اتصال آماده نیست.');return hash('sha256',self::SECRET_AAD.'|'.$appKey,true); }
    private static function isLocalHttp(string $url): bool { $p=parse_url($url);return ($p['scheme']??'')==='http'&&in_array(strtolower((string)($p['host']??'')),['localhost','127.0.0.1','::1'],true); }
    private static function base64urlEncode(string $data): string { return rtrim(strtr(base64_encode($data),'+/','-_'),'='); }
    private static function base64urlDecode(string $value): string|false { if($value===''||preg_match('/[^A-Za-z0-9_-]/',$value))return false;$pad=strlen($value)%4;if($pad)$value.=str_repeat('=',4-$pad);return base64_decode(strtr($value,'-_','+/'),true); }
}
