<?php
declare(strict_types=1);

final class FontManager
{
    private const VERSION='v33.003';
    private const MIN_BYTES=80000;
    private const MAX_BYTES=180000;
    private const RETRY_SECONDS=21600;

    public static function target(): string { return dirname(__DIR__).'/assets/fonts/Vazirmatn-Variable.woff2'; }
    public static function statusPath(): string { return dirname(__DIR__).'/storage/font-install-status.json'; }

    public static function valid(?string $path=null): bool
    {
        $path=$path??self::target();if(!is_file($path))return false;$size=@filesize($path);if(!is_int($size)||$size<self::MIN_BYTES||$size>self::MAX_BYTES)return false;
        $h=@fopen($path,'rb');if(!$h)return false;$magic=fread($h,4);fclose($h);return $magic==='wOF2';
    }

    public static function status(): array
    {
        $path=self::target();$raw=is_file(self::statusPath())?json_decode((string)@file_get_contents(self::statusPath()),true):null;
        return [
            'ready'=>self::valid($path),
            'version'=>self::VERSION,
            'source'=>'local-cache',
            'size'=>is_file($path)?(int)@filesize($path):0,
            'last_checked'=>is_array($raw)?($raw['checked_at']??null):null,
            'last_result'=>is_array($raw)?($raw['message']??null):null,
        ];
    }

    public static function ensureLocal(int $timeoutSeconds=4,bool $force=false,bool $allowStream=true): bool
    {
        $target=self::target();if(self::valid($target))return true;
        if(!$force&&self::recentFailedAttempt())return false;
        $dir=dirname($target);if(!is_dir($dir)&&!@mkdir($dir,0755,true)&&!is_dir($dir)){self::record(false,'font_directory_not_writable');return false;}
        $urls=[
            'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn%5Bwght%5D.woff2',
            'https://raw.githubusercontent.com/rastikerdar/vazirmatn/v33.003/fonts/webfonts/Vazirmatn%5Bwght%5D.woff2',
        ];
        $deadline=microtime(true)+max(1,$timeoutSeconds);
        foreach($urls as $url){if(microtime(true)>=$deadline)break;$data=self::fetch($url,$deadline,$allowStream);if($data===false)continue;$tmp=$target.'.tmp-'.bin2hex(random_bytes(5));if(@file_put_contents($tmp,$data,LOCK_EX)===strlen($data)&&self::valid($tmp)&&@rename($tmp,$target)){@chmod($target,0644);self::record(true,'installed');return true;}@unlink($tmp);}
        self::record(false,'download_failed');return false;
    }

    public static function installUploaded(array $file): bool
    {
        $tmp=(string)($file['tmp_name']??'');$size=(int)($file['size']??0);$error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
        if($error!==UPLOAD_ERR_OK||$tmp===''||!is_file($tmp))throw new RuntimeException('فایل وزیرمتن انتخاب نشده است.');
        if($size<self::MIN_BYTES||$size>self::MAX_BYTES||!self::valid($tmp))throw new RuntimeException('فایل انتخاب‌شده یک فایل WOFF2 معتبر وزیرمتن نیست.');
        $target=self::target();$dir=dirname($target);if(!is_dir($dir)&&!@mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('پوشه فونت روی سرور قابل ساخت نیست.');
        $copy=$target.'.upload-'.bin2hex(random_bytes(4));if(!@copy($tmp,$copy)||!self::valid($copy)){@unlink($copy);throw new RuntimeException('ذخیره فایل فونت روی سرور انجام نشد.');}
        if(!@rename($copy,$target)){@unlink($copy);throw new RuntimeException('جایگزینی فایل فونت روی سرور انجام نشد.');}
        @chmod($target,0644);self::record(true,'uploaded');return true;
    }

    public static function serve(): never
    {
        $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));if(!in_array($method,['GET','HEAD'],true)){http_response_code(405);header('Allow: GET, HEAD');exit;}
        $target=self::target();if(!self::valid($target)&&function_exists('curl_init'))self::ensureLocal(3,false,false);if(!self::valid($target)){http_response_code(404);header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');exit;}
        header('Content-Type: font/woff2');header('Content-Length: '.(string)filesize($target));header('Cache-Control: public, max-age=31536000, immutable');header('X-Content-Type-Options: nosniff');if($method!=='HEAD')readfile($target);exit;
    }

    private static function fetch(string $url,float $deadline,bool $allowStream): string|false
    {
        $remaining=static fn(): int=>max(0,(int)ceil($deadline-microtime(true)));
        if(function_exists('curl_init')&&$remaining()>0){
            $timeout=$remaining();$ch=curl_init($url);if($ch!==false){curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>4,CURLOPT_CONNECTTIMEOUT=>max(1,$timeout),CURLOPT_TIMEOUT=>max(1,$timeout),CURLOPT_USERAGENT=>'Sokna-House font runtime',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);if(defined('CURLOPT_PROTOCOLS')&&defined('CURLPROTO_HTTPS'))curl_setopt($ch,CURLOPT_PROTOCOLS,CURLPROTO_HTTPS);$data=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);if(is_string($data)&&$status>=200&&$status<300&&self::validBytes($data))return $data;}
        }
        if($allowStream&&$remaining()>0&&(bool)filter_var((string)ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN)){
            $ctx=stream_context_create(['http'=>['timeout'=>max(1,$remaining()),'follow_location'=>1,'max_redirects'=>4,'user_agent'=>'Sokna-House font runtime'],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);$data=@file_get_contents($url,false,$ctx);if(is_string($data)&&self::validBytes($data))return $data;
        }
        return false;
    }

    private static function validBytes(string $data): bool
    {
        $len=strlen($data);return $len>=self::MIN_BYTES&&$len<=self::MAX_BYTES&&substr($data,0,4)==='wOF2';
    }

    private static function recentFailedAttempt(): bool
    {
        $path=self::statusPath();if(!is_file($path)||(time()-(int)@filemtime($path))>=self::RETRY_SECONDS)return false;$raw=json_decode((string)@file_get_contents($path),true);return is_array($raw)&&($raw['ok']??null)===false;
    }

    private static function record(bool $ok,string $message): void
    {
        $storage=dirname(__DIR__).'/storage';if(!is_dir($storage))@mkdir($storage,0750,true);if(!is_dir($storage)||!is_writable($storage))return;@file_put_contents(self::statusPath(),json_encode(['ok'=>$ok,'message'=>$message,'checked_at'=>date(DATE_ATOM)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
    }
}
