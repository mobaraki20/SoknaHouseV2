<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/CafeApiV2.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Sokna-API-Version: '.CafeApiV2::VERSION);
ignore_user_abort(true);

function api_tracking_id(): string
{
    $incoming=trim((string)($_SERVER['HTTP_X_TRACKING_ID']??$_SERVER['HTTP_X_REQUEST_ID']??''));
    if($incoming!==''&&preg_match('/^[A-Za-z0-9._:-]{6,80}$/',$incoming))return $incoming;
    return 'HOUSE-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(4)));
}

$GLOBALS['CAFE_API_TRACKING_ID']=api_tracking_id();
header('X-Sokna-Tracking-ID: '.$GLOBALS['CAFE_API_TRACKING_ID']);

$GLOBALS['CAFE_API_LOG_CONTEXT'] = [
    'action'=>trim((string)($_GET['action'] ?? '')),
    'external_order_id'=>'',
    'reservation_code'=>'',
    'payload_hash'=>null,
    'tracking_id'=>$GLOBALS['CAFE_API_TRACKING_ID'],
];
$GLOBALS['CAFE_API_RAW_PAYLOAD'] = null;

function api_log_response(array $data, int $status): void
{
    $ctx = $GLOBALS['CAFE_API_LOG_CONTEXT'] ?? [];
    $action = (string)($ctx['action'] ?? '');
    $externalOrderId = (string)($ctx['external_order_id'] ?? '');
    if (!in_array($action, ['charge','void'], true) || $externalOrderId === '') return;
    $resultCode = (string)($data['status'] ?? $data['error'] ?? (!empty($data['ok']) ? 'ok' : 'error'));
    $transactionId = trim((string)($data['transaction_id'] ?? '')) ?: null;
    try {
        db()->prepare(
            'INSERT INTO '.table('cafe_api_request_logs').' (action,external_order_id,reservation_code,payload_hash,http_status,result_code,transaction_id,idempotent,tracking_id,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,NOW())'
        )->execute([
            $action,
            $externalOrderId,
            (string)($ctx['reservation_code'] ?? '') ?: null,
            $ctx['payload_hash'] ?? null,
            $status,
            text_substr($resultCode, 0, 80),
            $transactionId,
            !empty($data['idempotent']) ? 1 : 0,
            (string)($ctx['tracking_id'] ?? $GLOBALS['CAFE_API_TRACKING_ID'] ?? ''),
        ]);
    } catch (Throwable) {
        // ثبت لاگ نباید نتیجه عملیات مالی را تغییر دهد.
    }
}

function api_out(array $data, int $status = 200): never
{
    if(!array_key_exists('tracking_id',$data))$data['tracking_id']=(string)($GLOBALS['CAFE_API_TRACKING_ID']??'');
    api_log_response($data, $status);
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

function api_error(string $code, string $message, int $status): never
{
    api_out(['ok'=>false, 'error'=>$code, 'message'=>$message], $status);
}

function api_host_is_local(): bool
{
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return in_array($host, ['localhost','127.0.0.1','::1'], true)
        && in_array($remote, ['127.0.0.1','::1'], true);
}

function api_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) return true;
    $cfg = app_config();
    if (empty($cfg['cafe_api']['trust_proxy_https'])) return false;
    $trusted = array_values(array_filter(array_map('trim', (array)($cfg['cafe_api']['trusted_proxy_ips'] ?? []))));
    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    if (!$trusted || $remote === '' || !in_array($remote, $trusted, true)) return false;
    $forwarded = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
    return $forwarded === 'https';
}

function api_allow_local_http(): bool
{
    $cfg = app_config();
    return !empty($cfg['cafe_api']['allow_http_localhost']);
}

function api_bearer_token(): ?string
{
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($auth === '' && function_exists('getallheaders')) {
        foreach ((array)getallheaders() as $key=>$value) {
            if (strcasecmp((string)$key, 'Authorization') === 0) {
                $auth = (string)$value;
                break;
            }
        }
    }
    return preg_match('/^Bearer\s+(.+)$/i', $auth, $m) ? trim($m[1]) : null;
}

