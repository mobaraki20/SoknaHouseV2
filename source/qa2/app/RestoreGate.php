<?php
declare(strict_types=1);

/**
 * Review-only QA candidate. Coordinates PHP requests within one shared filesystem
 * and isolates destructive manual restore from in-flight/arriving writes.
 * Intentionally does NOT auto-remove markers after a crash: recovery is manual.
 */
final class RestoreGate
{
    private static $requestHandle = null;
    private static ?string $activeOperation = null;

    private static function paths(string $root): array
    {
        $storage = rtrim($root, '/').'/storage';
        if (!is_dir($storage) && !@mkdir($storage, 0770, true) && !is_dir($storage)) {
            throw new RuntimeException('فضای قفل نگهداری قابل استفاده نیست.');
        }
        return [
            'gate' => $storage.'/restore-gate.flock',
            'marker' => $storage.'/restore-operation.lock',
            'update_pending' => $storage.'/update-operation.lock',
            'maintenance' => $storage.'/maintenance.lock',
        ];
    }

    public static function enterRequest(string $root): void
    {
        if (is_resource(self::$requestHandle)) return;
        $p = self::paths($root);
        // Check both sides of flock: a marker can be created while opening gate.
        if (is_file($p['marker']) || is_file($p['update_pending'])) throw new RuntimeException('عملیات نگهداری در جریان است.');
        $h = @fopen($p['gate'], 'c+b');
        if (!is_resource($h)) throw new RuntimeException('دروازه حفاظتی قابل بازشدن نیست.');
        if (!@flock($h, LOCK_SH | LOCK_NB)) { fclose($h); throw new RuntimeException('دروازه حفاظتی مشغول است.'); }
        if (is_file($p['marker']) || is_file($p['update_pending'])) { flock($h, LOCK_UN); fclose($h); throw new RuntimeException('عملیات نگهداری در جریان است.'); }
        self::$requestHandle = $h;
    }

