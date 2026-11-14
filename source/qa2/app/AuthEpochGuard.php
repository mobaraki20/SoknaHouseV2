<?php
declare(strict_types=1);

/**
 * Review-only session-generation guard. Restoring an old DB can reuse a user ID
 * for a different person. All previous PHP sessions must be invalidated before
 * the restored user records become reachable again.
 *
 * A generation lives OUTSIDE database backups. Absence means an installation
 * that has never performed a guarded restore; it does not log out legacy users.
 */
final class AuthEpochGuard
{
    private static function path(string $root): string
    {
        return rtrim($root, '/').'/storage/runtime/auth-restore-generation';
    }

    public static function current(string $root): ?string
    {
        $path=self::path($root);
        clearstatcache(true,$path);
        if (!file_exists($path)) return null;
        $raw=@file_get_contents($path);
        if (!is_string($raw) || !preg_match('/^[a-f0-9]{64}\n?$/D',$raw)) {
            throw new RuntimeException('وضعیت اعتبار نشست پس از بازیابی نامعتبر است. ورود موقتاً مسدود شد.');
        }
        return trim($raw);
    }

    public static function stampLogin(string $root): void
    {
        $current=self::current($root);
        if ($current===null) unset($_SESSION['sokna_auth_generation']);
        else $_SESSION['sokna_auth_generation']=$current;
    }

    /** Must run before restore_remembered_login or any user-ID-based refresh. */
    public static function enforce(string $root): bool
    {
        $current=self::current($root);
        if ($current===null || empty($_SESSION['user'])) return true;
        $stored=(string)($_SESSION['sokna_auth_generation']??'');
        if (hash_equals($current,$stored)) return true;
        // Do not query the restored users table using a stale user ID.
        $_SESSION=[];
        if (session_status()===PHP_SESSION_ACTIVE && !session_regenerate_id(true)) {
            throw new RuntimeException('نشست قدیمی پس از بازیابی قابل ابطال نیست.');
        }
        if (function_exists('remember_cookie_name') && function_exists('auth_cookie_path') && function_exists('auth_cookie_secure')) {
            $name=remember_cookie_name();
            unset($_COOKIE[$name]);
            setcookie($name,'',['expires'=>time()-3600,'path'=>auth_cookie_path(),'secure'=>auth_cookie_secure(),'httponly'=>true,'samesite'=>'Lax']);
        }
        return false;
    }

    /** Call after committed restore, before ending the exclusive RestoreGate. */
    public static function rotate(string $root): string
    {
        $path=self::path($root);$dir=dirname($path);
        if (!is_dir($dir) && !@mkdir($dir,0750,true) && !is_dir($dir)) {
            throw new RuntimeException('فضای ایمن ابطال نشست‌ها در دسترس نیست.');
        }
        $new=bin2hex(random_bytes(32));
        $temp=$dir.'/.auth-restore-generation-'.bin2hex(random_bytes(8));
        try {
            if (@file_put_contents($temp,$new."\n",LOCK_EX)!==65) throw new RuntimeException('ثبت نسل جدید نشست موفق نبود.');
            @chmod($temp,0600);
            if (!@rename($temp,$path)) throw new RuntimeException('ثبت اتمیک نسل نشست موفق نبود.');
            clearstatcache(true,$path);
            return $new;
        } finally { if (is_file($temp)) @unlink($temp); }
    }
}