function api_rate_limit_cleanup(string $dir,int $now): void
{
    $marker=$dir.'/.cleanup';$last=is_file($marker)?(int)@filemtime($marker):0;if($last>0&&$last>$now-300)return;$fh=@fopen($marker,'c+');if(!$fh)return;if(!flock($fh,LOCK_EX|LOCK_NB)){fclose($fh);return;}clearstatcache(true,$marker);$last=is_file($marker)?(int)@filemtime($marker):0;if($last===0||$last<=$now-300){foreach((array)glob($dir.'/*.json') as $file){if(is_file($file)&&(int)@filemtime($file)<$now-600)@unlink($file);}@touch($marker,$now);}flock($fh,LOCK_UN);fclose($fh);
}

function api_rate_limit(string $tokenHash,string $action): void
{
    $group=in_array($action,['charge','void'],true)?'write':($action==='search'?'search':'read');
    $cfg=(array)(app_config()['cafe_api']['rate_limits']??[]);$defaults=['read'=>120,'search'=>90,'write'=>30];$limit=max(1,(int)($cfg[$group]??$defaults[$group]));$window=60;$now=time();$bucket=intdiv($now,$window);$reset=($bucket+1)*$window;
    $ip=trim((string)($_SERVER['REMOTE_ADDR']??'unknown'));$fingerprint=substr($tokenHash,0,16);$key=hash('sha256',$fingerprint.'|'.$ip.'|'.$action.'|'.$bucket);$dir=__DIR__.'/storage/runtime/cafe-rate-limit';$path=$dir.'/'.$key.'.json';$count=1;$storageOk=true;
    try{if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('rate_limit_storage_unavailable');api_rate_limit_cleanup($dir,$now);$fh=@fopen($path,'c+');if(!$fh)throw new RuntimeException('rate_limit_file_unavailable');if(!flock($fh,LOCK_EX))throw new RuntimeException('rate_limit_lock_failed');$raw=stream_get_contents($fh);$row=$raw!==false&&trim($raw)!==''?json_decode($raw,true):null;$count=max(0,(int)($row['count']??0))+1;ftruncate($fh,0);rewind($fh);fwrite($fh,json_encode(['count'=>$count,'reset'=>$reset],JSON_UNESCAPED_SLASHES));fflush($fh);flock($fh,LOCK_UN);fclose($fh);}catch(Throwable $e){$storageOk=false;log_exception($e,'cafe_api_rate_limit',['tracking_id'=>$GLOBALS['CAFE_API_TRACKING_ID']??'','action'=>$action]);}
    if(!$storageOk)return;
    $remaining=max(0,$limit-$count);header('RateLimit-Limit: '.$limit);header('RateLimit-Remaining: '.$remaining);header('RateLimit-Reset: '.$reset);
    if($count>$limit){$retry=max(1,$reset-$now);header('Retry-After: '.$retry);api_error('rate_limited','تعداد درخواست‌ها در این بازه بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.',429);}
}

function api_json_payload(): array
{
    if (is_array($GLOBALS['CAFE_API_RAW_PAYLOAD'])) return $GLOBALS['CAFE_API_RAW_PAYLOAD'];
    $contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0] ?? ''));
    if ($contentType !== 'application/json') api_error('unsupported_media_type', 'Content-Type باید application/json باشد.', 415);
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') api_error('invalid_request', 'بدنه JSON الزامی است.', 400);
    if (strlen($raw) > 262144) api_error('payload_too_large', 'حجم درخواست بیش از حد مجاز است.', 413);
    try {
        $payload = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        api_error('invalid_request', 'بدنه درخواست JSON معتبر نیست.', 400);
    }
    if (!is_array($payload) || array_is_list($payload)) api_error('invalid_request', 'ساختار درخواست معتبر نیست.', 400);
    $GLOBALS['CAFE_API_RAW_PAYLOAD'] = $payload;
    return $payload;
}

function api_transaction_id(string $kind): string
{
    return 'ACC-'.$kind.'-'.gmdate('YmdHis').'-'.strtoupper(bin2hex(random_bytes(5)));
}

function api_room_names(int $reservationId): array
{
    $stmt = db()->prepare(
        'SELECT COALESCE(NULLIF(rr.room_name_snapshot,\'\'),rooms.name) room_name, rooms.sort_order, rr.id
         FROM '.table('reservation_rooms').' rr
         JOIN '.table('rooms').' rooms ON rooms.id=rr.room_id
         WHERE rr.reservation_id=?
         ORDER BY rooms.sort_order,rr.id'
    );
    $stmt->execute([$reservationId]);
    $names = array_map(static fn(array $r): string => (string)$r['room_name'], $stmt->fetchAll());
    return array_values(array_unique(array_filter($names, static fn(string $name): bool => trim($name) !== '')));
}