    /** Promote this same request's shared lock after marking restore as pending. */
    public static function beginRestore(string $root, float $timeoutSeconds = 15.0): string
    {
        if (!is_resource(self::$requestHandle)) throw new RuntimeException('درخواست فاقد قفل مشترک است.');
        if (self::$activeOperation !== null) throw new RuntimeException('بازیابی تودرتو مجاز نیست.');
        $p = self::paths($root);
        if (is_file($p['maintenance']) || is_file($p['marker']) || is_file($p['update_pending'])) throw new RuntimeException('عملیات نگهداری دیگری فعال است.');
        $id = 'manual-restore-'.bin2hex(random_bytes(12));
        $marker = @fopen($p['marker'], 'x');
        if (!is_resource($marker)) throw new RuntimeException('بازیابی دیگری در حال آماده‌سازی است.');
        fwrite($marker, json_encode(['operation'=>$id, 'created_at'=>date(DATE_ATOM)], JSON_THROW_ON_ERROR));
        fflush($marker); fclose($marker);
        $gotExclusive = false;
        try {
            $deadline = microtime(true) + max(0.0, $timeoutSeconds);
            do {
                if (@flock(self::$requestHandle, LOCK_EX | LOCK_NB)) { $gotExclusive = true; break; }
                if (microtime(true) >= $deadline) break;
                usleep(40_000);
            } while (true);
            if (!$gotExclusive) throw new RuntimeException('مهلت انتظار برای پایان درخواست‌های فعال تمام شد.');
            // A pre-existing updater may have started just before the marker.
            if (is_file($p['maintenance']) || is_file($p['update_pending'])) throw new RuntimeException('عملیات به‌روزرسانی در جریان است.');
            $maintenance = @fopen($p['maintenance'], 'x');
            if (!is_resource($maintenance)) throw new RuntimeException('ساخت حالت نگهداری ناموفق بود.');
            fwrite($maintenance, json_encode(['operation'=>$id, 'state'=>'restoring', 'message'=>'در حال بازیابی اطلاعات'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            fflush($maintenance); fclose($maintenance);
            self::$activeOperation = $id;
            return $id;
        } catch (Throwable $error) {
            self::unlinkOwned($p['maintenance'], $id);
            self::unlinkOwned($p['marker'], $id);
            // The active request keeps a shared gate after a failed promotion.
            @flock(self::$requestHandle, LOCK_SH);
            throw $error;
        }
    }


    /**
     * Update preflight may stage files while normal requests continue, but its
     * database snapshot MUST run after all prior admitted requests have drained.
     * A pending marker rejects new entrants while we promote SH -> EX. The
     * maintenance marker remains after finishUpdateSnapshot() across polling
     * requests, whereas the temporary pending marker is only a drain barrier.
     */
    public static function beginUpdateSnapshot(string $root, string $id, float $timeoutSeconds = 25.0): void
    {
        if (!preg_match('/^upd-[0-9]{8}-[0-9]{6}-[a-f0-9]{6}$/', $id)) throw new RuntimeException('شناسه به‌روزرسانی معتبر نیست.');
        if (!is_resource(self::$requestHandle) || self::$activeOperation !== null) throw new RuntimeException('دروازه درخواست برای به‌روزرسانی آماده نیست.');
        $p = self::paths($root);
        if (is_file($p['marker']) || is_file($p['update_pending']) || is_file($p['maintenance'])) throw new RuntimeException('عملیات نگهداری دیگری فعال است.');
        $h = @fopen($p['update_pending'], 'x');
        if (!is_resource($h)) throw new RuntimeException('آماده‌سازی انحصاری به‌روزرسانی در جریان است.');
        try {
            $raw = json_encode(['operation'=>$id, 'created_at'=>date(DATE_ATOM)], JSON_THROW_ON_ERROR);
            if (@fwrite($h, $raw) !== strlen($raw) || !@fflush($h)) throw new RuntimeException('ثبت دروازه به‌روزرسانی کامل نشد.');
        } catch (Throwable $error) {
            // We created this path with exclusive 'x'; a partial marker should
            // not strand future updates if its first write fails.
            @unlink($p['update_pending']);
            throw $error;
        } finally { fclose($h); }
        try {
            $deadline = microtime(true) + max(0.0, $timeoutSeconds);
            $exclusive = false;
            do {
                if (@flock(self::$requestHandle, LOCK_EX | LOCK_NB)) { $exclusive = true; break; }
                if (microtime(true) >= $deadline) break;
                usleep(40_000);
            } while (true);
            if (!$exclusive) throw new RuntimeException('مهلت تخلیه درخواست‌های فعال پیش از Snapshot تمام شد.');
            if (is_file($p['marker']) || is_file($p['maintenance'])) throw new RuntimeException('عملیات نگهداری دیگری فعال است.');
            $maintenance = @fopen($p['maintenance'], 'x');
            if (!is_resource($maintenance)) throw new RuntimeException('ایجاد حالت نگهداری به‌روزرسانی ناموفق بود.');
            try {
                $raw = json_encode(['operation'=>$id,'state'=>'preparing','message'=>'در حال تهیه پشتیبان ایمن'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                if (@fwrite($maintenance, $raw) !== strlen($raw) || !@fflush($maintenance)) throw new RuntimeException('ثبت حالت نگهداری به‌روزرسانی ناموفق بود.');
            } finally { fclose($maintenance); }
            self::$activeOperation = $id;
        } catch (Throwable $error) {
            self::unlinkOwned($p['maintenance'], $id);
            self::unlinkOwned($p['update_pending'], $id);
            @flock(self::$requestHandle, LOCK_SH);
            throw $error;
        }
    }

    /** Finish snapshot + journal initialization; keep maintenance for the update steps. */
    public static function finishUpdateSnapshot(string $root, string $id): void
    {
        if (self::$activeOperation !== $id || !is_resource(self::$requestHandle)) throw new RuntimeException('مالکیت آماده‌سازی به‌روزرسانی معتبر نیست.');
        $p = self::paths($root);
        if (!self::isOwned($p['maintenance'], $id)) throw new RuntimeException('نشانگر نگهداری متعلق به این به‌روزرسانی نیست.');
        if (!self::unlinkOwned($p['update_pending'], $id)) throw new RuntimeException('پاکسازی نشانگر آماده‌سازی ناموفق بود.');
        if (!@flock(self::$requestHandle, LOCK_SH)) throw new RuntimeException('بازگشت قفل به حالت مشترک ناموفق بود.');
        self::$activeOperation = null;
    }

    /** Aborting prepare must not remove another operation's maintenance marker. */
    public static function abortUpdateSnapshot(string $root, string $id): void
    {
        if (self::$activeOperation !== $id || !is_resource(self::$requestHandle)) throw new RuntimeException('مالکیت لغو آماده‌سازی معتبر نیست.');
        $p = self::paths($root);
        $m = self::unlinkOwned($p['maintenance'], $id);
        $u = self::unlinkOwned($p['update_pending'], $id);
        @flock(self::$requestHandle, LOCK_SH);
        self::$activeOperation = null;
        if (!$m || !$u) throw new RuntimeException('پاکسازی کامل آماده‌سازی ناموفق بود؛ بررسی دستی لازم است.');
    }

    private static function isOwned(string $path, string $id): bool
    {
        if (!is_file($path)) return false;
        $v = json_decode((string)@file_get_contents($path), true);
        return is_array($v) && ($v['operation'] ?? null) === $id;
    }

    public static function endRestore(string $root, string $id): void
    {
        if (!is_resource(self::$requestHandle) || self::$activeOperation !== $id) {
            throw new RuntimeException('مالکیت قفل بازیابی معتبر نیست.');
        }
        $p = self::paths($root);
        $maintenanceRemoved = self::unlinkOwned($p['maintenance'], $id);
        $markerRemoved = self::unlinkOwned($p['marker'], $id);
        self::$activeOperation = null;
        if (!@flock(self::$requestHandle, LOCK_SH) || !$maintenanceRemoved || !$markerRemoved) {
            throw new RuntimeException('آزادسازی کامل حالت نگهداری تأیید نشد؛ بررسی دستی لازم است.');
        }
    }

    private static function unlinkOwned(string $path, string $id): bool
    {
        if (!is_file($path)) return false;
        $data = json_decode((string)@file_get_contents($path), true);
        return is_array($data) && ($data['operation']??null) === $id && @unlink($path);
    }

    public static function leaveRequest(): void
    {
        if (is_resource(self::$requestHandle)) {
            @flock(self::$requestHandle, LOCK_UN);
            fclose(self::$requestHandle);
        }
        self::$requestHandle = null;
    }
}