function api_room_search_values(int $reservationId): array
{
    $stmt = db()->prepare(
        'SELECT COALESCE(NULLIF(rr.room_name_snapshot,\'\'),rooms.name) room_name,rooms.slug
         FROM '.table('reservation_rooms').' rr
         JOIN '.table('rooms').' rooms ON rooms.id=rr.room_id
         WHERE rr.reservation_id=?'
    );
    $stmt->execute([$reservationId]);
    $values=[];
    foreach($stmt->fetchAll() as $row){
        foreach([(string)$row['room_name'],(string)$row['slug']] as $value){
            $normalized=CafeApiV2::normalizeText($value);if($normalized!=='')$values[]=$normalized;
        }
    }
    return array_values(array_unique($values));
}

function api_fetch_reservations(string $where, array $args = [], int $limit = 100): array
{
    $limit = max(1, min(500, $limit));
    $sql = 'SELECT r.id,r.code,r.status,
                   COALESCE(NULLIF(r.guest_name_snapshot,\'\'),g.display_name) guest_name,
                   COALESCE(NULLIF(r.phone_snapshot,\'\'),g.phone_normalized) mobile,
                   MIN(rr.check_in) check_in,
                   MAX(rr.check_out) check_out,
                   MAX(CASE WHEN ?>=rr.check_in AND ?<rr.check_out THEN 1 ELSE 0 END) has_active_allocation
            FROM '.table('reservations').' r
            JOIN '.table('guests').' g ON g.id=r.guest_id
            JOIN '.table('reservation_rooms').' rr ON rr.reservation_id=r.id
            JOIN '.table('rooms').' rooms ON rooms.id=rr.room_id
            WHERE '.$where.'
            GROUP BY r.id,r.code,r.status,r.guest_name_snapshot,g.display_name,r.phone_snapshot,g.phone_normalized
            ORDER BY MAX(rr.check_out) DESC,r.id DESC
            LIMIT '.$limit;
    $stmt = db()->prepare($sql);
    $today=sokna_today();
    $stmt->execute(array_merge([$today,$today],$args));
    return $stmt->fetchAll();
}

function api_fetch_reservation_by_code(string $code): ?array
{
    $sql = 'SELECT r.id,r.code,r.status,
                   COALESCE(NULLIF(r.guest_name_snapshot,\'\'),g.display_name) guest_name,
                   COALESCE(NULLIF(r.phone_snapshot,\'\'),g.phone_normalized) mobile,
                   MIN(rr.check_in) check_in,
                   MAX(rr.check_out) check_out,
                   MAX(CASE WHEN ?>=rr.check_in AND ?<rr.check_out THEN 1 ELSE 0 END) has_active_allocation
            FROM '.table('reservations').' r
            JOIN '.table('guests').' g ON g.id=r.guest_id
            JOIN '.table('reservation_rooms').' rr ON rr.reservation_id=r.id
            WHERE r.code=?
            GROUP BY r.id,r.code,r.status,r.guest_name_snapshot,g.display_name,r.phone_snapshot,g.phone_normalized
            LIMIT 1';
    $stmt = db()->prepare($sql);
    $today=sokna_today();
    $stmt->execute([$today,$today,$code]);
    return $stmt->fetch() ?: null;
}

function api_lock_reservation_id(string $code): ?int
{
    $stmt = db()->prepare('SELECT id FROM '.table('reservations').' WHERE code=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$code]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
}

function api_reservation_response(array $row): array
{
    $reason = CafeApiV2::chargeBlockReason(
        (string)$row['status'],
        $row['check_in'] !== null ? (string)$row['check_in'] : null,
        $row['check_out'] !== null ? (string)$row['check_out'] : null,
        (bool)$row['has_active_allocation'],
        sokna_today()
    );
    $maskedMobile=CafeApiV2::maskMobile((string)($row['mobile'] ?? ''));
    return [
        'reservation_code'=>(string)$row['code'],
        'guest_name'=>(string)$row['guest_name'],
        'room_names'=>api_room_names((int)$row['id']),
        'check_in'=>(string)$row['check_in'],
        'check_out'=>(string)$row['check_out'],
        'masked_mobile'=>$maskedMobile,
        // Current Café Local reads phone_hint; keep masked_mobile for API 2.0 clients.
        'phone_hint'=>$maskedMobile,
        'charge_allowed'=>$reason === null,
        'charge_block_reason'=>$reason,
    ];
}

function api_text_variants(string $token): array
{
    $variants = [$token];
    $variants[] = strtr($token, ['ی'=>'ي','ک'=>'ك']);
    $variants[] = strtr($token, ['ي'=>'ی','ك'=>'ک']);
    $variants[] = strtr($token, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
    $variants[] = strtr($token, ['0'=>'٠','1'=>'١','2'=>'٢','3'=>'٣','4'=>'٤','5'=>'٥','6'=>'٦','7'=>'٧','8'=>'٨','9'=>'٩']);
    return array_values(array_unique(array_filter(array_map('trim', $variants))));
}

function api_text_candidates(string $query): array
{
    $normalized = CafeApiV2::normalizeText($query);
    $tokens = array_values(array_filter(explode(' ', $normalized), static fn(string $token): bool => $token !== ''));
    if (!$tokens) return [];
    $tokens = array_slice($tokens, 0, 12);

    $columns = [
        'COALESCE(NULLIF(r.guest_name_snapshot,\'\'),g.display_name)',
        'COALESCE(NULLIF(rr.room_name_snapshot,\'\'),rooms.name)',
        'rooms.slug',
    ];
    $normalizedColumns = array_map(static fn(string $column): string =>
        "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE($column,''),'ي','ی'),'ى','ی'),'ك','ک'),'‌',' '),'ۀ','ه'))",
        $columns
    );
    $compactColumns = array_map(static fn(string $column): string =>
        "REPLACE(REPLACE(REPLACE($column,' ',''),'-',''),'ـ','')",
        $normalizedColumns
    );

    $clauses = [];
    $args = [];
    foreach ($tokens as $token) {
        $or = [];
        foreach (api_text_variants($token) as $variant) {
            foreach ($normalizedColumns as $column) {
                $or[] = $column.' LIKE ?';
                $args[] = '%'.$variant.'%';
            }
            $compact = normalize_name_key($variant);
            if ($compact !== '') {
                foreach ($compactColumns as $column) {
                    $or[] = $column.' LIKE ?';
                    $args[] = '%'.$compact.'%';
                }
            }
        }
        $clauses[] = '('.implode(' OR ', $or).')';
    }
    return api_fetch_reservations(implode(' AND ', $clauses), $args, 200);
}

function api_unified_search(string $query): array
{
    $query = trim($query);
    if ($query === '' || strlen($query) > 640) api_error('invalid_request', 'عبارت جست‌وجو معتبر نیست.', 400);
    if (isset($_GET['type'])) api_error('invalid_request', 'پارامتر type پشتیبانی نمی‌شود؛ فقط query ارسال شود.', 400);

    $normalizedQuery = CafeApiV2::normalizeText($query);
    $results = [];
    $add = static function(array $row, int $score) use (&$results): void {
        $code = (string)$row['code'];
        $current = $results[$code]['score'] ?? -1;
        if ($score > $current) $results[$code] = ['row'=>$row,'score'=>$score];
    };

    $codeQuery = CafeApiV2::normalizeReservationCode($query);
    foreach (api_fetch_reservations('r.code=?', [$codeQuery], 5) as $row) $add($row, 100);

    $digits = preg_replace('/\D+/', '', Jalali::toEnglishDigits($query)) ?? '';
    if (strlen($digits) >= 10) {
        $phone = normalize_phone($query);
        if ($phone !== null) {
            foreach (api_fetch_reservations('(r.phone_snapshot=? OR g.phone_normalized=?)', [$phone,$phone], 50) as $row) $add($row, 90);
        }
    }

    foreach (api_text_candidates($query) as $row) {
        $guest = CafeApiV2::normalizeText((string)$row['guest_name']);
        $guestCompact = normalize_name_key($guest);
        $queryCompact = normalize_name_key($normalizedQuery);
        $rooms = api_room_search_values((int)$row['id']);
        $score = -1;
        if ($guest === $normalizedQuery || ($queryCompact !== '' && $guestCompact === $queryCompact)) $score = max($score, 60);
        elseif ($normalizedQuery !== '' && (str_contains($guest, $normalizedQuery) || ($queryCompact !== '' && str_contains($guestCompact, $queryCompact)))) $score = max($score, 50);
        foreach ($rooms as $room) {
            $roomCompact = normalize_name_key($room);
            if ($room === $normalizedQuery || ($queryCompact !== '' && $roomCompact === $queryCompact)) $score = max($score, 80);
            elseif ($normalizedQuery !== '' && (str_contains($room, $normalizedQuery) || ($queryCompact !== '' && str_contains($roomCompact, $queryCompact)))) $score = max($score, 70);
        }
        if ($score >= 0) $add($row, $score);
    }

    foreach ($results as $key=>$entry) {
        $reason = CafeApiV2::chargeBlockReason(
            (string)$entry['row']['status'],
            (string)$entry['row']['check_in'],
            (string)$entry['row']['check_out'],
            (bool)$entry['row']['has_active_allocation'],
            sokna_today()
        );
        if ($reason === null) $results[$key]['score'] += 5;
    }
    uasort($results, static function(array $a, array $b): int {
        $score = $b['score'] <=> $a['score'];
        if ($score !== 0) return $score;
        return strcmp((string)$b['row']['check_in'], (string)$a['row']['check_in']);
    });
    $reservations = [];
    foreach (array_slice($results, 0, 20, true) as $entry) $reservations[] = api_reservation_response($entry['row']);
    return $reservations;
}

function api_existing_charge(string $externalOrderId, bool $forUpdate = false): ?array
{
    $sql = 'SELECT c.id,c.reservation_id,c.external_order_id,c.transaction_id,c.amount,c.currency,c.payload_hash,c.status,r.code reservation_code
            FROM '.table('cafe_charges').' c
            JOIN '.table('reservations').' r ON r.id=c.reservation_id
            WHERE c.external_order_id=? LIMIT 1'.($forUpdate ? ' FOR UPDATE' : '');
    $stmt = db()->prepare($sql);
    $stmt->execute([$externalOrderId]);
    return $stmt->fetch() ?: null;
}

function api_replay_charge_or_conflict(array $existing, array $validated): never
{
    if (!CafeApiV2::isSameCharge($existing, $validated)) {
        api_error('external_order_conflict', 'این external_order_id قبلاً با رزرو، مبلغ یا Snapshot دیگری ثبت شده است.', 409);
    }
    api_out([
        'ok'=>true,
        'status'=>(string)$existing['status'],
        'transaction_id'=>(string)$existing['transaction_id'],
        'external_order_id'=>$validated['external_order_id'],
        'idempotent'=>true,
    ]);
}

set_exception_handler(function(Throwable $e): void {
    $ctx=$GLOBALS['CAFE_API_LOG_CONTEXT']??[];
    log_exception($e, 'cafe_api_v2_uncaught', [
        'tracking_id'=>$GLOBALS['CAFE_API_TRACKING_ID']??'',
        'action'=>$ctx['action']??'',
        'external_order_id'=>$ctx['external_order_id']??'',
        'reservation_code'=>$ctx['reservation_code']??'',
        'schema_version'=>cafe_api_schema_status()['version']??'unknown',
    ]);
    api_error('internal_error', 'خطای داخلی سامانه رخ داد.', 500);
});

try {
    if (!api_is_https() && !(api_allow_local_http() && api_host_is_local())) {
        api_error('https_required', 'API در محیط تولید فقط از طریق HTTPS در دسترس است.', 400);
    }

    $providedToken = api_bearer_token();
    $storedHash = cafe_api_token_hash();
    if ($storedHash === '' || $providedToken === null || !hash_equals($storedHash, hash('sha256', $providedToken))) {
        api_error('unauthorized', 'Bearer Token معتبر نیست.', 401);
    }
    if (!feature_enabled('cafe')) api_error('module_disabled', 'قابلیت اتصال کافه غیرفعال است.', 403);

    $action = trim((string)($_GET['action'] ?? ''));
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    api_rate_limit(hash('sha256',$providedToken),$action!==''?$action:'unknown');

    if ($action === 'capabilities' && $method === 'GET') {
        api_out([
            'ok'=>true,
            'api_version'=>CafeApiV2::VERSION,
            'capabilities'=>[
                'unified_search'=>true,
                'invoice_snapshot'=>true,
                'invoice_snapshot_versions'=>[1,2,3],
                'charge_request_metadata'=>true,
                'search_phone_hint'=>true,
                'idempotent_charge'=>true,
                'charge_void'=>true,
            ],
            'schema_ready'=>(bool)(cafe_api_schema_status()['ready']??false),
        ]);
    }

    if ($action === 'search' && $method === 'GET') {
        api_out(['ok'=>true, 'reservations'=>api_unified_search((string)($_GET['query'] ?? ''))]);
    }

    if ($action === 'reservation' && $method === 'GET') {
        $code = CafeApiV2::normalizeReservationCode((string)($_GET['reservation_code'] ?? ''));
        if ($code === '' || strlen($code) > 40) api_error('invalid_request', 'کد رزرو معتبر نیست.', 400);
        $reservation = api_fetch_reservation_by_code($code);
        if (!$reservation) api_error('reservation_not_found', 'رزرو موردنظر پیدا نشد.', 404);
        api_out(['ok'=>true, 'reservation'=>api_reservation_response($reservation)]);
    }

    if ($action === 'charge' && $method === 'POST') {
        $schemaStatus=cafe_api_schema_status();
        if(empty($schemaStatus['ready'])){
            log_exception(new RuntimeException('Cafe API schema is not ready.'),'cafe_api_v2_schema',[
                'tracking_id'=>$GLOBALS['CAFE_API_TRACKING_ID']??'',
                'schema_version'=>$schemaStatus['version']??'unknown',
            ]);
            api_error('schema_not_ready','ساختار نوشتن اتصال کافه کامل نیست؛ مدیر بخش «اتصال کافه» و کد پیگیری پاسخ را بررسی کند.',503);
        }
        try {
            $validated = CafeApiV2::validateChargePayload(api_json_payload());
        } catch (CafeApiException $e) {
            $payload = $GLOBALS['CAFE_API_RAW_PAYLOAD'] ?? [];
            if (is_array($payload)) {
                $GLOBALS['CAFE_API_LOG_CONTEXT']['external_order_id'] = trim((string)($payload['external_order_id'] ?? ''));
                $GLOBALS['CAFE_API_LOG_CONTEXT']['reservation_code'] = trim((string)($payload['reservation_code'] ?? ''));
            }
            api_error($e->apiCode, $e->getMessage(), $e->httpStatus);
        }
        $GLOBALS['CAFE_API_LOG_CONTEXT']['external_order_id'] = $validated['external_order_id'];
        $GLOBALS['CAFE_API_LOG_CONTEXT']['reservation_code'] = $validated['reservation_code'];
        $GLOBALS['CAFE_API_LOG_CONTEXT']['payload_hash'] = $validated['payload_hash'];

        db()->beginTransaction();
        try {
            $existing = api_existing_charge($validated['external_order_id'], true);
            if ($existing) {
                db()->commit();
                api_replay_charge_or_conflict($existing, $validated);
            }

            $lockedReservationId = api_lock_reservation_id($validated['reservation_code']);
            $reservation = $lockedReservationId === null ? null : api_fetch_reservation_by_code($validated['reservation_code']);
            if (!$reservation || (int)$reservation['id'] !== $lockedReservationId) {
                db()->rollBack();
                api_error('reservation_not_found', 'رزرو موردنظر پیدا نشد.', 404);
            }
            $blockReason = CafeApiV2::chargeBlockReason(
                (string)$reservation['status'],
                (string)$reservation['check_in'],
                (string)$reservation['check_out'],
                (bool)$reservation['has_active_allocation'],
                sokna_today()
            );
            if ($blockReason !== null) {
                db()->rollBack();
                api_error('reservation_not_chargeable', $blockReason, 422);
            }

            $transactionId = api_transaction_id('TRX');
            db()->prepare(
                'INSERT INTO '.table('cafe_charges').' (
                    reservation_id,external_order_id,transaction_id,invoice_number,ordered_at,amount,
                    subtotal_amount,discount_amount,currency,invoice_version,table_name,
                    invoice_snapshot_json,payload_hash,status,created_at
                 ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,\'posted\',NOW())'
            )->execute([
                (int)$reservation['id'],
                $validated['external_order_id'],
                $transactionId,
                $validated['invoice']['number'],
                $validated['ordered_at_local'],
                $validated['amount'],
                $validated['invoice']['subtotal'],
                $validated['invoice']['discount'],
                $validated['currency'],
                $validated['invoice']['version'],
                $validated['invoice']['table_name'],
                $validated['invoice_json'],
                $validated['payload_hash'],
            ]);
            $chargeId = (int)db()->lastInsertId();
            $itemStmt = db()->prepare(
                'INSERT INTO '.table('cafe_charge_items').' (cafe_charge_id,item_name,quantity,unit_price,line_total,item_note,sort_order)
                 VALUES (?,?,?,?,?,?,?)'
            );
            foreach ($validated['invoice']['items'] as $itemIndex => $item) {
                $sortOrder = isset($item['sort_order']) && is_int($item['sort_order'])
                    ? max(0, min(65535, $item['sort_order']))
                    : $itemIndex;
                $itemStmt->execute([
                    $chargeId,$item['name'],$item['quantity'],$item['unit_price'],$item['line_total'],$item['note'],$sortOrder,
                ]);
            }
            db()->commit();
            audit('cafe_charge_posted', 'cafe_charge', $chargeId, [
                'external_order_id'=>$validated['external_order_id'],
                'reservation_code'=>$validated['reservation_code'],
                'transaction_id'=>$transactionId,
                'amount'=>$validated['amount'],
                'currency'=>$validated['currency'],
                'invoice_number'=>$validated['invoice']['number'],
                'invoice_version'=>$validated['invoice']['version'],
                'items_count'=>count($validated['invoice']['items']),
                'requested_at'=>$validated['requested_at_iso']??null,
                'client_payload_hash'=>$validated['client_payload_hash']??null,
            ]);
            api_out([
                'ok'=>true,
                'status'=>'posted',
                'transaction_id'=>$transactionId,
                'external_order_id'=>$validated['external_order_id'],
                'idempotent'=>false,
            ]);
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            try{
                $existing = api_existing_charge($validated['external_order_id'], false);
                if ($existing) api_replay_charge_or_conflict($existing, $validated);
            }catch(Throwable $lookupError){
                log_exception($lookupError,'cafe_api_v2_charge_lookup',[
                    'tracking_id'=>$GLOBALS['CAFE_API_TRACKING_ID']??'',
                    'external_order_id'=>$validated['external_order_id'],
                ]);
            }
            $schemaStatus=cafe_api_schema_status();
            log_exception($e, 'cafe_api_v2_charge', [
                'tracking_id'=>$GLOBALS['CAFE_API_TRACKING_ID']??'',
                'external_order_id'=>$validated['external_order_id'],
                'reservation_code'=>$validated['reservation_code'],
                'schema_version'=>$schemaStatus['version']??'unknown',
                'schema_ready'=>!empty($schemaStatus['ready']),
            ]);
            if(empty($schemaStatus['ready']))api_error('schema_not_ready','ساختار نوشتن اتصال کافه کامل نیست؛ مدیر بخش «اتصال کافه» و کد پیگیری پاسخ را بررسی کند.',503);
            $sqlState=$e instanceof PDOException?(string)$e->getCode():'';$driverCode=$e instanceof PDOException?(int)($e->errorInfo[1]??0):0;
            if($sqlState==='40001'||in_array($driverCode,[1205,1213],true))api_error('temporary_failure','ثبت هزینه موقتاً کامل نشد؛ همان درخواست و همان external_order_id دوباره ارسال شود.',503);
            api_error('internal_error', 'خطای داخلی سامانه رخ داد؛ مدیر کد پیگیری را بررسی کند.', 500);
        }
    }

    if ($action === 'void' && $method === 'POST') {
        try {
            $validated = CafeApiV2::validateVoidPayload(api_json_payload());
        } catch (CafeApiException $e) {
            $payload = $GLOBALS['CAFE_API_RAW_PAYLOAD'] ?? [];
            if (is_array($payload)) $GLOBALS['CAFE_API_LOG_CONTEXT']['external_order_id'] = trim((string)($payload['external_order_id'] ?? ''));
            api_error($e->apiCode, $e->getMessage(), $e->httpStatus);
        }
        $GLOBALS['CAFE_API_LOG_CONTEXT']['external_order_id'] = $validated['external_order_id'];

        db()->beginTransaction();
        try {
            $charge = api_existing_charge($validated['external_order_id'], true);
            if (!$charge) {
                db()->rollBack();
                api_error('unknown_external_order_id', 'external_order_id در حساب اقامتگاه پیدا نشد.', 404);
            }
            $GLOBALS['CAFE_API_LOG_CONTEXT']['reservation_code'] = (string)$charge['reservation_code'];
            $GLOBALS['CAFE_API_LOG_CONTEXT']['payload_hash'] = (string)$charge['payload_hash'];
            $voidStmt = db()->prepare('SELECT id,transaction_id FROM '.table('cafe_charge_voids').' WHERE cafe_charge_id=? LIMIT 1 FOR UPDATE');
            $voidStmt->execute([(int)$charge['id']]);
            if ($void = $voidStmt->fetch()) {
                db()->commit();
                api_out([
                    'ok'=>true,
                    'status'=>'voided',
                    'transaction_id'=>(string)$void['transaction_id'],
                    'original_transaction_id'=>(string)$charge['transaction_id'],
                    'external_order_id'=>$validated['external_order_id'],
                    'idempotent'=>true,
                ]);
            }
            if ((string)$charge['status'] !== 'posted') {
                db()->rollBack();
                api_error('charge_not_voidable', 'تراکنش اصلی در وضعیت قابل برگشت نیست.', 409);
            }

            $voidTransactionId = api_transaction_id('VOID');
            db()->prepare(
                'INSERT INTO '.table('cafe_charge_voids').' (cafe_charge_id,transaction_id,reason,requested_at,created_at)
                 VALUES (?,?,?,?,NOW())'
            )->execute([(int)$charge['id'],$voidTransactionId,$validated['reason'],$validated['requested_at_local']]);
            $voidId = (int)db()->lastInsertId();
            db()->prepare('UPDATE '.table('cafe_charges').' SET status=\'voided\',voided_at=COALESCE(voided_at,NOW()) WHERE id=?')
                ->execute([(int)$charge['id']]);
            db()->commit();
            audit('cafe_charge_voided', 'cafe_charge_void', $voidId, [
                'external_order_id'=>$validated['external_order_id'],
                'transaction_id'=>$voidTransactionId,
                'original_transaction_id'=>(string)$charge['transaction_id'],
                'reason'=>$validated['reason'],
                'requested_at'=>$validated['requested_at_iso'],
            ]);
            api_out([
                'ok'=>true,
                'status'=>'voided',
                'transaction_id'=>$voidTransactionId,
                'original_transaction_id'=>(string)$charge['transaction_id'],
                'external_order_id'=>$validated['external_order_id'],
                'idempotent'=>false,
            ]);
        } catch (PDOException $e) {
            if (db()->inTransaction()) db()->rollBack();
            $charge = api_existing_charge($validated['external_order_id'], false);
            if ($charge) {
                $voidStmt = db()->prepare('SELECT transaction_id FROM '.table('cafe_charge_voids').' WHERE cafe_charge_id=? LIMIT 1');
                $voidStmt->execute([(int)$charge['id']]);
                if ($voidTransactionId = $voidStmt->fetchColumn()) {
                    api_out([
                        'ok'=>true,
                        'status'=>'voided',
                        'transaction_id'=>(string)$voidTransactionId,
                        'original_transaction_id'=>(string)$charge['transaction_id'],
                        'external_order_id'=>$validated['external_order_id'],
                        'idempotent'=>true,
                    ]);
                }
            }
            log_exception($e, 'cafe_api_v2_void');
            api_error('internal_error', 'خطای داخلی سامانه رخ داد.', 500);
        }
    }

    api_error('invalid_request', 'مسیر یا روش درخواست معتبر نیست.', 400);
} catch (CafeApiException $e) {
    api_error($e->apiCode, $e->getMessage(), $e->httpStatus);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    $ctx=$GLOBALS['CAFE_API_LOG_CONTEXT']??[];
    log_exception($e, 'cafe_api_v2', [
        'tracking_id'=>$GLOBALS['CAFE_API_TRACKING_ID']??'',
        'action'=>$ctx['action']??'',
    ]);
    api_error('internal_error', 'خطای داخلی سامانه رخ داد.', 500);
}
