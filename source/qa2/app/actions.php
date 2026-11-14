<?php
declare(strict_types=1);

function handle_login(): never
{
    verify_csrf();$id=max(0,(int)($_POST['user_id']??0));$password=(string)($_POST['password']??'');$ip=(string)($_SERVER['REMOTE_ADDR']??'');
    $fails=0;try{$s=db()->prepare('SELECT COUNT(*) FROM '.table('login_attempts').' WHERE ip_address=? AND successful=0 AND created_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)');$s->execute([$ip]);$fails=(int)$s->fetchColumn();}catch(Throwable $e){}
    if($fails>=15){flash('error','تعداد تلاش‌های ناموفق زیاد بوده است. چند دقیقه بعد دوباره تلاش کنید.');redirect(url('login'));}
    $s=db()->prepare('SELECT * FROM '.table('users').' WHERE id=? AND active=1 LIMIT 1');$s->execute([$id]);$u=$s->fetch();$ok=$u&&password_verify($password,(string)$u['password_hash']);
    try{db()->prepare('INSERT INTO '.table('login_attempts').' (username,ip_address,successful) VALUES (?,?,?)')->execute([$u['username']??null,$ip,$ok?1:0]);}catch(Throwable $e){}
    if(!$ok){$_SESSION['login_user_id']=$id;flash('error','رمز عبور درست نیست.');redirect(url('login',['return'=>(string)($_POST['return']??'')]));}
    set_login_session($u,true);db()->prepare('UPDATE '.table('users').' SET last_login_at=NOW() WHERE id=?')->execute([$id]);
    if(!empty($_POST['remember_device'])){try{issue_remember_token($id);}catch(Throwable $e){log_exception($e,'remember_token_issue',['user_id'=>$id]);flash('info','ورود انجام شد، اما ماندن روی این دستگاه فعال نشد.');}}
    setcookie('sokna_last_user_id',(string)$id,['expires'=>time()+31536000,'path'=>auth_cookie_path(),'secure'=>auth_cookie_secure(),'httponly'=>false,'samesite'=>'Lax']);audit('login','user',$id);redirect(safe_internal_return((string)($_POST['return']??''),'today'));
}
function handle_logout(): never
{
    verify_csrf();if(is_logged_in())audit('logout','user',(int)current_user()['id']);forget_current_remember_token();$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',(bool)$p['secure'],(bool)$p['httponly']);}session_destroy();redirect(url('login'));
}
function handle_reauth_submit(): never
{
    verify_csrf();require_login();$s=db()->prepare('SELECT password_hash FROM '.table('users').' WHERE id=? AND active=1');$s->execute([(int)current_user()['id']]);$hash=$s->fetchColumn();if(!$hash||!password_verify((string)($_POST['password']??''),(string)$hash)){flash('error','رمز عبور درست نیست.');redirect(url('reauth'));}$_SESSION['password_confirmed_at']=time();audit('reauth_success','user',(int)current_user()['id']);$return=safe_internal_return((string)($_SESSION['reauth_return']??''));$pending=(string)($_SESSION['reauth_pending_action']??'');unset($_SESSION['reauth_return'],$_SESSION['reauth_pending_action']);if($pending==='cafe_token_create')handle_cafe_token_create(true);if($pending==='cafe_token_revoke')handle_cafe_token_revoke(true);redirect($return);
}
function handle_reauth_cancel(): never { verify_csrf();$return=safe_internal_return((string)($_SESSION['reauth_return']??''));unset($_SESSION['reauth_return'],$_SESSION['reauth_pending_action']);redirect($return); }

function reservation_back(int $id=0): string { return $id>0?url('reservation',['id'=>$id]):url('reservation_new'); }
function service_add_back(int $id=0): string
{
    return (string)($_POST['return_to']??'')==='services'?url('services',$id>0?['reservation_id'=>$id]:[]).'#service-add':reservation_back($id);
}
function parse_reservation_allocations(?int $excludeReservation=null): array
{
    $roomIds=(array)($_POST['room_id']??[]);$ins=(array)($_POST['check_in']??[]);$outs=(array)($_POST['check_out']??[]);$counts=(array)($_POST['guests_count']??[]);$nightlyRates=(array)($_POST['agreed_nightly_rate']??[]);$legacyAgreed=(array)($_POST['agreed_subtotal']??[]);$pricingModes=(array)($_POST['pricing_mode']??[]);$hasNightlyField=array_key_exists('agreed_nightly_rate',$_POST);
    if(!$roomIds)throw new RuntimeException('حداقل یک اتاق انتخاب کنید.');$rooms=[];foreach(get_rooms(false) as $r)$rooms[(int)$r['id']]=$r;$alloc=[];$roomTotal=0;
    foreach($roomIds as $i=>$raw){
        $rid=(int)$raw;$room=$rooms[$rid]??null;if(!$room||!(int)$room['active'])throw new RuntimeException('یکی از اتاق‌های انتخاب‌شده فعال نیست.');
        $jin=en_digits((string)($ins[$i]??''));$jout=en_digits((string)($outs[$i]??''));$in=Jalali::toIso($jin);$out=Jalali::toIso($jout);if(!$in||!$out)throw new RuntimeException('تاریخ ورود یا خروج معتبر نیست.');
        $guests=max(1,(int)en_digits((string)($counts[$i]??1)));if($guests>(int)$room['max_capacity'])throw new RuntimeException('تعداد مهمان اتاق '.$room['name'].' بیشتر از ظرفیت مجاز است.');if(!room_accepts_stay($room,$in,$out))throw new RuntimeException('اتاق '.$room['name'].' در این بازه داخل چرخه فعالیت نیست.');
        $pricing=calculate_room_stay_pricing($rid,$in,$out,$guests,(int)$room['standard_capacity']);$nights=max(1,(int)$pricing['nights']);$pricingMode=in_array((string)($pricingModes[$i]??'auto'),['auto','manual'],true)?(string)$pricingModes[$i]:'auto';
        $agreedNightly=null;$subtotal=(int)$pricing['subtotal'];
        if($pricingMode==='manual'){
            if($hasNightlyField){$rawNightly=normalize_money_string($nightlyRates[$i]??'');if($rawNightly==='')throw new RuntimeException('قیمت توافقی هر شب اتاق '.$room['name'].' را وارد کنید یا به نرخ سیستم برگردید.');$agreedNightly=parse_money_amount($rawNightly);$subtotal=checked_money_multiply($agreedNightly,$nights);}
            else{$legacyRaw=normalize_money_string($legacyAgreed[$i]??'');if($legacyRaw==='')throw new RuntimeException('مبلغ توافقی اتاق '.$room['name'].' را وارد کنید یا به نرخ سیستم برگردید.');$subtotal=parse_money_amount($legacyRaw);$agreedNightly=(int)floor($subtotal/$nights);}
        }
        if(!room_is_free($rid,$in,$out,$excludeReservation))throw new RuntimeException('اتاق '.$room['name'].' در بخشی از این بازه آزاد نیست.');
        $pricing['pricing_mode']=$pricingMode;$pricing['agreed_nightly_rate']=$agreedNightly;$pricing['agreed_subtotal']=$subtotal;$pricing['system_calculated_subtotal']=(int)$pricing['subtotal'];
        $alloc[]=['room_id'=>$rid,'room_name'=>(string)$room['name'],'check_in'=>$in,'check_out'=>$out,'guests_count'=>$guests,'nights'=>$nights,'pricing_mode'=>$pricingMode,'agreed_nightly_rate'=>$agreedNightly,'calculated_subtotal'=>(int)$pricing['subtotal'],'subtotal'=>$subtotal,'pricing_snapshot'=>json_encode($pricing,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)];$roomTotal=checked_money_add($roomTotal,$subtotal);
    }
    for($i=0;$i<count($alloc);$i++)for($j=$i+1;$j<count($alloc);$j++)if($alloc[$i]['room_id']===$alloc[$j]['room_id']&&$alloc[$i]['check_in']<$alloc[$j]['check_out']&&$alloc[$i]['check_out']>$alloc[$j]['check_in'])throw new RuntimeException('یک اتاق در همین رزرو دو بازه هم‌پوشان دارد.');
    return [$alloc,$roomTotal];
}
function lock_and_verify_allocations(array $allocations,?int $excludeReservation=null): void
{
    $ids=array_values(array_unique(array_map(static fn(array $a)=>(int)$a['room_id'],$allocations)));
    sort($ids,SORT_NUMERIC);
    if($ids){$placeholders=implode(',',array_fill(0,count($ids),'?'));$lock=db()->prepare('SELECT id FROM '.table('rooms').' WHERE id IN ('.$placeholders.') ORDER BY id FOR UPDATE');$lock->execute($ids);if(count($lock->fetchAll())!==count($ids))throw new RuntimeException('یکی از اتاق‌ها دیگر در دسترس نیست.');}
    foreach($allocations as $a)if(!room_is_free((int)$a['room_id'],(string)$a['check_in'],(string)$a['check_out'],$excludeReservation))throw new RuntimeException('اتاق '.$a['room_name'].' در بخشی از این بازه دیگر آزاد نیست.');
}
function resolve_reservation_guest(int $requestedId,string $submittedName,?string $submittedPhone): array
{
    if($requestedId>0){
        $s=db()->prepare('SELECT id,display_name,phone_normalized FROM '.table('guests').' WHERE id=? LIMIT 1');$s->execute([$requestedId]);$guest=$s->fetch();
        if(!$guest)throw new RuntimeException('پرونده مهمان انتخاب‌شده پیدا نشد؛ مهمان را دوباره انتخاب کنید.');
        return ['id'=>(int)$guest['id'],'name'=>(string)$guest['display_name'],'phone'=>normalize_mobile_phone((string)($guest['phone_normalized']??''))];
    }
    $name=trim($submittedName);$phone=normalize_mobile_or_throw($submittedPhone);
    if($name==='')throw new RuntimeException('نام مهمان الزامی است.');
    if($phone){
        $s=db()->prepare('SELECT id,display_name,phone_normalized FROM '.table('guests').' WHERE phone_normalized=? ORDER BY updated_at DESC,id DESC LIMIT 1');$s->execute([$phone]);$guest=$s->fetch();
        if($guest)return ['id'=>(int)$guest['id'],'name'=>(string)$guest['display_name'],'phone'=>normalize_mobile_phone((string)($guest['phone_normalized']??''))];
    }
    db()->prepare('INSERT INTO '.table('guests').' (display_name,phone_normalized,whatsapp_normalized) VALUES (?,?,?)')->execute([$name,$phone,$phone]);
    return ['id'=>(int)db()->lastInsertId(),'name'=>$name,'phone'=>$phone];
}
function handle_reservation_create(): never
{
    verify_csrf();$submittedGuestName=trim((string)($_POST['guest_name']??''));$submittedPhone=(string)($_POST['phone']??'');$requestedGuestId=max(0,(int)($_POST['guest_id']??0));$deposit=parse_money_amount($_POST['deposit_amount']??0);$status=$deposit>0?'confirmed':'hold';$client=trim((string)($_POST['client_token']??''));if(!preg_match('/^[a-f0-9]{32,64}$/',$client))$client=bin2hex(random_bytes(16));$waitlistId=max(0,(int)($_POST['waitlist_id']??0));
    try{[$alloc,$roomTotal]=parse_reservation_allocations(null);$lodging=max(0,$roomTotal);$requestedFinal=array_key_exists('final_lodging_total',$_POST)?parse_money_amount($_POST['final_lodging_total']??$lodging):max(0,checked_money_add($lodging,parse_signed_money_amount($_POST['final_adjustment']??0)));$finalSplit=reservation_final_lodging_split($lodging,$requestedFinal);$adjust=(int)$finalSplit['adjustment'];$discount=(int)$finalSplit['discount'];$alloc=reservation_apply_effective_pricing($alloc,(int)$finalSplit['final']);$notes=trim((string)($_POST['notes']??''));$codeLock=false;db()->beginTransaction();if(!reservation_code_lock_acquire())throw new RuntimeException('ساخت شماره رزرو موقتاً در دسترس نیست؛ چند لحظه دیگر دوباره تلاش کنید.');$codeLock=true;if($waitlistId>0){$waitLock=db()->prepare('SELECT id,status FROM '.table('waitlist').' WHERE id=? FOR UPDATE');$waitLock->execute([$waitlistId]);$waitRow=$waitLock->fetch();if(!$waitRow||(string)$waitRow['status']!=='open')throw new RuntimeException('این موردِ لیست انتظار قبلاً تعیین تکلیف شده است.');}lock_and_verify_allocations($alloc,null);$guest=resolve_reservation_guest($requestedGuestId,$submittedGuestName,$submittedPhone);$guestId=(int)$guest['id'];$guestName=(string)$guest['name'];$phone=$guest['phone'];$code=reservation_code();db()->prepare('INSERT INTO '.table('reservations').' (code,client_token,guest_id,guest_name_snapshot,phone_snapshot,status,lodging_amount,final_adjustment,discount_amount,discount_note,notes,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$code,$client,$guestId,$guestName,$phone,$status,$lodging,$adjust,$discount,$discount>0?'تخفیف محاسبه‌شده از مبلغ نهایی رزرو':null,$notes,current_user()['id'],current_user()['id']]);$id=(int)db()->lastInsertId();$st=db()->prepare('INSERT INTO '.table('reservation_rooms').' (reservation_id,room_id,room_name_snapshot,check_in,check_out,guests_count,calculated_subtotal,subtotal,pricing_snapshot) VALUES (?,?,?,?,?,?,?,?,?)');foreach($alloc as $a)$st->execute([$id,$a['room_id'],$a['room_name'],$a['check_in'],$a['check_out'],$a['guests_count'],$a['calculated_subtotal'],$a['subtotal'],$a['pricing_snapshot']]);if($deposit>0){$method=in_array($_POST['payment_method']??'transfer',['cash','card','transfer','other'],true)?(string)$_POST['payment_method']:'other';db()->prepare("INSERT INTO ".table('payments')." (reservation_id,amount,kind,method,created_by) VALUES (?,?,'payment',?,?)")->execute([$id,$deposit,$method,current_user()['id']]);}if($waitlistId>0)db()->prepare("UPDATE ".table('waitlist')." SET status='converted' WHERE id=? AND status='open'")->execute([$waitlistId]);db()->commit();reservation_code_lock_release();$codeLock=false;$_SESSION['reservation_draft_committed_token']=$client;audit('reservation_create','reservation',$id,['rooms'=>count($alloc),'agreed_lodging_amount'=>$lodging,'final_lodging_amount'=>(int)$finalSplit['final'],'surcharge'=>$adjust,'discount'=>$discount,'status'=>$status,'waitlist_id'=>$waitlistId?:null]);if($waitlistId>0)audit('waitlist_convert','waitlist',$waitlistId,['reservation_id'=>$id]);ReportManager::capturePaceSnapshotSafe();flash('success',$status==='hold'?'رزرو در انتظار ثبت شد.':'رزرو قطعی ثبت شد.');redirect(url('reservation',['id'=>$id]));}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if(!empty($codeLock))reservation_code_lock_release();log_exception($e,'reservation_create');flash('error',$e instanceof RuntimeException?$e->getMessage():'ثبت رزرو انجام نشد.');redirect(url('reservation_new',$waitlistId>0?['waitlist_id'=>$waitlistId]:[]));}
}
function handle_reservation_update(): never
{
    verify_csrf();$id=max(0,(int)($_POST['id']??0));$expected=max(1,(int)($_POST['lock_version']??1));$r=get_reservation($id);if(!$r){flash('error','رزرو پیدا نشد.');redirect(url('today'));}if($r['status']==='cancelled'){flash('error','رزرو لغوشده از این فرم قابل ویرایش نیست.');redirect(url('reservation',['id'=>$id]));}$before=get_allocations($id);$historical=reservation_historical_edit_admin_only($r,$before);$confirmationLocked=reservation_confirmation_locked($r,$before);$historyNote=trim((string)($_POST['history_note']??''));if($historical&&!is_admin()){flash('error','اصلاح رزرو پایان‌یافته فقط برای مدیر مجاز است.');redirect(url('reservation',['id'=>$id]));}if($historical&&$historyNote===''){flash('error','برای اصلاح رزرو پایان‌یافته، درج دلیل الزامی است.');redirect(url('reservation_edit',['id'=>$id]));}$submittedGuestName=trim((string)($_POST['guest_name']??''));$submittedPhone=(string)($_POST['phone']??'');$requestedGuestId=max(0,(int)($_POST['guest_id']??0));$status=(string)$r['status'];
    try{[$alloc,$roomTotal]=parse_reservation_allocations($id);$lodging=max(0,$roomTotal);$currentFinal=max(0,checked_money_subtract(checked_money_add((int)($r['lodging_amount']??0),(int)($r['final_adjustment']??0)),(int)($r['discount_amount']??0)));$requestedFinal=array_key_exists('final_lodging_total',$_POST)?parse_money_amount($_POST['final_lodging_total']??$currentFinal):max(0,checked_money_add($lodging,parse_signed_money_amount($_POST['final_adjustment']??0)));$finalSplit=reservation_final_lodging_split($lodging,$requestedFinal);$adjust=(int)$finalSplit['adjustment'];$discount=(int)$finalSplit['discount'];$alloc=reservation_apply_effective_pricing($alloc,(int)$finalSplit['final']);$notes=trim((string)($_POST['notes']??''));db()->beginTransaction();lock_and_verify_allocations($alloc,$id);$lock=db()->prepare('SELECT lock_version FROM '.table('reservations').' WHERE id=? FOR UPDATE');$lock->execute([$id]);if((int)$lock->fetchColumn()!==$expected)throw new RuntimeException('رزرو هم‌زمان تغییر کرده است؛ صفحه را تازه کنید.');$guest=resolve_reservation_guest($requestedGuestId,$submittedGuestName,$submittedPhone);$guestId=(int)$guest['id'];$guestName=(string)$guest['name'];$phone=$guest['phone'];$discountNote=$discount>0?'تخفیف محاسبه‌شده از مبلغ نهایی رزرو':null;$u=db()->prepare('UPDATE '.table('reservations').' SET guest_id=?,guest_name_snapshot=?,phone_snapshot=?,status=?,lodging_amount=?,final_adjustment=?,discount_amount=?,discount_note=?,notes=?,updated_by=?,lock_version=lock_version+1 WHERE id=? AND lock_version=?');$u->execute([$guestId,$guestName,$phone,$status,$lodging,$adjust,$discount,$discountNote,$notes,current_user()['id'],$id,$expected]);if($u->rowCount()!==1)throw new RuntimeException('رزرو هم‌زمان تغییر کرده است.');db()->prepare('DELETE FROM '.table('reservation_rooms').' WHERE reservation_id=?')->execute([$id]);$st=db()->prepare('INSERT INTO '.table('reservation_rooms').' (reservation_id,room_id,room_name_snapshot,check_in,check_out,guests_count,calculated_subtotal,subtotal,pricing_snapshot) VALUES (?,?,?,?,?,?,?,?,?)');foreach($alloc as $a)$st->execute([$id,$a['room_id'],$a['room_name'],$a['check_in'],$a['check_out'],$a['guests_count'],$a['calculated_subtotal'],$a['subtotal'],$a['pricing_snapshot']]);db()->commit();audit('reservation_update','reservation',$id,['before'=>$before,'after'=>$alloc,'agreed_lodging_amount'=>$lodging,'final_lodging_amount'=>(int)$finalSplit['final'],'surcharge'=>$adjust,'discount'=>$discount,'status'=>$status,'historical'=>$historical,'historical_note'=>$historyNote]);ReportManager::capturePaceSnapshotSafe();flash('success','تغییرات رزرو ذخیره شد.');redirect(url('reservation',['id'=>$id]));}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();log_exception($e,'reservation_update');flash('error',$e instanceof RuntimeException?$e->getMessage():'ویرایش رزرو انجام نشد.');redirect(url('reservation_edit',['id'=>$id]));}
}

function handle_payment_add(): never
{
    verify_csrf();$id=(int)($_POST['reservation_id']??0);$amount=parse_money_amount($_POST['amount']??0);
    $method=in_array($_POST['method']??'transfer',['cash','card','transfer','other'],true)?(string)$_POST['method']:'other';
    $reference=trim((string)($_POST['reference']??''));$note=trim((string)($_POST['note']??''));$token=trim((string)($_POST['client_token']??''));
    $fingerprint=financial_commit_fingerprint(['reservation_id'=>$id,'kind'=>'payment','amount'=>$amount,'method'=>$method,'reference'=>$reference,'note'=>$note]);
    $replay=financial_commit_replay_state($token,$fingerprint);
    if($replay==='invalid'){flash('error','فرم پرداخت معتبر نیست؛ صفحه را تازه کنید و دوباره تلاش کنید.');redirect(reservation_back($id));}
    if($replay==='replay'){flash('info','این پرداخت قبلاً ثبت شده است؛ تراکنش دوباره ایجاد نشد.');redirect(reservation_back($id));}
    if($replay==='conflict'){flash('error','این فرم مالی قبلاً با اطلاعات دیگری استفاده شده است؛ صفحه را تازه کنید.');redirect(reservation_back($id));}
    if($amount<=0){flash('error','مبلغ پرداخت معتبر نیست.');redirect(reservation_back($id));}
    try{
        if(text_length($reference)>160)throw new RuntimeException('شماره پیگیری / مرجع تراکنش حداکثر ۱۶۰ نویسه است.');
        if(text_length($note)>500)throw new RuntimeException('یادداشت تراکنش حداکثر ۵۰۰ نویسه است.');
        db()->beginTransaction();
        $durableReplay=FinancialCommitGuard::claim(db(),$token,$fingerprint,'payment',$id);
        if($durableReplay==='invalid')throw new RuntimeException('فرم مالی نامعتبر است.');
        if($durableReplay==='replay'||$durableReplay==='conflict'){
            db()->rollBack();
            flash($durableReplay==='replay'?'info':'error',
                $durableReplay==='replay'?'این پرداخت قبلاً ثبت شده و دوباره ایجاد نشد.':'این فرم قبلاً با اطلاعات متفاوت استفاده شده است.');
            redirect(reservation_back($id));
        }
        $lock=db()->prepare('SELECT status FROM '.table('reservations').' WHERE id=? FOR UPDATE');$lock->execute([$id]);$status=$lock->fetchColumn();
        if($status===false)throw new RuntimeException('رزرو پیدا نشد.');if($status==='cancelled')throw new RuntimeException('برای رزرو لغوشده پرداخت جدید ثبت نمی‌شود.');
        db()->prepare("INSERT INTO ".table('payments')." (reservation_id,amount,kind,method,reference,note,created_by) VALUES (?,?,'payment',?,?,?,?)")->execute([$id,$amount,$method,$reference!==''?$reference:null,$note!==''?$note:null,current_user()['id']]);
        if($status==='hold')db()->prepare("UPDATE ".table('reservations')." SET status='confirmed',updated_by=?,lock_version=lock_version+1 WHERE id=? AND status='hold'")->execute([current_user()['id'],$id]);
        db()->commit();financial_commit_remember($token,$fingerprint);audit('payment_add','reservation',$id,['amount'=>$amount,'auto_confirmed'=>$status==='hold']);if($status==='hold')ReportManager::capturePaceSnapshotSafe();flash('success',$status==='hold'?'پرداخت ثبت شد و رزرو قطعی شد.':'پرداخت ثبت شد.');
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();log_exception($e,'payment_add',['reservation_id'=>$id]);flash('error',$e instanceof RuntimeException?$e->getMessage():'ثبت پرداخت انجام نشد.');}
    redirect(reservation_back($id));
}
function handle_refund_add(): never
{
    verify_csrf();$id=(int)($_POST['reservation_id']??0);$amount=parse_money_amount($_POST['amount']??0);
    $method=in_array($_POST['method']??'transfer',['cash','card','transfer','other'],true)?(string)$_POST['method']:'other';
    $reference=trim((string)($_POST['reference']??''));$note=trim((string)($_POST['note']??''));$token=trim((string)($_POST['client_token']??''));
    $fingerprint=financial_commit_fingerprint(['reservation_id'=>$id,'kind'=>'refund','amount'=>$amount,'method'=>$method,'reference'=>$reference,'note'=>$note]);
    $replay=financial_commit_replay_state($token,$fingerprint);
    if($replay==='invalid'){flash('error','فرم بازپرداخت معتبر نیست؛ صفحه را تازه کنید و دوباره تلاش کنید.');redirect(reservation_back($id));}
    if($replay==='replay'){flash('info','این بازپرداخت قبلاً ثبت شده است؛ تراکنش دوباره ایجاد نشد.');redirect(reservation_back($id));}
    if($replay==='conflict'){flash('error','این فرم مالی قبلاً با اطلاعات دیگری استفاده شده است؛ صفحه را تازه کنید.');redirect(reservation_back($id));}
    if($amount<=0){flash('error','مبلغ بازپرداخت معتبر نیست.');redirect(reservation_back($id));}
    try{
        if(text_length($reference)>160)throw new RuntimeException('شماره پیگیری / مرجع تراکنش حداکثر ۱۶۰ نویسه است.');
        if(text_length($note)>500)throw new RuntimeException('یادداشت تراکنش حداکثر ۵۰۰ نویسه است.');
        db()->beginTransaction();
        $durableReplay=FinancialCommitGuard::claim(db(),$token,$fingerprint,'refund',$id);
        if($durableReplay==='invalid')throw new RuntimeException('فرم مالی نامعتبر است.');
        if($durableReplay==='replay'||$durableReplay==='conflict'){
            db()->rollBack();
            flash($durableReplay==='replay'?'info':'error',
                $durableReplay==='replay'?'این بازپرداخت قبلاً ثبت شده و دوباره ایجاد نشد.':'این فرم قبلاً با اطلاعات متفاوت استفاده شده است.');
            redirect(reservation_back($id));
        }
        
        $lock=db()->prepare('SELECT lodging_amount,final_adjustment,discount_amount,cancellation_penalty,status FROM '.table('reservations').' WHERE id=? FOR UPDATE');$lock->execute([$id]);$reservation=$lock->fetch();if(!$reservation)throw new RuntimeException('رزرو پیدا نشد.');
        $serviceRows=db()->prepare('SELECT total,status FROM '.table('reservation_services').' WHERE reservation_id=? FOR UPDATE');$serviceRows->execute([$id]);$services=0;foreach($serviceRows->fetchAll() as $row)if((string)$row['status']!=='cancelled')$services+=(int)$row['total'];
        $cafeRows=db()->prepare('SELECT amount,status FROM '.table('cafe_charges').' WHERE reservation_id=? FOR UPDATE');$cafeRows->execute([$id]);$cafe=0;foreach($cafeRows->fetchAll() as $row)if((string)$row['status']==='posted')$cafe+=(int)$row['amount'];
        $payments=db()->prepare('SELECT amount,kind FROM '.table('payments').' WHERE reservation_id=? AND voided_at IS NULL FOR UPDATE');$payments->execute([$id]);$netPaid=0;foreach($payments->fetchAll() as $row)$netPaid+=(($row['kind']??'payment')==='refund'?-1:1)*(int)$row['amount'];
        $ledger=LedgerService::calculate($reservation,$services,$cafe,$netPaid);
        if($amount>$netPaid)throw new RuntimeException('بازپرداخت نمی‌تواند از خالص دریافتی بیشتر باشد.');
        $managerOverride=refund_requires_manager($ledger,$amount);
        if($managerOverride&&!is_admin())throw new RuntimeException('بازپرداخت بیشتر از بستانکاری مهمان فقط برای مدیر مجاز است.');
        if($managerOverride&&$note==='')throw new RuntimeException('برای بازپرداختی که بدهی ایجاد می‌کند یا بدهی را افزایش می‌دهد، درج دلیل الزامی است.');
        $afterBalance=(int)$ledger['balance']+$amount;
        db()->prepare("INSERT INTO ".table('payments')." (reservation_id,amount,kind,method,reference,note,created_by) VALUES (?,?,'refund',?,?,?,?)")->execute([$id,$amount,$method,$reference!==''?$reference:null,$note!==''?$note:null,current_user()['id']]);
        db()->commit();financial_commit_remember($token,$fingerprint);audit('refund_add','reservation',$id,['amount'=>$amount,'balance_before'=>(int)$ledger['balance'],'balance_after'=>$afterBalance,'credit_limit'=>refund_credit_limit($ledger),'manager_override'=>$managerOverride,'reason'=>$managerOverride?$note:null]);flash('success','بازپرداخت ثبت شد.');
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();log_exception($e,'refund_add',['reservation_id'=>$id]);flash('error',$e instanceof RuntimeException?$e->getMessage():'ثبت بازپرداخت انجام نشد.');}
    redirect(reservation_back($id));
}
function handle_payment_void(): never
{
    verify_csrf();$pid=(int)($_POST['payment_id']??0);$rid=(int)($_POST['reservation_id']??0);
    try{
        db()->beginTransaction();$lock=db()->prepare('SELECT id FROM '.table('reservations').' WHERE id=? FOR UPDATE');$lock->execute([$rid]);if(!$lock->fetchColumn())throw new RuntimeException('رزرو پیدا نشد.');
        $payments=db()->prepare('SELECT id,amount,kind FROM '.table('payments').' WHERE reservation_id=? AND voided_at IS NULL FOR UPDATE');$payments->execute([$rid]);$rows=$payments->fetchAll();$target=null;$net=0;foreach($rows as $row){$net+=(($row['kind']??'payment')==='refund'?-1:1)*(int)$row['amount'];if((int)$row['id']===$pid)$target=$row;}
        if(!$target)throw new RuntimeException('تراکنش پیدا نشد یا قبلاً باطل شده است.');$after=$net-((($target['kind']??'payment')==='refund'?-1:1)*(int)$target['amount']);if($after<0)throw new RuntimeException('این پرداخت پس از ثبت بازپرداخت وابسته قابل ابطال نیست؛ ابتدا بازپرداخت مرتبط را تعیین تکلیف کنید.');
        $u=db()->prepare('UPDATE '.table('payments').' SET voided_at=NOW(),voided_by=? WHERE id=? AND reservation_id=? AND voided_at IS NULL');$u->execute([current_user()['id'],$pid,$rid]);if($u->rowCount()!==1)throw new RuntimeException('تراکنش هم‌زمان تغییر کرده است.');db()->commit();
        audit('payment_void','reservation',$rid,['payment_id'=>$pid]);flash('success','تراکنش باطل شد؛ سابقه آن حفظ شده است.');
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();log_exception($e,'payment_void',['reservation_id'=>$rid,'payment_id'=>$pid]);flash('error',$e instanceof RuntimeException?$e->getMessage():'ابطال تراکنش انجام نشد.');}
    redirect(reservation_back($rid));
}
function handle_discount_update(): never
{
    verify_csrf();$id=(int)($_POST['reservation_id']??0);$amount=parse_money_amount($_POST['discount_amount']??0);
    try{
        db()->beginTransaction();$s=db()->prepare('SELECT status,lodging_amount,final_adjustment,cancellation_reason FROM '.table('reservations').' WHERE id=? FOR UPDATE');$s->execute([$id]);$r=$s->fetch();if(!$r)throw new RuntimeException('رزرو پیدا نشد.');if($r['status']==='cancelled')throw new RuntimeException('تخفیف اقامت رزرو لغوشده از این مسیر تغییر نمی‌کند.');$historical=reservation_historical_edit_admin_only($r,get_allocations($id));$discountNote=trim((string)($_POST['discount_note']??''))?:null;if($historical&&!is_admin())throw new RuntimeException('اصلاح تخفیف رزرو پایان‌یافته فقط برای مدیر مجاز است.');if($historical&&$discountNote===null)throw new RuntimeException('برای اصلاح تخفیف رزرو پایان‌یافته، درج دلیل الزامی است.');
        $base=max(0,checked_money_add((int)$r['lodging_amount'],(int)$r['final_adjustment']));if($amount>$base)throw new RuntimeException('تخفیف از مبلغ اقامت بیشتر است.');
        db()->prepare('UPDATE '.table('reservations').' SET discount_amount=?,discount_note=?,updated_by=?,lock_version=lock_version+1 WHERE id=?')->execute([$amount,$discountNote,current_user()['id'],$id]);db()->commit();
        audit('discount_update','reservation',$id,['amount'=>$amount,'historical'=>$historical,'reason'=>$historical?$discountNote:null]);flash('success','تخفیف و مانده حساب به‌روز شد.');
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();log_exception($e,'discount_update',['reservation_id'=>$id]);flash('error',$e instanceof RuntimeException?$e->getMessage():'ذخیره تخفیف انجام نشد.');}
    redirect(reservation_back($id));
}
function apply_reservation_cancellation(int $id,string $reason,int $penalty,?string $note,string $auditAction='reservation_cancel'): void
{
    db()->beginTransaction();try{
        $s=db()->prepare('SELECT status,cancellation_reason FROM '.table('reservations').' WHERE id=? FOR UPDATE');$s->execute([$id]);$reservation=$s->fetch();if(!$reservation)throw new RuntimeException('رزرو پیدا نشد.');if((string)$reservation['status']==='cancelled')throw new RuntimeException('این رزرو قبلاً تعیین تکلیف شده است.');$allocations=get_allocations($id);$historical=reservation_historical_edit_admin_only($reservation,$allocations);if($historical&&!is_admin())throw new RuntimeException('لغو رزرو پایان‌یافته فقط برای مدیر مجاز است.');if($historical&&$note===null)throw new RuntimeException('برای لغو مدیریتی رزرو پایان‌یافته، درج دلیل الزامی است.');
        if($reason==='guest_no_show'&&!reservation_no_show_eligible($reservation,$allocations))throw new RuntimeException('عدم مراجعه فقط برای رزرو قطعی از روز شروع اقامت تا پیش از تاریخ خروج ثبت می‌شود.');
        db()->prepare("UPDATE ".table('reservations')." SET status='cancelled',cancellation_reason=?,cancellation_note=?,cancellation_penalty=?,cancelled_by=?,cancelled_at=NOW(),updated_by=?,lock_version=lock_version+1 WHERE id=? AND status<>'cancelled'")->execute([$reason,$note,$penalty,current_user()['id'],current_user()['id'],$id]);
        db()->commit();audit($auditAction,'reservation',$id,['reason'=>$reason,'penalty'=>$penalty]);ReportManager::capturePaceSnapshotSafe();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}

function handle_reservation_no_show(): never
{
    verify_csrf();$id=(int)($_POST['reservation_id']??0);$penalty=parse_money_amount($_POST['penalty']??0);$note=trim((string)($_POST['note']??''))?:null;
    try{apply_reservation_cancellation($id,'guest_no_show',$penalty,$note,'reservation_no_show');flash('success','عدم مراجعه ثبت شد. اتاق از موجودی رزرو آزاد شد و سابقه مالی حذف نشد.');$activeServices=reservation_active_service_count($id);if($activeServices>0)flash('info',fa_digits($activeServices).' خدمت فعال در پرونده باقی مانده است؛ لغو خودکار انجام نشد و باید دستی بررسی شود.');}catch(Throwable $e){log_exception($e,'reservation_no_show',['reservation_id'=>$id]);flash('error',$e instanceof RuntimeException?$e->getMessage():'ثبت عدم مراجعه انجام نشد.');}
    redirect(reservation_back($id));
}

function handle_reservation_cancel(): never
{
    verify_csrf();$id=(int)($_POST['reservation_id']??0);$reason=(string)($_POST['reason']??'other');if(!in_array($reason,['guest_cancelled','property_cancelled','entry_error','other'],true))$reason='other';$penalty=parse_money_amount($_POST['penalty']??0);$note=trim((string)($_POST['note']??''))?:null;
    try{apply_reservation_cancellation($id,$reason,$penalty,$note);flash('success','رزرو لغو شد. پرداخت‌های قبلی بدون تغییر در سابقه باقی ماندند.');$activeServices=reservation_active_service_count($id);if($activeServices>0)flash('info',fa_digits($activeServices).' خدمت فعال در پرونده باقی مانده است؛ لغو خودکار انجام نشد و باید دستی بررسی شود.');}catch(Throwable $e){log_exception($e,'reservation_cancel',['reservation_id'=>$id]);flash('error',$e instanceof RuntimeException?$e->getMessage():'لغو رزرو انجام نشد.');}
    redirect(reservation_back($id));
}

function handle_guest_update(): never
{
    verify_csrf();$id=(int)($_POST['guest_id']??0);$reservationId=max(0,(int)($_POST['reservation_id']??0));$name=trim((string)($_POST['display_name']??''));
    $return=safe_internal_return((string)($_POST['return_to']??''),'guest');
    if($return===url('guest'))$return=url('guest',['id'=>$id]);
    if($name===''){flash('error','نام مهمان الزامی است.');redirect($return);}
    try{
        $phone=normalize_mobile_or_throw((string)($_POST['phone']??''));
        db()->beginTransaction();
        db()->prepare('UPDATE '.table('guests').' SET display_name=?,phone_normalized=?,whatsapp_normalized=?,city=?,tags=?,notes=? WHERE id=?')->execute([$name,$phone,$phone,trim((string)($_POST['city']??''))?:null,trim((string)($_POST['tags']??''))?:null,trim((string)($_POST['notes']??''))?:null,$id]);
        // When edited from a reservation, update only that reservation's snapshot.
        // Historical/other reservations retain the contact data that belonged to them.
        if($reservationId>0){db()->prepare('UPDATE '.table('reservations').' SET guest_name_snapshot=?,phone_snapshot=?,updated_by=?,lock_version=lock_version+1 WHERE id=? AND guest_id=?')->execute([$name,$phone,current_user()['id'],$reservationId,$id]);}
        db()->commit();audit('guest_update','guest',$id,['reservation_id'=>$reservationId?:null]);flash('success','اطلاعات مهمان ذخیره شد.');
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();flash('error',$e instanceof RuntimeException?$e->getMessage():'ذخیره اطلاعات مهمان انجام نشد.');}
    redirect($return);
}
function handle_guest_merge(): never
{
    verify_csrf();$target=(int)($_POST['target_id']??0);$source=(int)($_POST['source_id']??0);
    if($target<=0||$source<=0||$target===$source){flash('error','دو پرونده معتبر برای ادغام انتخاب کنید.');redirect(url('guests'));}
    db()->beginTransaction();
    try{
        $lock=db()->prepare('SELECT * FROM '.table('guests').' WHERE id IN (?,?) FOR UPDATE');$lock->execute([$target,$source]);$rows=[];foreach($lock->fetchAll() as $row)$rows[(int)$row['id']]=$row;
        if(!isset($rows[$target],$rows[$source]))throw new RuntimeException('یکی از پرونده‌های مهمان پیدا نشد.');$t=$rows[$target];$src=$rows[$source];
        $targetPhone=normalize_phone((string)($t['phone_normalized']??''));$sourcePhone=normalize_phone((string)($src['phone_normalized']??''));
        if(!$targetPhone||!$sourcePhone||$targetPhone!==$sourcePhone)throw new RuntimeException('ادغام خودکار فقط برای پرونده‌هایی با شماره موبایل یکسان مجاز است.');
        $mergeTags=function(?string $a,?string $b): ?string {$parts=[];foreach([$a,$b] as $raw){foreach(preg_split('/[,،؛;]+/u',trim((string)$raw))?:[] as $part){$part=trim((string)$part);if($part!==''&&!in_array($part,$parts,true))$parts[]=$part;}}return $parts?implode('، ',$parts):null;};
        $mergeNotes=function(?string $a,?string $b): ?string {$a=trim((string)$a);$b=trim((string)$b);if($a==='')return $b!==''?$b:null;if($b===''||$a===$b)return $a;return $a."\n\n".$b;};
        $city=trim((string)($t['city']??''))!==''?$t['city']:($src['city']??null);$whatsapp=normalize_phone((string)($t['whatsapp_normalized']??''))?:normalize_phone((string)($src['whatsapp_normalized']??''))?:$targetPhone;$tags=$mergeTags($t['tags']??null,$src['tags']??null);$notes=$mergeNotes($t['notes']??null,$src['notes']??null);
        db()->prepare('UPDATE '.table('guests').' SET phone_normalized=?,whatsapp_normalized=?,city=?,tags=?,notes=? WHERE id=?')->execute([$targetPhone,$whatsapp,$city,$tags,$notes,$target]);
        db()->prepare('UPDATE '.table('reservations').' SET guest_id=? WHERE guest_id=?')->execute([$target,$source]);
        db()->prepare('DELETE FROM '.table('guests').' WHERE id=?')->execute([$source]);
        db()->commit();audit('guest_merge','guest',$target,['source'=>$source,'preserved_fields'=>['city'=>(bool)$city,'tags'=>(bool)$tags,'notes'=>(bool)$notes]]);flash('success','پرونده‌ها ادغام شدند و اطلاعات تکمیلی حفظ شد.');redirect(url('guest',['id'=>$target]));
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();log_exception($e,'guest_merge',['target'=>$target,'source'=>$source]);flash('error',$e instanceof RuntimeException?$e->getMessage():'ادغام پرونده انجام نشد.');redirect(url('guest',['id'=>$target]));}
}

function slugify_room(string $name): string { $base=strtolower(trim(preg_replace('/[^A-Za-z0-9]+/','-',en_digits($name))??'','-'));return $base!==''?$base:'room-'.substr(bin2hex(random_bytes(4)),0,6); }
function unique_room_slug(string $name): string
{
    $base=slugify_room($name);$slug=$base;$n=2;$st=db()->prepare('SELECT 1 FROM '.table('rooms').' WHERE slug=? LIMIT 1');
    while(true){$st->execute([$slug]);if(!$st->fetchColumn())return $slug;$suffix='-'.$n++;$slug=substr($base,0,max(1,120-strlen($suffix))).$suffix;if($n>9999)$slug='room-'.substr(bin2hex(random_bytes(8)),0,12);}
}
function optional_jalali_iso(string $value): ?string { $value=trim(en_digits($value));if($value==='')return null;return Jalali::toIso($value); }
function media_oriented_gd_image(string $file,string $mime): array
{
    if(!media_processing_available())return [null,0,0];
    $raw=@file_get_contents($file);if($raw===false)return [null,0,0];$image=@imagecreatefromstring($raw);if(!$image)return [null,0,0];
    if($mime==='image/jpeg'&&function_exists('exif_read_data')){
        $exif=@exif_read_data($file,'IFD0');$orientation=(int)($exif['Orientation']??1);
        $rotate=static function($img,float $angle){$next=@imagerotate($img,$angle,0);if($next){imagedestroy($img);return $next;}return $img;};
        if($orientation===2&&function_exists('imageflip'))imageflip($image,IMG_FLIP_HORIZONTAL);
        elseif($orientation===3)$image=$rotate($image,180);
        elseif($orientation===4&&function_exists('imageflip'))imageflip($image,IMG_FLIP_VERTICAL);
        elseif($orientation===5){if(function_exists('imageflip'))imageflip($image,IMG_FLIP_HORIZONTAL);$image=$rotate($image,-90);}
        elseif($orientation===6)$image=$rotate($image,-90);
        elseif($orientation===7){if(function_exists('imageflip'))imageflip($image,IMG_FLIP_HORIZONTAL);$image=$rotate($image,90);}
        elseif($orientation===8)$image=$rotate($image,90);
    }
    return [$image,(int)imagesx($image),(int)imagesy($image)];
}
function create_media_web_variants(string $absolute,string $relative,string $mime,array &$createdPaths): array
{
    [$source,$sourceWidth,$sourceHeight]=media_oriented_gd_image($absolute,$mime);if(!$source||$sourceWidth<1||$sourceHeight<1)return [0,0];
    $targets=$sourceWidth>1280?[640,1280]:($sourceWidth>640?[640,1280]:[1280]);
    foreach($targets as $target){
        $width=min($target,$sourceWidth);$height=max(1,(int)round($sourceHeight*($width/$sourceWidth)));$canvas=imagecreatetruecolor($width,$height);if(!$canvas)continue;
        imagealphablending($canvas,false);imagesavealpha($canvas,true);$transparent=imagecolorallocatealpha($canvas,0,0,0,127);imagefill($canvas,0,0,$transparent);
        if(!imagecopyresampled($canvas,$source,0,0,0,0,$width,$height,$sourceWidth,$sourceHeight)){imagedestroy($canvas);continue;}
        $variant=media_variant_path($relative,$target);$variantAbs=$variant!==''?dirname(__DIR__).'/'.$variant:'';
        if($variantAbs!==''&&@imagewebp($canvas,$variantAbs,82))$createdPaths[]=$variant;imagedestroy($canvas);
    }
    imagedestroy($source);return [$sourceWidth,$sourceHeight];
}
function store_media_asset(array $file,array &$createdPaths): ?int
{
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
    if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK)throw new RuntimeException('آپلود تصویر کامل نشد.');
    if((int)$file['size']>8*1024*1024)throw new RuntimeException('حجم هر تصویر باید حداکثر ۸ مگابایت باشد.');
    $info=@getimagesize((string)$file['tmp_name']);if(!$info||!in_array($info['mime'],['image/jpeg','image/png','image/webp'],true))throw new RuntimeException('فرمت تصویر باید JPEG، PNG یا WebP باشد.');
    $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$info['mime']];$dir=dirname(__DIR__).'/uploads/rooms';@mkdir($dir,0775,true);
    $name='media-'.date('YmdHis').'-'.substr(bin2hex(random_bytes(6)),0,12).'.'.$ext;$dst=$dir.'/'.$name;
    if(!move_uploaded_file((string)$file['tmp_name'],$dst)&&!rename((string)$file['tmp_name'],$dst))throw new RuntimeException('ذخیره تصویر انجام نشد.');
    $path='uploads/rooms/'.$name;$createdPaths[]=$path;$original=trim(basename((string)($file['name']??'')));if($original==='')$original=null;
    $processedWidth=0;$processedHeight=0;
    try{[$processedWidth,$processedHeight]=create_media_web_variants($dst,$path,(string)$info['mime'],$createdPaths);}catch(Throwable $e){log_exception($e,'media_variant_generation',['path'=>$path]);}
    $width=$processedWidth>0?$processedWidth:(int)($info[0]??0);$height=$processedHeight>0?$processedHeight:(int)($info[1]??0);
    db()->prepare('INSERT INTO '.table('media_assets').' (path,original_name,mime_type,file_size,width,height) VALUES (?,?,?,?,?,?)')->execute([$path,$original,$info['mime'],(int)($file['size']??0),$width,$height]);
    return (int)db()->lastInsertId();
}
function room_profile_input(): array
{
    $standard=(int)en_digits((string)($_POST['standard_capacity']??2));$max=(int)en_digits((string)($_POST['max_capacity']??$standard));
    $double=(int)en_digits((string)($_POST['double_beds']??0));$single=(int)en_digits((string)($_POST['single_beds']??0));
    if($standard<1||$standard>99||$max<1||$max>99)throw new RuntimeException('ظرفیت اتاق باید بین ۱ تا ۹۹ نفر باشد.');
    if($max<$standard)throw new RuntimeException('حداکثر ظرفیت نمی‌تواند کمتر از ظرفیت پایه باشد.');
    if($double<0||$double>20||$single<0||$single>20)throw new RuntimeException('تعداد تخت دبل و سینگل باید بین صفر تا ۲۰ باشد.');
    $profileEnabled=!empty($_POST['public_profile_enabled'])?1:0;$bedCapacity=$double*2+$single;if($profileEnabled&&($double+$single)<1)throw new RuntimeException('برای فعال‌کردن پروفایل عمومی، حداقل یک تخت دبل یا سینگل ثبت کنید.');
    if($profileEnabled&&$standard>$bedCapacity)throw new RuntimeException('برای پروفایل عمومی، ظرفیت پایه نمی‌تواند از ظرفیت تخت‌های ثبت‌شده بیشتر باشد؛ نفرات اضافه با سرویس خواب سنتی محاسبه می‌شوند.');
    $location=trim((string)($_POST['public_location_label']??''));if(text_length($location)>120)throw new RuntimeException('توضیح موقعیت اتاق حداکثر ۱۲۰ نویسه است.');
    return ['standard'=>$standard,'max'=>$max,'double'=>$double,'single'=>$single,'private'=>!empty($_POST['private_bathroom'])?1:0,'profile_enabled'=>$profileEnabled,'location'=>$location!==''?$location:null,'location_visible'=>($location!==''&&!empty($_POST['public_location_visible']))?1:0];
}
function room_selected_amenities(): array
{
    $ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['amenity_ids']??[])),static fn(int $id)=>$id>0)));if(!$ids)return [];
    $marks=implode(',',array_fill(0,count($ids),'?'));$st=db()->prepare('SELECT id FROM '.table('amenities')." WHERE scope='room' AND active=1 AND id IN ($marks)");$st->execute($ids);return array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));
}
function sync_room_amenities(int $roomId,array $ids): void
{
    db()->prepare('DELETE FROM '.table('room_amenities').' WHERE room_id=?')->execute([$roomId]);if(!$ids)return;
    $st=db()->prepare('INSERT INTO '.table('room_amenities').' (room_id,amenity_id) VALUES (?,?)');foreach($ids as $id)$st->execute([$roomId,(int)$id]);
}
function validate_room_lifecycle_change(int $id,array $current,int $active,?string $from,?string $retired): void
{
    if(!$active&&(int)$current['active']===1){$future=db()->prepare("SELECT 1 FROM ".table('reservation_rooms')." rr JOIN ".table('reservations')." r ON r.id=rr.reservation_id WHERE rr.room_id=? AND rr.check_out>? AND r.status<>'cancelled' LIMIT 1");$future->execute([$id,sokna_today()]);if($future->fetchColumn())throw new RuntimeException('این اتاق رزرو جاری یا آینده دارد؛ ابتدا رزروهای مرتبط را تعیین تکلیف کنید.');}
    if($from){$st=db()->prepare("SELECT 1 FROM ".table('reservation_rooms')." rr JOIN ".table('reservations')." r ON r.id=rr.reservation_id WHERE rr.room_id=? AND rr.check_in<? AND r.status<>'cancelled' LIMIT 1");$st->execute([$id,$from]);if($st->fetchColumn())throw new RuntimeException('شروع فعالیت انتخاب‌شده با سابقه رزرو این اتاق سازگار نیست.');}
    if($retired){$st=db()->prepare("SELECT 1 FROM ".table('reservation_rooms')." rr JOIN ".table('reservations')." r ON r.id=rr.reservation_id WHERE rr.room_id=? AND rr.check_out>? AND r.status<>'cancelled' LIMIT 1");$st->execute([$id,$retired]);if($st->fetchColumn())throw new RuntimeException('پس از تاریخ خروج از چرخه، رزرو فعال برای این اتاق وجود دارد.');}
    if($from){$st=db()->prepare('SELECT 1 FROM '.table('room_blocks').' WHERE room_id=? AND start_date<? LIMIT 1');$st->execute([$id,$from]);if($st->fetchColumn())throw new RuntimeException('شروع فعالیت انتخاب‌شده با سابقه خارج از سرویس این اتاق سازگار نیست.');$st=db()->prepare('SELECT 1 FROM '.table('room_rate_overrides').' WHERE room_id=? AND start_date<? LIMIT 1');$st->execute([$id,$from]);if($st->fetchColumn())throw new RuntimeException('شروع فعالیت انتخاب‌شده با سابقه قیمت این اتاق سازگار نیست.');}
    if($retired){$st=db()->prepare('SELECT 1 FROM '.table('room_blocks').' WHERE room_id=? AND end_date>? LIMIT 1');$st->execute([$id,$retired]);if($st->fetchColumn())throw new RuntimeException('پس از تاریخ خروج از چرخه، بازه خارج از سرویس برای این اتاق ثبت شده است.');$st=db()->prepare('SELECT 1 FROM '.table('room_rate_overrides').' WHERE room_id=? AND end_date>? LIMIT 1');$st->execute([$id,$retired]);if($st->fetchColumn())throw new RuntimeException('پس از تاریخ خروج از چرخه، قیمت بازه‌ای برای این اتاق ثبت شده است.');}
}
function apply_media_gallery_changes(string $relationTable,string $ownerColumn,int $ownerId,string $ownerLabel): void
{
    $allowed=[
        'room_images'=>['owner'=>'room_id','label'=>'اتاق'],
        'service_images'=>['owner'=>'service_id','label'=>'خدمت / گشت'],
    ];
    if(!isset($allowed[$relationTable])||$allowed[$relationTable]['owner']!==$ownerColumn)throw new RuntimeException('نوع گالری معتبر نیست.');
    $ownerLabel=trim($ownerLabel)!==''?$ownerLabel:(string)$allowed[$relationTable]['label'];
    $table=table($relationTable);$ownerSql='`'.$ownerColumn.'`';
    $imagesSt=db()->prepare('SELECT rel.*,m.path FROM '.$table.' rel JOIN '.table('media_assets').' m ON m.id=rel.media_id WHERE rel.'.$ownerSql.'=? FOR UPDATE');$imagesSt->execute([$ownerId]);$images=$imagesSt->fetchAll();$byId=[];$currentMedia=[];foreach($images as $img){$byId[(int)$img['id']]=$img;$currentMedia[(int)$img['media_id']]=(int)$img['id'];}
    $deleteIds=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['delete_image_ids']??[])),static fn(int $id)=>$id>0&&isset($byId[$id]))));
    $deletedMedia=[];foreach($deleteIds as $id)$deletedMedia[(int)$byId[$id]['media_id']]=true;
    $libraryIds=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['library_media_ids']??[])),static fn(int $id)=>$id>0)));
    $validLibrary=[];if($libraryIds){$marks=implode(',',array_fill(0,count($libraryIds),'?'));$st=db()->prepare('SELECT id FROM '.table('media_assets')." WHERE id IN ($marks)");$st->execute($libraryIds);$validLibrary=array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));}
    $keptMedia=[];foreach($currentMedia as $mid=>$imageId)if(!isset($deletedMedia[$mid]))$keptMedia[$mid]=true;
    $newLibrary=array_values(array_filter($validLibrary,static fn(int $mid)=>!isset($keptMedia[$mid])));
    $keepCount=count($images)-count($deleteIds);if($keepCount+count($newLibrary)>6)throw new RuntimeException('برای هر '.$ownerLabel.' حداکثر ۶ تصویر نگه دارید.');
    if($deleteIds){$marks=implode(',',array_fill(0,count($deleteIds),'?'));db()->prepare('DELETE FROM '.$table." WHERE $ownerSql=? AND id IN ($marks)")->execute(array_merge([$ownerId],$deleteIds));}
    $orderMap=(array)($_POST['image_order']??[]);foreach($orderMap as $rawId=>$rawOrder){$iid=(int)$rawId;if($iid<=0||!isset($byId[$iid])||in_array($iid,$deleteIds,true))continue;$ord=max(0,min(99,(int)en_digits((string)$rawOrder)));db()->prepare('UPDATE '.$table.' SET sort_order=? WHERE id=? AND '.$ownerSql.'=?')->execute([$ord,$iid,$ownerId]);}
    $stMax=db()->prepare('SELECT COALESCE(MAX(sort_order),0) FROM '.$table.' WHERE '.$ownerSql.'=?');$stMax->execute([$ownerId]);$maxOrder=(int)($stMax->fetchColumn()?:0);
    foreach($newLibrary as $mediaId){$maxOrder+=10;db()->prepare('INSERT IGNORE INTO '.$table.' ('.$ownerSql.',media_id,is_cover,sort_order) VALUES (?,?,0,?)')->execute([$ownerId,$mediaId,$maxOrder]);}
    $coverMediaId=(int)($_POST['cover_media_id']??0);if($coverMediaId>0){$ok=db()->prepare('SELECT id FROM '.$table.' WHERE media_id=? AND '.$ownerSql.'=? LIMIT 1');$ok->execute([$coverMediaId,$ownerId]);if($ok->fetchColumn()){db()->prepare('UPDATE '.$table.' SET is_cover=0 WHERE '.$ownerSql.'=?')->execute([$ownerId]);db()->prepare('UPDATE '.$table.' SET is_cover=1 WHERE media_id=? AND '.$ownerSql.'=?')->execute([$coverMediaId,$ownerId]);}}
    $hasCover=db()->prepare('SELECT id FROM '.$table.' WHERE '.$ownerSql.'=? AND is_cover=1 LIMIT 1');$hasCover->execute([$ownerId]);if(!$hasCover->fetchColumn()){$first=db()->prepare('SELECT id FROM '.$table.' WHERE '.$ownerSql.'=? ORDER BY sort_order,id LIMIT 1');$first->execute([$ownerId]);$firstId=(int)($first->fetchColumn()?:0);if($firstId>0)db()->prepare('UPDATE '.$table.' SET is_cover=1 WHERE id=?')->execute([$firstId]);}
}
function apply_room_gallery_changes(int $roomId): void { apply_media_gallery_changes('room_images','room_id',$roomId,'اتاق'); }
function apply_service_gallery_changes(int $serviceId): void { apply_media_gallery_changes('service_images','service_id',$serviceId,'خدمت / گشت'); }
function unlink_created_media_paths(array $paths): void { foreach(array_values(array_unique($paths)) as $path)if(str_starts_with((string)$path,'uploads/rooms/'))@unlink(dirname(__DIR__).'/'.$path); }
function handle_room_create(): never
{
    verify_csrf();$back=url('rooms',['view'=>'rooms','mode'=>'create']);$id=0;$name='';$slug='';
    try{
        $name=trim((string)($_POST['name']??''));
        if($name==='')throw new RuntimeException('نام اتاق الزامی است.');
        if(text_length($name)>100)throw new RuntimeException('نام اتاق حداکثر ۱۰۰ نویسه است.');
        $profile=room_profile_input();
        $from=optional_jalali_iso((string)($_POST['available_from']??''));$retired=optional_jalali_iso((string)($_POST['retired_from']??''));
        if(trim((string)($_POST['available_from']??''))!==''&&!$from)throw new RuntimeException('تاریخ شروع فعالیت معتبر نیست.');
        if(trim((string)($_POST['retired_from']??''))!==''&&!$retired)throw new RuntimeException('تاریخ خروج از چرخه معتبر نیست.');
        if($from&&$retired&&$from>=$retired)throw new RuntimeException('تاریخ خروج از چرخه باید بعد از شروع فعالیت باشد.');
        $slug=unique_room_slug($name);
        db()->beginTransaction();
        db()->prepare('INSERT INTO '.table('rooms').' (name,slug,standard_capacity,max_capacity,base_rate,extra_person_rate,double_beds,single_beds,private_bathroom,public_profile_enabled,public_location_label,public_location_visible,available_from,retired_from,active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?)')->execute([$name,$slug,$profile['standard'],$profile['max'],parse_money_amount($_POST['base_rate']??0),parse_money_amount($_POST['extra_person_rate']??0),$profile['double'],$profile['single'],$profile['private'],$profile['profile_enabled'],$profile['location'],$profile['location_visible'],$from,$retired,(int)en_digits((string)($_POST['sort_order']??0))]);
        $id=(int)db()->lastInsertId();sync_room_amenities($id,room_selected_amenities());db()->commit();
        audit('room_create','room',$id,['available_from'=>$from,'retired_from'=>$retired,'public_profile'=>$profile['profile_enabled']]);ReportManager::capturePaceSnapshotSafe();flash('success','اتاق ساخته شد. تصاویر را می‌توانید جداگانه مدیریت کنید.');
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        log_exception($e,'room_create',['room_id'=>$id,'name'=>$name,'slug'=>$slug]);
        flash('error',$e instanceof RuntimeException?$e->getMessage():'ساخت اتاق انجام نشد. شناسه خطا در «وضعیت سامانه» ثبت شده است.');
    }
    redirect($id>0?url('rooms',['view'=>'rooms','room'=>$id]):$back);
}
function handle_room_update(): never
{
    verify_csrf();$id=(int)($_POST['room_id']??0);$back=url('rooms',['view'=>'rooms','room'=>$id]);$name='';
    try{
        $name=trim((string)($_POST['name']??''));
        if($name==='')throw new RuntimeException('نام اتاق الزامی است.');
        if(text_length($name)>100)throw new RuntimeException('نام اتاق حداکثر ۱۰۰ نویسه است.');
        $profile=room_profile_input();
        $active=!empty($_POST['active'])?1:0;$from=optional_jalali_iso((string)($_POST['available_from']??''));$retired=optional_jalali_iso((string)($_POST['retired_from']??''));
        if(trim((string)($_POST['available_from']??''))!==''&&!$from)throw new RuntimeException('تاریخ شروع فعالیت معتبر نیست.');
        if(trim((string)($_POST['retired_from']??''))!==''&&!$retired)throw new RuntimeException('تاریخ خروج از چرخه معتبر نیست.');
        if($from&&$retired&&$from>=$retired)throw new RuntimeException('تاریخ خروج از چرخه باید بعد از شروع فعالیت باشد.');
        db()->beginTransaction();$currentSt=db()->prepare('SELECT * FROM '.table('rooms').' WHERE id=? FOR UPDATE');$currentSt->execute([$id]);$current=$currentSt->fetch();if(!$current)throw new RuntimeException('اتاق پیدا نشد.');validate_room_lifecycle_change($id,$current,$active,$from,$retired);
        db()->prepare('UPDATE '.table('rooms').' SET name=?,standard_capacity=?,max_capacity=?,base_rate=?,extra_person_rate=?,double_beds=?,single_beds=?,private_bathroom=?,public_profile_enabled=?,public_location_label=?,public_location_visible=?,available_from=?,retired_from=?,active=?,sort_order=? WHERE id=?')->execute([$name,$profile['standard'],$profile['max'],parse_money_amount($_POST['base_rate']??0),parse_money_amount($_POST['extra_person_rate']??0),$profile['double'],$profile['single'],$profile['private'],$profile['profile_enabled'],$profile['location'],$profile['location_visible'],$from,$retired,$active,(int)en_digits((string)($_POST['sort_order']??0)),$id]);
        sync_room_amenities($id,room_selected_amenities());db()->commit();
        audit('room_update','room',$id,['available_from'=>$from,'retired_from'=>$retired,'public_profile'=>$profile['profile_enabled']]);ReportManager::capturePaceSnapshotSafe();flash('success','مشخصات و امکانات اتاق ذخیره شد.');
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();log_exception($e,'room_update',['room_id'=>$id,'name'=>$name]);flash('error',$e instanceof RuntimeException?$e->getMessage():'ذخیره اتاق انجام نشد. شناسه خطا در «وضعیت سامانه» ثبت شده است.');
    }
    redirect($back);
}
function handle_room_reactivate(): never
{
    verify_csrf();$id=(int)($_POST['room_id']??0);$back=url('rooms',['view'=>'rooms']);
    try{
        db()->beginTransaction();$st=db()->prepare('SELECT * FROM '.table('rooms').' WHERE id=? FOR UPDATE');$st->execute([$id]);$room=$st->fetch();if(!$room)throw new RuntimeException('اتاق پیدا نشد.');
        db()->prepare('UPDATE '.table('rooms').' SET active=1,retired_from=NULL WHERE id=?')->execute([$id]);db()->commit();
        audit('room_reactivate','room',$id,['previous_active'=>(int)$room['active'],'previous_retired_from'=>$room['retired_from']??null]);ReportManager::capturePaceSnapshotSafe();flash('success','اتاق دوباره وارد چرخه شد.');$back=url('rooms',['view'=>'rooms','room'=>$id]);
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();log_exception($e,'room_reactivate',['room_id'=>$id]);flash('error',$e instanceof RuntimeException?$e->getMessage():'فعال‌سازی مجدد اتاق انجام نشد.');}
    redirect($back);
}
function handle_room_delete(): never
{
    verify_csrf();$id=(int)($_POST['room_id']??0);
    try{
        db()->beginTransaction();$st=db()->prepare('SELECT * FROM '.table('rooms').' WHERE id=? FOR UPDATE');$st->execute([$id]);$room=$st->fetch();if(!$room)throw new RuntimeException('اتاق پیدا نشد.');
        if(room_is_archived_now($room)){db()->commit();flash('info','این اتاق قبلاً از چرخه خارج شده است.');redirect(url('rooms',['view'=>'rooms','archive'=>1]));}
        $future=db()->prepare("SELECT 1 FROM ".table('reservation_rooms')." rr JOIN ".table('reservations')." r ON r.id=rr.reservation_id WHERE rr.room_id=? AND rr.check_out>? AND r.status<>'cancelled' LIMIT 1");$future->execute([$id,sokna_today()]);if($future->fetchColumn())throw new RuntimeException('این اتاق رزرو جاری یا آینده دارد؛ ابتدا رزروهای مرتبط را تعیین تکلیف کنید.');
        db()->prepare('UPDATE '.table('rooms').' SET active=0,retired_from=? WHERE id=?')->execute([sokna_today(),$id]);db()->commit();
        audit('room_archive','room',$id,['name'=>$room['name'],'retired_from'=>sokna_today(),'restorable'=>true]);ReportManager::capturePaceSnapshotSafe();flash('success','اتاق از چرخه خارج شد. سوابق رزرو و مالی حفظ شده و هر زمان لازم باشد قابل بازگشت است.');
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();log_exception($e,'room_archive',['room_id'=>$id]);flash('error',$e instanceof RuntimeException?$e->getMessage():'خارج‌کردن اتاق از چرخه انجام نشد.');}
    redirect(url('rooms',['view'=>'rooms']));
}

function handle_room_gallery_save(): never
{
    verify_csrf();$id=(int)($_POST['room_id']??0);$back=url('rooms',['view'=>'rooms','room'=>$id]).'#room-gallery';
    if($id<=0){flash('error','اتاق برای مدیریت تصاویر پیدا نشد.');redirect(url('rooms',['view'=>'rooms']));}
    try{
        db()->beginTransaction();$roomSt=db()->prepare('SELECT id FROM '.table('rooms').' WHERE id=? FOR UPDATE');$roomSt->execute([$id]);if(!$roomSt->fetchColumn())throw new RuntimeException('اتاق پیدا نشد.');
        apply_room_gallery_changes($id);db()->commit();audit('room_gallery_update','room',$id);flash('success','تصاویر اتاق ذخیره شد.');
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();log_exception($e,'room_gallery_update',['room_id'=>$id]);flash('error',$e instanceof RuntimeException?$e->getMessage():'ذخیره تصاویر انجام نشد.');}
    redirect($back);
}
function handle_service_gallery_save(): never
{
    verify_csrf();$id=(int)($_POST['service_id']??0);$back=url('services').'#service-gallery-'.$id;$created=[];
    if($id<=0){flash('error','خدمت یا گشت برای مدیریت تصاویر پیدا نشد.');redirect(url('services'));}
    try{
        db()->beginTransaction();$serviceSt=db()->prepare('SELECT id,name FROM '.table('extra_services').' WHERE id=? FOR UPDATE');$serviceSt->execute([$id]);$service=$serviceSt->fetch();if(!$service)throw new RuntimeException('خدمت یا گشت پیدا نشد.');
        $newIds=[];$files=$_FILES['gallery_files']??null;
        if(is_array($files)&&isset($files['name'])&&is_array($files['name'])){
            $count=count($files['name']);
            for($i=0;$i<$count;$i++){
                $error=(int)($files['error'][$i]??UPLOAD_ERR_NO_FILE);if($error===UPLOAD_ERR_NO_FILE)continue;
                $file=['name'=>$files['name'][$i]??'','type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i]??'','error'=>$error,'size'=>$files['size'][$i]??0];
                $mediaId=(int)(store_media_asset($file,$created)??0);if($mediaId>0)$newIds[]=$mediaId;
            }
        }
        if($newIds)$_POST['library_media_ids']=array_values(array_unique(array_merge((array)($_POST['library_media_ids']??[]),$newIds)));
        apply_service_gallery_changes($id);db()->commit();audit('service_gallery_update','extra_service',$id,['uploaded_count'=>count($newIds)]);flash('success','تصاویر خدمت / گشت ذخیره شد.');
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();unlink_created_media_paths($created);log_exception($e,'service_gallery_update',['service_id'=>$id]);flash('error',$e instanceof RuntimeException?$e->getMessage():'ذخیره تصاویر انجام نشد.');}
    redirect($back);
}

function handle_media_asset_upload(): never
{
    verify_csrf();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');$created=[];$mediaId=0;
    try{
        $file=$_FILES['media_file']??null;if(!is_array($file))throw new RuntimeException('تصویری برای بارگذاری انتخاب نشده است.');
        db()->beginTransaction();$mediaId=(int)(store_media_asset($file,$created)??0);if($mediaId<=0)throw new RuntimeException('تصویری برای بارگذاری انتخاب نشده است.');
        $st=db()->prepare('SELECT * FROM '.table('media_assets').' WHERE id=? LIMIT 1');$st->execute([$mediaId]);$asset=$st->fetch();if(!$asset)throw new RuntimeException('تصویر ذخیره شد اما اطلاعات آن قابل بازیابی نیست.');db()->commit();
        audit('media_asset_upload','media_asset',$mediaId,['original_name'=>$asset['original_name']??null,'file_size'=>(int)($asset['file_size']??0)]);
        echo json_encode(['ok'=>true,'asset'=>['id'=>$mediaId,'name'=>trim((string)($asset['original_name']??''))?:'تصویر آپلودشده','url'=>runtime_base_url(media_display_path($asset,640))]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();unlink_created_media_paths($created);log_exception($e,'media_asset_upload',['media_id'=>$mediaId]);http_response_code($e instanceof RuntimeException?422:500);echo json_encode(['ok'=>false,'message'=>$e instanceof RuntimeException?$e->getMessage():'بارگذاری تصویر انجام نشد. دوباره تلاش کنید.'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
}
function handle_media_asset_delete(): never
{
    verify_csrf();$mediaId=(int)($_POST['media_id']??0);$roomId=max(0,(int)($_POST['room_id']??0));$serviceId=max(0,(int)($_POST['service_id']??0));
    $back=$serviceId>0?url('services').'#service-gallery-'.$serviceId:($roomId>0?url('rooms',['view'=>'rooms','room'=>$roomId]).'#room-gallery':url('rooms',['view'=>'rooms']));
    if($mediaId<=0){flash('error','تصویر معتبر نیست.');redirect($back);}
    $asset=null;
    try{
        db()->beginTransaction();$st=db()->prepare('SELECT * FROM '.table('media_assets').' WHERE id=? FOR UPDATE');$st->execute([$mediaId]);$asset=$st->fetch();if(!$asset)throw new RuntimeException('تصویر پیدا نشد.');
        $roomUsage=db()->prepare('SELECT COUNT(*) FROM '.table('room_images').' WHERE media_id=?');$roomUsage->execute([$mediaId]);$roomCount=(int)$roomUsage->fetchColumn();
        $serviceUsage=db()->prepare('SELECT COUNT(*) FROM '.table('service_images').' WHERE media_id=?');$serviceUsage->execute([$mediaId]);$serviceCount=(int)$serviceUsage->fetchColumn();$count=$roomCount+$serviceCount;
        if($count>0){$parts=[];if($roomCount)$parts[]=fa_digits($roomCount).' اتاق';if($serviceCount)$parts[]=fa_digits($serviceCount).' خدمت / گشت';throw new RuntimeException('این تصویر هنوز در '.implode(' و ',$parts).' استفاده می‌شود؛ ابتدا آن را از موارد مربوط حذف کنید.');}
        db()->prepare('DELETE FROM '.table('media_assets').' WHERE id=?')->execute([$mediaId]);db()->commit();
        foreach(media_asset_storage_paths($asset) as $path)@unlink(dirname(__DIR__).'/'.$path);
        audit('media_asset_delete','media_asset',$mediaId,['original_name'=>$asset['original_name']??null]);flash('success','تصویر از کتابخانه حذف شد.');
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();log_exception($e,'media_asset_delete',['media_id'=>$mediaId]);flash('error',$e instanceof RuntimeException?$e->getMessage():'حذف تصویر انجام نشد.');}
    redirect($back);
}

function handle_room_price_update(): never
{
    verify_csrf();$id=(int)($_POST['room_id']??0);$base=parse_money_amount($_POST['base_rate']??0);$extra=parse_money_amount($_POST['extra_person_rate']??0);
    if($id<1){flash('error','اتاق معتبر نیست.');redirect(url('rooms',['view'=>'pricing']));}
    try{db()->beginTransaction();$st=db()->prepare('SELECT id,name,base_rate,extra_person_rate FROM '.table('rooms').' WHERE id=? FOR UPDATE');$st->execute([$id]);$room=$st->fetch();if(!$room)throw new RuntimeException('اتاق پیدا نشد.');db()->prepare('UPDATE '.table('rooms').' SET base_rate=?,extra_person_rate=? WHERE id=?')->execute([$base,$extra,$id]);db()->commit();audit('room_price_update','room',$id,['base_before'=>(int)$room['base_rate'],'base_after'=>$base,'extra_before'=>(int)$room['extra_person_rate'],'extra_after'=>$extra]);flash('success','قیمت پایه اتاق ذخیره شد.');}
    catch(Throwable $e){if(db()->inTransaction())db()->rollBack();log_exception($e,'room_price_update',['room_id'=>$id]);flash('error',$e instanceof RuntimeException?$e->getMessage():'ذخیره قیمت اتاق انجام نشد.');}
    redirect(url('rooms',['view'=>'pricing','room'=>$id]));
}

function handle_room_bulk_pricing_save(): never
{
    verify_csrf();$ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['room_ids']??[])),static fn(int $id)=>$id>0)));$target=(string)($_POST['target']??'base_rate');$mode=(string)($_POST['mode']??'fixed');$amount=parse_money_amount($_POST['amount']??0);
    if(!$ids){flash('error','حداقل یک اتاق برای تغییر گروهی انتخاب کنید.');redirect(url('rooms',['view'=>'pricing']));}if(!in_array($target,['base_rate','extra_person_rate'],true)||!in_array($mode,['fixed','increase','decrease'],true)){flash('error','نوع تغییر گروهی معتبر نیست.');redirect(url('rooms',['view'=>'pricing']));}
    try{
        db()->beginTransaction();$marks=implode(',',array_fill(0,count($ids),'?'));$st=db()->prepare('SELECT * FROM '.table('rooms')." WHERE id IN ($marks) FOR UPDATE");$st->execute($ids);$rooms=$st->fetchAll();if(count($rooms)!==count($ids))throw new RuntimeException('یکی از اتاق‌های انتخاب‌شده پیدا نشد.');$changes=[];$skipped=[];
        foreach($rooms as $room){if($target==='extra_person_rate'&&(int)$room['max_capacity']<=(int)$room['standard_capacity']){$skipped[]=(string)$room['name'];continue;}$current=(int)$room[$target];$next=$mode==='increase'?$current+$amount:($mode==='decrease'?max(0,$current-$amount):$amount);db()->prepare('UPDATE '.table('rooms').' SET '.$target.'=? WHERE id=?')->execute([$next,(int)$room['id']]);$changes[]=['room_id'=>(int)$room['id'],'room_name'=>(string)$room['name'],'before'=>$current,'after'=>$next];}
        if(!$changes)throw new RuntimeException('هیچ‌کدام از اتاق‌های انتخاب‌شده برای این تغییر قابل اعمال نیستند.');db()->commit();audit('room_bulk_pricing','room',null,['target'=>$target,'mode'=>$mode,'amount'=>$amount,'changes'=>$changes,'skipped'=>$skipped]);flash('success','قیمت '.fa_digits(count($changes)).' اتاق به‌صورت گروهی به‌روز شد.');if($skipped)flash('info','هزینه نفر اضافه برای این اتاق‌ها تغییر نکرد چون ظرفیت اضافه ندارند: '.implode('، ',$skipped));
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();log_exception($e,'room_bulk_pricing');flash('error',$e instanceof RuntimeException?$e->getMessage():'تغییر گروهی قیمت انجام نشد.');}
    redirect(url('rooms',['view'=>'pricing']));
}
function handle_amenity_save(): never
{
    verify_csrf();$id=(int)($_POST['amenity_id']??0);$scope=(string)($_POST['scope']??'room');$returnScope=in_array((string)($_POST['return_scope']??$scope),['room','property'],true)?(string)($_POST['return_scope']??$scope):'room';$name=trim((string)($_POST['name']??''));$active=!empty($_POST['active'])?1:0;$public=!empty($_POST['public_visible'])?1:0;$sort=max(-999,min(999,(int)en_digits((string)($_POST['sort_order']??0))));
    if(!in_array($scope,['room','property'],true)||$name===''){flash('error','نوع و نام امکان را کامل کنید.');redirect(url('rooms',['view'=>'amenities','scope'=>$returnScope]));}if(text_length($name)>100){flash('error','نام امکان حداکثر ۱۰۰ نویسه است.');redirect(url('rooms',['view'=>'amenities','scope'=>$returnScope]));}
    try{if($id>0){$st=db()->prepare('UPDATE '.table('amenities').' SET scope=?,name=?,active=?,public_visible=?,sort_order=? WHERE id=?');$st->execute([$scope,$name,$active,$public,$sort,$id]);if($st->rowCount()===0){$chk=db()->prepare('SELECT id FROM '.table('amenities').' WHERE id=?');$chk->execute([$id]);if(!$chk->fetchColumn())throw new RuntimeException('امکان پیدا نشد.');}}else{db()->prepare('INSERT INTO '.table('amenities').' (scope,name,active,public_visible,sort_order) VALUES (?,?,?,?,?)')->execute([$scope,$name,$active,$public,$sort]);$id=(int)db()->lastInsertId();}audit('amenity_save','amenity',$id,['scope'=>$scope]);flash('success','فهرست امکانات ذخیره شد.');}catch(Throwable $e){log_exception($e,'amenity_save',['amenity_id'=>$id]);flash('error',$e instanceof PDOException&&str_contains($e->getMessage(),'Duplicate')?'امکانی با همین نام در این بخش وجود دارد.':($e instanceof RuntimeException?$e->getMessage():'ذخیره امکان انجام نشد.'));}
    redirect(url('rooms',['view'=>'amenities','scope'=>$returnScope]));
}
function handle_amenity_delete(): never
{
    verify_csrf();$id=(int)($_POST['amenity_id']??0);$returnScope=in_array((string)($_POST['return_scope']??'room'),['room','property'],true)?(string)($_POST['return_scope']??'room'):'room';
    try{
        db()->beginTransaction();$st=db()->prepare('SELECT name,scope FROM '.table('amenities').' WHERE id=? FOR UPDATE');$st->execute([$id]);$amenity=$st->fetch();if(!$amenity)throw new RuntimeException('امکان پیدا نشد.');
        if((string)$amenity['scope']==='room'){$usageStmt=db()->prepare('SELECT room_id FROM '.table('room_amenities').' WHERE amenity_id=? FOR UPDATE');$usageStmt->execute([$id]);if($usageStmt->fetchColumn()!==false)throw new RuntimeException('این امکان هنوز به یک یا چند اتاق وصل است؛ ابتدا آن را از اتاق‌ها بردارید یا غیرفعالش کنید.');}
        db()->prepare('DELETE FROM '.table('amenities').' WHERE id=?')->execute([$id]);db()->commit();audit('amenity_delete','amenity',$id,['name'=>$amenity['name'],'scope'=>$amenity['scope']]);flash('success','امکان حذف شد.');
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();log_exception($e,'amenity_delete',['amenity_id'=>$id]);flash('error',$e instanceof RuntimeException?$e->getMessage():'حذف امکان انجام نشد.');}
    redirect(url('rooms',['view'=>'amenities','scope'=>$returnScope]));
}

function handle_room_block_create(): never
{
    verify_csrf();$rid=(int)($_POST['room_id']??0);$in=Jalali::toIso(en_digits((string)($_POST['start_date']??'')));$out=Jalali::toIso(en_digits((string)($_POST['end_date']??'')));$reason=trim((string)($_POST['reason']??''));
    if(!$in||!$out||$in>=$out||$reason===''){flash('error','بازه و علت خارج از سرویس را کامل کنید.');redirect(url('rooms',['view'=>'rooms','room'=>$rid]).'#room-out-of-service');}
    if(text_length($reason)>255){flash('error','علت خارج از سرویس حداکثر ۲۵۵ نویسه است.');redirect(url('rooms',['view'=>'rooms','room'=>$rid]).'#room-out-of-service');}
    try{
        db()->beginTransaction();$lock=db()->prepare('SELECT * FROM '.table('rooms').' WHERE id=? FOR UPDATE');$lock->execute([$rid]);$room=$lock->fetch();if(!$room)throw new RuntimeException('اتاق پیدا نشد.');
        if(!room_accepts_stay($room,$in,$out))throw new RuntimeException('اتاق در تمام این بازه داخل چرخه فعالیت نیست. تاریخ شروع یا خروج از چرخه را بررسی کنید.');
        $busy=db()->prepare("SELECT 1 FROM ".table('reservation_rooms')." rr JOIN ".table('reservations')." r ON r.id=rr.reservation_id WHERE rr.room_id=? AND rr.check_in<? AND rr.check_out>? AND r.status<>'cancelled' LIMIT 1");$busy->execute([$rid,$out,$in]);if($busy->fetchColumn())throw new RuntimeException('در این بازه رزرو فعال وجود دارد؛ ابتدا رزرو را تعیین تکلیف کنید.');
        $block=db()->prepare('SELECT 1 FROM '.table('room_blocks').' WHERE room_id=? AND start_date<? AND end_date>? LIMIT 1');$block->execute([$rid,$out,$in]);if($block->fetchColumn())throw new RuntimeException('این بازه با یک خارج‌ازسرویس دیگر هم‌پوشانی دارد.');
        db()->prepare('INSERT INTO '.table('room_blocks').' (room_id,start_date,end_date,reason,created_by) VALUES (?,?,?,?,?)')->execute([$rid,$in,$out,$reason,current_user()['id']]);db()->commit();audit('room_block_create','room',$rid,['start'=>$in,'end'=>$out]);ReportManager::capturePaceSnapshotSafe();flash('success','بازه خارج از سرویس ثبت شد.');
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();log_exception($e,'room_block_create',['room_id'=>$rid]);flash('error',$e instanceof RuntimeException?$e->getMessage():'ثبت خارج از سرویس انجام نشد.');}
    redirect(url('rooms',['view'=>'rooms','room'=>$rid]).'#room-out-of-service');
}

function handle_room_block_delete(): never
{
    verify_csrf();$id=(int)($_POST['block_id']??0);$rid=(int)($_POST['room_id']??0);
    try{db()->prepare('DELETE FROM '.table('room_blocks').' WHERE id=?'.($rid>0?' AND room_id=?':''))->execute($rid>0?[$id,$rid]:[$id]);audit('room_block_delete','room_block',$id,['room_id'=>$rid]);ReportManager::capturePaceSnapshotSafe();flash('success','بازه خارج از سرویس حذف شد.');}
    catch(Throwable $e){log_exception($e,'room_block_delete',['block_id'=>$id,'room_id'=>$rid]);flash('error','حذف بازه خارج از سرویس انجام نشد.');}
    redirect($rid>0?url('rooms',['view'=>'rooms','room'=>$rid]).'#room-out-of-service':url('rooms',['view'=>'rooms']));
}
function handle_rate_override_save(): never
{
    verify_csrf();$id=(int)($_POST['override_id']??0);$batch=trim((string)($_POST['batch_key']??''));if($batch!==''&&!preg_match('/^[a-f0-9]{16,32}$/',$batch))$batch='';
    $roomIds=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['room_ids']??[])))));if(!$roomIds&&isset($_POST['room_id']))$roomIds=[(int)$_POST['room_id']];$roomIds=array_values(array_filter($roomIds,static fn(int $x)=>$x>0));if(!$roomIds){flash('error','حداقل یک اتاق انتخاب کنید.');redirect(url('rooms',['view'=>'pricing']));}
    $in=Jalali::toIso(en_digits((string)($_POST['start_date']??'')));$out=Jalali::toIso(en_digits((string)($_POST['end_date']??'')));if(!$in||!$out||$in>=$out){flash('error','بازه قیمت معتبر نیست.');redirect(url('rooms',['view'=>'pricing']));}
    $mode=in_array((string)($_POST['rate_mode']??'fixed'),['fixed','increase','decrease'],true)?(string)$_POST['rate_mode']:'fixed';$base=parse_money_amount($_POST['base_rate']??0);$extra=parse_money_amount($_POST['extra_person_rate']??0);$label=trim((string)($_POST['label']??''))?:null;
    if($label!==null&&text_length($label)>160){flash('error','عنوان قیمت بازه‌ای حداکثر ۱۶۰ نویسه است.');redirect(url('rooms',['view'=>'pricing']));}
    try{
        db()->beginTransaction();
        $valid=db()->prepare('SELECT * FROM '.table('rooms').' WHERE id=? FOR UPDATE');
        foreach($roomIds as $rid){$valid->execute([$rid]);$room=$valid->fetch();if(!$room)throw new RuntimeException('یکی از اتاق‌های انتخاب‌شده پیدا نشد.');if(!room_accepts_stay($room,$in,$out))throw new RuntimeException('یکی از اتاق‌های انتخاب‌شده در تمام این بازه داخل چرخه فعالیت نیست.');}
        if($batch==='')$batch=bin2hex(random_bytes(8));
        foreach($roomIds as $rid){$overlap=db()->prepare("SELECT 1 FROM ".table('room_rate_overrides')." WHERE room_id=? AND start_date<? AND end_date>? AND COALESCE(batch_key,'')<>? ".($id>0?'AND id<>? ':'')."LIMIT 1");$args=[$rid,$out,$in,$batch];if($id>0)$args[]=$id;$overlap->execute($args);if($overlap->fetchColumn())throw new RuntimeException('برای یکی از اتاق‌ها در این تاریخ، قیمت بازه‌ای دیگری وجود دارد.');}
        if($id>0&&empty($_POST['batch_key'])){db()->prepare('UPDATE '.table('room_rate_overrides').' SET room_id=?,start_date=?,end_date=?,rate_mode=?,base_rate=?,extra_person_rate=?,label=?,created_by=? WHERE id=?')->execute([$roomIds[0],$in,$out,$mode,$base,$extra,$label,current_user()['id'],$id]);$saved=[$id];}
        else{
            $existing=[];$st=db()->prepare('SELECT id,room_id FROM '.table('room_rate_overrides').' WHERE batch_key=? FOR UPDATE');$st->execute([$batch]);foreach($st->fetchAll() as $row)$existing[(int)$row['room_id']]=(int)$row['id'];
            if($existing){$remove=array_diff(array_keys($existing),$roomIds);if($remove){$ph=implode(',',array_fill(0,count($remove),'?'));db()->prepare('DELETE FROM '.table('room_rate_overrides').' WHERE batch_key=? AND room_id IN ('.$ph.')')->execute(array_merge([$batch],array_values($remove)));}}
            $saved=[];foreach($roomIds as $rid){if(isset($existing[$rid])){$oid=$existing[$rid];db()->prepare('UPDATE '.table('room_rate_overrides').' SET start_date=?,end_date=?,rate_mode=?,base_rate=?,extra_person_rate=?,label=?,created_by=? WHERE id=?')->execute([$in,$out,$mode,$base,$extra,$label,current_user()['id'],$oid]);}else{db()->prepare('INSERT INTO '.table('room_rate_overrides').' (room_id,start_date,end_date,rate_mode,base_rate,extra_person_rate,label,batch_key,created_by) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$rid,$in,$out,$mode,$base,$extra,$label,$batch,current_user()['id']]);$oid=(int)db()->lastInsertId();}$saved[]=$oid;}
        }
        db()->commit();audit('rate_override_save','rate_override',$saved[0]??0,['rooms'=>$roomIds,'mode'=>$mode,'batch_key'=>$batch]);flash('success',count($roomIds)>1?'قیمت بازه‌ای برای '.fa_digits(count($roomIds)).' اتاق ذخیره شد.':'قیمت بازه‌ای ذخیره شد.');
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();log_exception($e,'rate_override_save',['rooms'=>$roomIds,'mode'=>$mode]);flash('error',$e instanceof RuntimeException?$e->getMessage():'ذخیره قیمت بازه‌ای انجام نشد.');}
    redirect(url('rooms',['view'=>'pricing']));
}

function handle_rate_override_delete(): never
{
    verify_csrf();$id=(int)($_POST['override_id']??0);$batch=trim((string)($_POST['batch_key']??''));if($batch!==''&&preg_match('/^[a-f0-9]{16,32}$/',$batch)){db()->prepare('DELETE FROM '.table('room_rate_overrides').' WHERE batch_key=?')->execute([$batch]);audit('rate_override_delete','rate_override',null,['batch_key'=>$batch]);flash('success','گروه قیمت بازه‌ای حذف شد.');}else{db()->prepare('DELETE FROM '.table('room_rate_overrides').' WHERE id=?')->execute([$id]);audit('rate_override_delete','rate_override',$id);flash('success','قیمت بازه‌ای حذف شد.');}redirect(url('rooms',['view'=>'pricing']));
}

function handle_features_save(): never
{
    verify_csrf();$defs=FeatureRegistry::definitions();$section=in_array((string)($_POST['features_section']??'all'),['features','cafe','all'],true)?(string)($_POST['features_section']??'all'):'all';$cafeTokenMissing=false;
    try{
        db()->beginTransaction();$st=db()->prepare('INSERT INTO '.table('features').' (feature_key,enabled) VALUES (?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),updated_at=NOW()');
        foreach($defs as $key=>$meta){if($section==='features'&&$key==='cafe')continue;if($section==='cafe'&&$key!=='cafe')continue;$enabled=!empty($_POST['features'][$key])?1:0;if($key==='cafe'&&$enabled&&!cafe_api_token_configured()){$enabled=0;$cafeTokenMissing=true;}$st->execute([$key,$enabled]);}
        db()->commit();FeatureRegistry::clearRequestCache();audit('features_save','settings',null,['section'=>$section]);if($cafeTokenMissing)flash('info','اتصال کافه تا زمان ساخت توکن فعال نشد.');flash('success',$section==='cafe'?'وضعیت اتصال کافه ذخیره شد.':'قابلیت‌های سامانه ذخیره شدند.');
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();FeatureRegistry::clearRequestCache();log_exception($e,'features_save',['section'=>$section]);flash('error','ذخیره قابلیت‌های سامانه انجام نشد.');}
    redirect(url($section==='cafe'?'cafe_connection':'features'));
}
function handle_service_catalog_save(): never { verify_csrf();$id=(int)($_POST['service_id']??0);$name=trim((string)($_POST['name']??''));if($name===''){flash('error','نام خدمت الزامی است.');redirect(url('services'));}$category=in_array($_POST['category']??'other',['tour','transfer','food','experience','rental','other'],true)?(string)$_POST['category']:'other';$pricing=in_array($_POST['pricing_type']??'fixed',['fixed','per_person','quantity'],true)?(string)$_POST['pricing_type']:'fixed';$args=[$category,$name,$pricing,parse_money_amount($_POST['default_price']??0),parse_money_amount($_POST['supplier_cost']??0),trim((string)($_POST['provider_name']??''))?:null,normalize_phone((string)($_POST['provider_phone']??'')),!empty($_POST['is_timed'])?1:0,($_POST['capacity']??'')===''?null:max(1,(int)$_POST['capacity']),!empty($_POST['active'])?1:0,(int)($_POST['sort_order']??0)];if($id>0){db()->prepare('UPDATE '.table('extra_services').' SET category=?,name=?,pricing_type=?,default_price=?,supplier_cost=?,provider_name=?,provider_phone=?,is_timed=?,capacity=?,active=?,sort_order=? WHERE id=?')->execute([...$args,$id]);}else{db()->prepare('INSERT INTO '.table('extra_services').' (category,name,pricing_type,default_price,supplier_cost,provider_name,provider_phone,is_timed,capacity,active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute($args);$id=(int)db()->lastInsertId();}audit('service_catalog_save','extra_service',$id);flash('success','خدمت ذخیره شد.');redirect(url('services')); }
function service_total(string $pricing,int $unit,int $quantity,int $people): int { return $pricing==='fixed'?$unit:checked_money_multiply($unit,$pricing==='per_person'?max(1,$people):max(1,$quantity)); }
function verify_service_capacity(array $service,?string $date,?string $time,int $quantity,int $people,?int $excludeRecord=null): void
{
    if(empty($service['is_timed']))return;
    if(!$date)throw new RuntimeException('برای این خدمت زمان‌دار، تاریخ خدمت الزامی است.');
    $capacity=(int)($service['capacity']??0);if($capacity<=0)return;
    $pricing=(string)($service['pricing_type']??$service['pricing_type_snapshot']??'fixed');
    $requested=service_capacity_units($pricing,$quantity,$people);
    $sql="SELECT COALESCE(SUM(CASE pricing_type_snapshot WHEN 'quantity' THEN GREATEST(quantity,1) WHEN 'per_person' THEN GREATEST(people_count,1) ELSE 1 END),0) FROM ".table('reservation_services')." WHERE service_id=? AND service_date=? AND status<>'cancelled' AND ((service_time IS NULL AND ? IS NULL) OR service_time=?)";
    $args=[(int)$service['id'],$date,$time,$time];if($excludeRecord){$sql.=' AND id<>?';$args[]=$excludeRecord;}
    $st=db()->prepare($sql);$st->execute($args);$used=(int)$st->fetchColumn();
    if($used+$requested>$capacity)throw new RuntimeException('ظرفیت این نوبت خدمت تکمیل است یا تعداد درخواستی بیشتر از ظرفیت باقی‌مانده است.');
}
function handle_service_add(): never
{
    verify_csrf();
    $rid=(int)($_POST['reservation_id']??0);$back=service_add_back($rid);
    $reservation=get_reservation($rid);
    if(!$reservation){flash('error','رزرو پیدا نشد.');redirect($back);}
    if($reservation['status']==='cancelled'){flash('error','به رزرو لغوشده خدمت جدید اضافه نمی‌شود.');redirect($back);}
    $allocations=get_allocations($rid);
    if(reservation_service_financial_admin_only($reservation,$allocations)&&!is_admin()){flash('error','از روز خروج به بعد، افزودن خدمت فقط برای اصلاح مدیریتی مجاز است.');redirect($back);}

    $sid=(int)($_POST['service_id']??0);
    $qty=max(1,(int)($_POST['quantity']??1));
    $people=max(1,(int)($_POST['people_count']??1));
    $status=in_array($_POST['status']??'requested',['requested','confirmed','done','cancelled'],true)?(string)$_POST['status']:'requested';
    $rawDate=trim((string)($_POST['service_date']??''));
    $date=$rawDate!==''?Jalali::toIso(en_digits($rawDate)):null;
    if($rawDate!==''&&!$date){flash('error','تاریخ خدمت معتبر نیست.');redirect($back);}
    $time=trim((string)($_POST['service_time']??''))?:null;
    if($time!==null&&!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',en_digits($time))){flash('error','ساعت خدمت معتبر نیست.');redirect($back);}
    $time=$time!==null?en_digits($time):null;
    if($time!==null&&$date===null){flash('error','برای ثبت ساعت، ابتدا تاریخ اجرا را انتخاب کنید.');redirect($back);}
    if(!service_date_within_stay($allocations,$date)){flash('error','تاریخ اجرای خدمت باید داخل بازه اقامت همین رزرو باشد.');redirect($back);}
    $notes=trim((string)($_POST['notes']??''));if(text_length($notes)>500){flash('error','یادداشت خدمت حداکثر ۵۰۰ کاراکتر است.');redirect($back);}
    $changeReason=trim((string)($_POST['change_reason']??''))?:null;
    $token=trim((string)($_POST['client_token']??''));
    $fingerprint=financial_commit_fingerprint(['reservation_id'=>$rid,'kind'=>'service_add','service_id'=>$sid,'unit_price'=>parse_money_amount($_POST['unit_price']??0),'quantity'=>$qty,'people_count'=>$people,'status'=>$status,'service_date'=>$date,'service_time'=>$time,'notes'=>$notes,'change_reason'=>$changeReason]);
    $replay=financial_commit_replay_state($token,$fingerprint);
    if($replay==='invalid'){flash('error','فرم ثبت خدمت منقضی یا نامعتبر است؛ صفحه را تازه‌سازی و دوباره ثبت کنید.');redirect($back);}
    if($replay==='conflict'){flash('error','این فرم قبلاً با اطلاعات دیگری ارسال شده است؛ صفحه را تازه‌سازی کنید.');redirect($back);}
    if($replay==='replay'){flash('info','این خدمت قبلاً ثبت شده است و دوباره به حساب رزرو اضافه نشد.');redirect($back);}

    try{
        db()->beginTransaction();
        $durableReplay=FinancialCommitGuard::claim(db(),$token,$fingerprint,'service_add',$rid);
        if($durableReplay==='invalid')throw new RuntimeException('فرم مالی نامعتبر است.');
        if($durableReplay==='replay'||$durableReplay==='conflict'){
            db()->rollBack();
            flash($durableReplay==='replay'?'info':'error',
                $durableReplay==='replay'?'این خدمت قبلاً ثبت شده و دوباره ایجاد نشد.':'این فرم قبلاً با اطلاعات متفاوت استفاده شده است.');
            redirect($back);
        }
        
        $reservationLock=db()->prepare('SELECT status,cancellation_reason FROM '.table('reservations').' WHERE id=? FOR UPDATE');
        $reservationLock->execute([$rid]);
        $lockedReservation=$reservationLock->fetch();
        if(!$lockedReservation)throw new RuntimeException('رزرو پیدا نشد.');
        if((string)$lockedReservation['status']==='cancelled')throw new RuntimeException('به رزرو لغوشده خدمت جدید اضافه نمی‌شود.');
        $lockedAllocations=get_allocations($rid);
        $historical=reservation_service_financial_admin_only($lockedReservation,$lockedAllocations);
        if($historical&&!is_admin())throw new RuntimeException('از روز خروج به بعد، افزودن خدمت فقط برای مدیر مجاز است.');
        if($historical&&$changeReason===null)throw new RuntimeException('برای اصلاح مالی از روز خروج به بعد، درج دلیل الزامی است.');
        if(!service_date_within_stay($lockedAllocations,$date))throw new RuntimeException('تاریخ اجرای خدمت باید داخل بازه اقامت همین رزرو باشد.');

        $serviceSt=db()->prepare('SELECT * FROM '.table('extra_services').' WHERE id=? AND active=1 FOR UPDATE');
        $serviceSt->execute([$sid]);
        $service=$serviceSt->fetch();
        if(!$service)throw new RuntimeException('خدمت پیدا نشد یا غیرفعال است.');

        $unit=parse_money_amount($_POST['unit_price']??$service['default_price']);
        if($status!=='cancelled')verify_service_capacity($service,$date,$time,$qty,$people);
        $total=service_total((string)$service['pricing_type'],$unit,$qty,$people);
        db()->prepare('INSERT INTO '.table('reservation_services').' (reservation_id,service_id,name_snapshot,pricing_type_snapshot,unit_price,quantity,people_count,total,supplier_cost,provider_snapshot,status,service_date,service_time,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$rid,$sid,$service['name'],$service['pricing_type'],$unit,$qty,$people,$total,(int)$service['supplier_cost'],$service['provider_name'],$status,$date,$time,$notes!==''?$notes:null,current_user()['id']]);
        db()->commit();
        financial_commit_remember($token,$fingerprint);
        audit('service_add','reservation',$rid,['service_id'=>$sid,'total'=>$total,'historical'=>$historical,'reason'=>$historical?$changeReason:null]);
        flash('success','خدمت به پرونده رزرو اضافه شد.');
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        flash('error',$e instanceof RuntimeException?$e->getMessage():'ثبت خدمت انجام نشد.');
    }
    redirect($back);
}
function handle_service_update(): never
{
    verify_csrf();
    $id=(int)($_POST['reservation_service_id']??0);
    $rid=(int)($_POST['reservation_id']??0);
    $qty=max(1,(int)($_POST['quantity']??1));
    $people=max(1,(int)($_POST['people_count']??1));
    $rawDate=trim((string)($_POST['service_date']??''));
    $date=$rawDate!==''?Jalali::toIso(en_digits($rawDate)):null;
    if($rawDate!==''&&!$date){flash('error','تاریخ خدمت معتبر نیست.');redirect(reservation_back($rid));}
    $time=trim((string)($_POST['service_time']??''))?:null;
    if($time!==null&&!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',en_digits($time))){flash('error','ساعت خدمت معتبر نیست.');redirect(reservation_back($rid));}
    $time=$time!==null?en_digits($time):null;
    if($time!==null&&$date===null){flash('error','برای ثبت ساعت، ابتدا تاریخ اجرا را انتخاب کنید.');redirect(reservation_back($rid));}
    $notes=trim((string)($_POST['notes']??''));if(text_length($notes)>500){flash('error','یادداشت خدمت حداکثر ۵۰۰ کاراکتر است.');redirect(reservation_back($rid));}
    $changeReason=trim((string)($_POST['change_reason']??''))?:null;

    try{
        db()->beginTransaction();
        $reservationSt=db()->prepare('SELECT status,cancellation_reason FROM '.table('reservations').' WHERE id=? FOR UPDATE');
        $reservationSt->execute([$rid]);$reservation=$reservationSt->fetch();if(!$reservation)throw new RuntimeException('رزرو پیدا نشد.');
        $allocations=get_allocations($rid);
        $historical=reservation_service_financial_admin_only($reservation,$allocations);
        if($historical&&!is_admin())throw new RuntimeException('اصلاح مالی خدمات پس از پایان اقامت، لغو یا عدم مراجعه فقط برای مدیر مجاز است.');
        if($historical&&$changeReason===null)throw new RuntimeException('برای اصلاح مالی تاریخی خدمات، درج دلیل الزامی است.');

        $rowSt=db()->prepare('SELECT rs.*,es.is_timed,es.capacity FROM '.table('reservation_services').' rs LEFT JOIN '.table('extra_services').' es ON es.id=rs.service_id WHERE rs.id=? AND rs.reservation_id=? FOR UPDATE');
        $rowSt->execute([$id,$rid]);
        $row=$rowSt->fetch();
        if(!$row)throw new RuntimeException('خدمت پیدا نشد.');

        if(!empty($row['service_id'])){
            $catalogLock=db()->prepare('SELECT id FROM '.table('extra_services').' WHERE id=? FOR UPDATE');
            $catalogLock->execute([(int)$row['service_id']]);
        }

        $unit=parse_money_amount($_POST['unit_price']??$row['unit_price']);
        $qty=max(1,(int)($_POST['quantity']??$row['quantity']));
        $people=max(1,(int)($_POST['people_count']??$row['people_count']));
        $status=in_array($_POST['status']??$row['status'],['requested','confirmed','done','cancelled'],true)?(string)$_POST['status']:(string)$row['status'];
        if($status!=='cancelled'&&!service_date_within_stay($allocations,$date))throw new RuntimeException('تاریخ اجرای خدمت باید داخل بازه اقامت همین رزرو باشد.');
        if($status!=='cancelled'&&!empty($row['service_id']))verify_service_capacity(['id'=>$row['service_id'],'is_timed'=>$row['is_timed'],'capacity'=>$row['capacity'],'pricing_type_snapshot'=>$row['pricing_type_snapshot']],$date,$time,$qty,$people,$id);
        $total=service_total((string)$row['pricing_type_snapshot'],$unit,$qty,$people);
        db()->prepare('UPDATE '.table('reservation_services').' SET unit_price=?,quantity=?,people_count=?,total=?,status=?,service_date=?,service_time=?,notes=? WHERE id=? AND reservation_id=?')->execute([$unit,$qty,$people,$total,$status,$date,$time,$notes!==''?$notes:null,$id,$rid]);
        db()->commit();
        audit('service_update','reservation',$rid,['service_record'=>$id,'historical'=>$historical,'reason'=>$historical?$changeReason:null,'before'=>['status'=>$row['status'],'unit_price'=>(int)$row['unit_price'],'quantity'=>(int)$row['quantity'],'people_count'=>(int)$row['people_count'],'total'=>(int)$row['total'],'service_date'=>$row['service_date'],'service_time'=>$row['service_time']],'after'=>['status'=>$status,'unit_price'=>$unit,'quantity'=>$qty,'people_count'=>$people,'total'=>$total,'service_date'=>$date,'service_time'=>$time]]);
        flash('success','خدمت به‌روز شد.');
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        flash('error',$e instanceof RuntimeException?$e->getMessage():'به‌روزرسانی خدمت انجام نشد.');
    }
    redirect(reservation_back($rid));
}
function handle_waitlist_add(): never { verify_csrf();$in=Jalali::toIso(en_digits((string)($_POST['check_in']??'')));$out=Jalali::toIso(en_digits((string)($_POST['check_out']??'')));$name=trim((string)($_POST['guest_name']??''));if(!$in||!$out||$in>=$out||$name===''){flash('error','اطلاعات درخواست کامل نیست.');redirect(url('waitlist'));}try{$phone=normalize_mobile_or_throw((string)($_POST['phone']??''));$guests=max(1,(int)($_POST['guests_count']??1));db()->prepare('INSERT INTO '.table('waitlist').' (guest_name,phone_normalized,check_in,check_out,guests_count,notes,created_by) VALUES (?,?,?,?,?,?,?)')->execute([$name,$phone,$in,$out,$guests,trim((string)($_POST['notes']??''))?:null,current_user()['id']]);$id=(int)db()->lastInsertId();audit('waitlist_add','waitlist',$id,['check_in'=>$in,'check_out'=>$out,'guests_count'=>$guests]);flash('success','درخواست در لیست انتظار ثبت شد.');}catch(Throwable $e){flash('error',$e instanceof RuntimeException?$e->getMessage():'ثبت درخواست انجام نشد.');}redirect(url('waitlist')); }
function handle_waitlist_close(): never { verify_csrf();$id=(int)($_POST['waitlist_id']??0);db()->prepare("UPDATE ".table('waitlist')." SET status='closed' WHERE id=? AND status='open'")->execute([$id]);audit('waitlist_close','waitlist',$id);flash('success','درخواست بسته شد.');redirect(url('waitlist')); }
function handle_reminder_add(): never { verify_csrf();$rid=(int)($_POST['reservation_id']??0);$date=Jalali::toIso(en_digits((string)($_POST['due_date']??'')));$time=preg_match('/^\d{2}:\d{2}$/',en_digits((string)($_POST['due_time']??'')))?en_digits((string)$_POST['due_time']):'09:00';$note=trim((string)($_POST['note']??''));if(!$date||$note===''){flash('error','تاریخ و متن یادآوری لازم است.');redirect(reservation_back($rid));}db()->prepare('INSERT INTO '.table('reminders').' (reservation_id,due_at,note,created_by) VALUES (?,?,?,?)')->execute([$rid,$date.' '.$time.':00',$note,current_user()['id']]);flash('success','یادآوری ثبت شد.');redirect(reservation_back($rid)); }
function handle_reminder_done(): never { verify_csrf();$id=(int)($_POST['reminder_id']??0);$rid=(int)($_POST['reservation_id']??0);db()->prepare('UPDATE '.table('reminders').' SET done_at=NOW() WHERE id=? AND reservation_id=?')->execute([$id,$rid]);flash('success','یادآوری انجام‌شده ثبت شد.');redirect(reservation_back($rid)); }

function store_document_asset(array $file,string $kind): ?string
{
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK)throw new RuntimeException('آپلود تصویر سند کامل نشد.');if((int)$file['size']>5*1024*1024)throw new RuntimeException('حجم تصویر سند بیشتر از ۵ مگابایت است.');$info=@getimagesize((string)$file['tmp_name']);if(!$info||!in_array($info['mime'],['image/jpeg','image/png','image/webp'],true))throw new RuntimeException('لوگو و مهر باید JPEG، PNG یا WebP باشند.');$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$info['mime']];$dir=dirname(__DIR__).'/uploads/documents';if(!is_dir($dir)&&!@mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('پوشه تصاویر اسناد ساخته نشد.');$name=preg_replace('/[^a-z]/','',$kind).'-'.date('YmdHis').'-'.bin2hex(random_bytes(3)).'.'.$ext;$dst=$dir.'/'.$name;if(!move_uploaded_file((string)$file['tmp_name'],$dst)&&!rename((string)$file['tmp_name'],$dst))throw new RuntimeException('ذخیره تصویر سند انجام نشد.');@chmod($dst,0644);return 'uploads/documents/'.$name;
}
function remove_public_upload(?string $relative): void
{
    $relative=(string)$relative;if($relative===''||!str_starts_with($relative,'uploads/'))return;$root=realpath(dirname(__DIR__).'/uploads');$path=realpath(dirname(__DIR__).'/'.$relative);if($root&&$path&&str_starts_with($path,$root.DIRECTORY_SEPARATOR)&&is_file($path))@unlink($path);
}

function handle_messages_save(): never
{
    verify_csrf();$rows=[];$allowed=array_fill_keys(template_allowed_variables(),true);
    try{
        foreach((array)($_POST['templates']??[]) as $id=>$data){$title=trim((string)($data['title']??''));$body=trim((string)($data['body']??''));$active=!empty($data['active'])?1:0;if($active&&($title===''||$body===''))throw new RuntimeException('متن فعال باید عنوان و متن داشته باشد.');preg_match_all('/{{\s*([a-z0-9_]+)\s*}}/i',$body,$matches);foreach(array_unique($matches[1]??[]) as $variable)if(!isset($allowed[$variable]))throw new RuntimeException('متغیر ناشناخته {{'.$variable.'}} در یکی از پیام‌ها وجود دارد.');$rows[]=[(int)$id,$title,$body,$active];}
        db()->beginTransaction();$st=db()->prepare('UPDATE '.table('message_templates').' SET title=?,body=?,active=? WHERE id=?');foreach($rows as [$id,$title,$body,$active])$st->execute([$title,$body,$active,$id]);db()->commit();audit('message_templates_save','settings');flash('success','متن‌ها و پیام‌ها ذخیره شدند.');
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();log_exception($e,'message_templates_save');flash('error',$e instanceof RuntimeException?$e->getMessage():'ذخیره متن‌ها انجام نشد.');}
    redirect(url('messages'));
}
function handle_settings_save(): never
{
    verify_csrf();$section=(string)($_POST['settings_section']??'all');if(!in_array($section,['general','voucher','all'],true))$section='all';
    $values=[];$staged=[];$oldFiles=[];$newFiles=[];$existingLogo=property_logo_path();$existingStamp=(string)setting('voucher_stamp_path','');
    try{
        if($section==='general'||$section==='all'){
            $siteUrl=trim((string)($_POST['site_url']??''));if($siteUrl!==''&&(!filter_var($siteUrl,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($siteUrl,PHP_URL_SCHEME)),['http','https'],true)))throw new RuntimeException('آدرس سامانه باید یک نشانی معتبر با http یا https باشد.');
            $checkIn=en_digits(trim((string)($_POST['check_in_time']??'')));$checkOut=en_digits(trim((string)($_POST['check_out_time']??'')));foreach([['ورود',$checkIn],['خروج',$checkOut]] as [$label,$time])if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$time))throw new RuntimeException('ساعت '.$label.' باید به شکل ساعت و دقیقه، مانند ۱۴:۰۰، وارد شود.');
            $normalizeSettingPhone=static function(string $raw,string $label): string {$raw=trim($raw);if($raw==='')return '';$normalized=normalize_phone($raw);$digits=$normalized?preg_replace('/\D/','',$normalized):'';if(!$normalized||$digits===''||strlen($digits)<7||strlen($digits)>15)throw new RuntimeException($label.' معتبر نیست.');return local_phone($normalized);};
            $propertyPhone=$normalizeSettingPhone((string)($_POST['property_phone']??''),'تلفن مجموعه');$whatsappNumber=$normalizeSettingPhone((string)($_POST['whatsapp_number']??''),'شماره واتس‌اپ');
            $values+=['property_name'=>trim((string)($_POST['property_name']??'')),'site_url'=>$siteUrl===''?'':rtrim($siteUrl,'/'),'check_in_time'=>fa_digits($checkIn),'check_out_time'=>fa_digits($checkOut),'whatsapp_number'=>$whatsappNumber,'property_phone'=>$propertyPhone,'property_address'=>trim((string)($_POST['property_address']??'')),'public_show_prices'=>!empty($_POST['public_show_prices'])?'1':'0','public_intro_text'=>trim((string)($_POST['public_intro_text']??''))];if($values['property_name']==='')throw new RuntimeException('نام مجموعه نمی‌تواند خالی باشد.');if(text_length($values['public_intro_text'])>220)throw new RuntimeException('متن معرفی صفحه عمومی حداکثر ۲۲۰ نویسه است.');
            if(array_key_exists('public_horizon_date',$_POST)){$raw=trim(en_digits((string)$_POST['public_horizon_date']));$iso=preg_match('/^\d{4}-\d{2}-\d{2}$/',$raw)?$raw:Jalali::toIso($raw);$today=sokna_today();$latest=(new DateTimeImmutable($today))->modify('+12 months')->format('Y-m-d');if(!$iso||$iso<=$today||$iso>$latest)throw new RuntimeException('تاریخ پایان نمایش عمومی باید بعد از امروز و حداکثر تا ۱۲ ماه آینده باشد.');$values['public_horizon_date']=$iso;}
            $currentLogo=$existingLogo;$targetLogo=$currentLogo;if(!empty($_POST['remove_logo']))$targetLogo='';if(!empty($_FILES['logo']['name'])){$targetLogo=(string)store_document_asset($_FILES['logo'],'logo');if($targetLogo==='')throw new RuntimeException('ذخیره لوگوی مجموعه انجام نشد.');$staged[]=$targetLogo;}$values['property_logo_path']=$targetLogo;$values['voucher_logo_path']=$targetLogo;if($currentLogo!==''&&$currentLogo!==$targetLogo)$oldFiles[]=$currentLogo;if($targetLogo!==''&&$targetLogo!==$currentLogo)$newFiles[]=$targetLogo;
        }
        if($section==='voucher'||$section==='all'){
            $values+=['voucher_title'=>trim((string)($_POST['voucher_title']??'')),'voucher_footer'=>trim((string)($_POST['voucher_footer']??'')),'cancellation_policy_text'=>trim((string)($_POST['cancellation_policy_text']??''))];foreach(['voucher_show_room_prices','voucher_show_discount','voucher_show_services','voucher_show_cafe','voucher_show_cancellation_policy'] as $k)$values[$k]=!empty($_POST[$k])?'1':'0';
            $currentStamp=$existingStamp;$targetStamp=$currentStamp;if(!empty($_POST['remove_stamp']))$targetStamp='';if(!empty($_FILES['stamp']['name'])){$targetStamp=(string)store_document_asset($_FILES['stamp'],'stamp');if($targetStamp==='')throw new RuntimeException('ذخیره مهر ووچر انجام نشد.');$staged[]=$targetStamp;}$values['voucher_stamp_path']=$targetStamp;if($currentStamp!==''&&$currentStamp!==$targetStamp)$oldFiles[]=$currentStamp;if($targetStamp!==''&&$targetStamp!==$currentStamp)$newFiles[]=$targetStamp;
        }
        $retainedLogo=(string)($values['property_logo_path']??$existingLogo);$retainedStamp=(string)($values['voucher_stamp_path']??$existingStamp);foreach([$retainedLogo,$retainedStamp] as $path)if($path!=='')$newFiles[]=$path;
        db()->beginTransaction();foreach($values as $k=>$v)set_setting($k,$v);db()->commit();clear_setting_request_cache();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();clear_setting_request_cache();foreach(array_unique($staged) as $path)remove_public_upload($path);log_exception($e,'settings_save');flash('error',$e instanceof RuntimeException?$e->getMessage():'ذخیره تنظیمات انجام نشد.');redirect(url('settings',['section'=>$section==='voucher'?'voucher':'general']));}
    foreach(array_unique($oldFiles) as $path)if(!in_array($path,$newFiles,true))remove_public_upload($path);audit('settings_save','settings',null,['section'=>$section]);flash('success',$section==='voucher'?'تنظیمات ووچر ذخیره شد.':'اطلاعات مجموعه ذخیره شد.');redirect(url('settings',['section'=>$section==='voucher'?'voucher':'general']));
}

function handle_user_create(): never { verify_csrf();$username=trim((string)($_POST['username']??''));$name=trim((string)($_POST['display_name']??''));$pass=(string)($_POST['password']??'');$role=in_array($_POST['role']??'reservation',['admin','reservation','viewer'],true)?(string)$_POST['role']:'reservation';if(!preg_match('/^[A-Za-z0-9_.-]{3,80}$/',$username)||$name===''||strlen($pass)<10){flash('error','نام کاربری، نام نمایشی یا رمز معتبر نیست.');redirect(url('users'));}try{db()->prepare('INSERT INTO '.table('users').' (username,password_hash,display_name,role) VALUES (?,?,?,?)')->execute([$username,password_hash($pass,PASSWORD_DEFAULT),$name,$role]);$id=(int)db()->lastInsertId();audit('user_create','user',$id);flash('success','کاربر ساخته شد.');}catch(Throwable $e){log_exception($e,'user_create');flash('error','ساخت کاربر انجام نشد؛ نام کاربری ممکن است تکراری باشد.');}redirect(url('users')); }
function handle_user_password(): never
{
    verify_csrf();$id=(int)($_POST['user_id']??0);$pass=(string)($_POST['password']??'');$current=(string)($_POST['current_password']??'');$s=db()->prepare('SELECT password_hash FROM '.table('users').' WHERE id=? AND active=1');$s->execute([(int)current_user()['id']]);$hash=$s->fetchColumn();if(!$hash||!password_verify($current,(string)$hash)){flash('error','رمز فعلی مدیر درست نیست.');redirect(url('users'));}if(strlen($pass)<10){flash('error','رمز جدید باید حداقل ۱۰ کاراکتر باشد.');redirect(url('users'));}
    try{db()->beginTransaction();$target=db()->prepare('SELECT id FROM '.table('users').' WHERE id=? FOR UPDATE');$target->execute([$id]);if(!$target->fetchColumn())throw new RuntimeException('حساب کاربر پیدا نشد.');db()->prepare('UPDATE '.table('users').' SET password_hash=? WHERE id=?')->execute([password_hash($pass,PASSWORD_DEFAULT),$id]);db()->prepare('DELETE FROM '.table('remember_tokens').' WHERE user_id=?')->execute([$id]);db()->commit();audit('user_password','user',$id);flash('success','رمز کاربر تغییر کرد و نشست‌های ماندگار قبلی باطل شدند.');}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();log_exception($e,'user_password',['user_id'=>$id]);flash('error',$e instanceof RuntimeException?$e->getMessage():'تغییر رمز انجام نشد.');}redirect(url('users'));
}
function handle_user_toggle(): never
{
    verify_csrf();$id=(int)($_POST['user_id']??0);if($id===(int)current_user()['id']){flash('error','حساب فعلی را نمی‌توانید غیرفعال کنید.');redirect(url('users'));}
    try{db()->beginTransaction();$admins=db()->query("SELECT id FROM ".table('users')." WHERE role='admin' AND active=1 ORDER BY id FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);$s=db()->prepare('SELECT role,active FROM '.table('users').' WHERE id=? FOR UPDATE');$s->execute([$id]);$target=$s->fetch();if(!$target)throw new RuntimeException('حساب کاربر پیدا نشد.');if((int)$target['active']===1&&(string)$target['role']==='admin'&&count($admins)<=1)throw new RuntimeException('آخرین مدیر فعال را نمی‌توانید غیرفعال کنید.');$next=(int)$target['active']===1?0:1;db()->prepare('UPDATE '.table('users').' SET active=? WHERE id=?')->execute([$next,$id]);db()->prepare('DELETE FROM '.table('remember_tokens').' WHERE user_id=?')->execute([$id]);db()->commit();audit('user_toggle','user',$id,['active'=>$next]);flash('success','وضعیت کاربر تغییر کرد.');}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();log_exception($e,'user_toggle',['user_id'=>$id]);flash('error',$e instanceof RuntimeException?$e->getMessage():'تغییر وضعیت کاربر انجام نشد.');}redirect(url('users'));
}

function handle_cafe_token_create(bool $reauthenticated=false): never { if(!$reauthenticated){verify_csrf();require_recent_password('cafe_connection','cafe_token_create');}$raw='sk_live_'.bin2hex(random_bytes(32));set_setting('cafe_api_token_hash',hash('sha256',$raw));$_SESSION['new_cafe_token']=$raw;audit('cafe_token_create','settings');flash('success','توکن جدید ساخته شد. فقط همین بار نمایش داده می‌شود.');redirect(url('cafe_connection')); }
function handle_cafe_token_revoke(bool $reauthenticated=false): never
{
    if(!$reauthenticated){verify_csrf();require_recent_password('cafe_connection','cafe_token_revoke');}
    try{db()->beginTransaction();set_setting('cafe_api_token_hash','');db()->prepare("UPDATE ".table('features')." SET enabled=0 WHERE feature_key='cafe'")->execute();db()->commit();clear_setting_request_cache('cafe_api_token_hash');FeatureRegistry::clearRequestCache();audit('cafe_token_revoke','settings');flash('success','توکن کافه باطل و اتصال غیرفعال شد.');}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();clear_setting_request_cache('cafe_api_token_hash');FeatureRegistry::clearRequestCache();log_exception($e,'cafe_token_revoke');flash('error','ابطال توکن کافه انجام نشد و وضعیت قبلی حفظ شد.');}redirect(url('cafe_connection'));
}

function handle_core_pair(): never
{
    verify_csrf();require_recent_password('core_connection');
    try{
        $key=(string)($_POST['pairing_key']??'');
        if($key==='')throw new InvalidArgumentException('کلید اتصال مرکز سکنا را وارد کنید.');
        SoknaCoreClient::pairWithKey($key,(int)current_user()['id']);
        flash('success','اتصال به مرکز سکنا با موفقیت ثبت شد. برای عیب‌یابی می‌توانید «آزمایش اتصال» را اجرا کنید.');
    }catch(Throwable $e){
        log_exception($e,'core_pair');
        flash('error',$e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():'اتصال به مرکز سکنا انجام نشد.');
    }
    redirect(url('core_connection'));
}
function handle_core_probe(): never
{
    verify_csrf();
    try{
        SoknaCoreClient::probe((int)current_user()['id']);
        flash('success','اتصال به مرکز سکنا تأیید شد.');
    }catch(Throwable $e){
        log_exception($e,'core_probe');
        flash('error',$e instanceof RuntimeException?$e->getMessage():'آزمایش اتصال به مرکز سکنا انجام نشد.');
    }
    redirect(url('core_connection'));
}
function handle_backup_download(): never { verify_csrf();require_recent_password('backup');try{audit('backup_download_requested','settings');$payload=BackupManagerV2::export();$json=json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="sokna-v2-backup-'.date('Ymd-His').'.json"');header('Content-Length: '.strlen($json));echo $json;exit;}catch(Throwable $e){log_exception($e,'backup_download');flash('error','ساخت فایل پشتیبان انجام نشد. جزئیات فنی در گزارش سامانه ثبت شد.');redirect(url('backup'));} }
function handle_backup_restore(): never
{
    verify_csrf();require_recent_password('backup');
    if(empty($_POST['restore_ack'])){flash('error','برای بازیابی باید جایگزینی اطلاعات فعلی را تأیید کنید.');redirect(url('backup'));}
    if(empty($_FILES['backup_file']['tmp_name'])){flash('error','فایل پشتیبان انتخاب نشده است.');redirect(url('backup'));}
    if((int)($_FILES['backup_file']['size']??0)>100_000_000){flash('error','حجم فایل پشتیبان بیش از حد مجاز است.');redirect(url('backup'));}
    try{
        $payload=json_decode((string)file_get_contents($_FILES['backup_file']['tmp_name']),true,512,JSON_THROW_ON_ERROR);
        // Requests that arrived before the RestoreGate marker may hold the shared
        // gate lock while waiting for this exact PHP session file lock. Releasing
        // the session before promotion avoids a cross-lock deadlock/time-out.
        if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
        $gateId=RestoreGate::beginRestore(dirname(__DIR__),25.0);
        $restoreCommitted=false;$generationRotated=false;
        try{
            BackupManagerV2::restore($payload);
            $restoreCommitted=true;
            AuthEpochGuard::rotate(dirname(__DIR__));
            $generationRotated=true;
        }finally{
            // A committed restore without revoked sessions must fail CLOSED.
            // Marker removal then requires supervised recovery.
            if(!$restoreCommitted||$generationRotated)RestoreGate::endRestore(dirname(__DIR__),$gateId);
        }
        if(session_status()!==PHP_SESSION_ACTIVE && !session_start())throw new RuntimeException('بازگشایی نشست پس از بازیابی ناموفق بود.');
        AuthEpochGuard::enforce(dirname(__DIR__));
        flash('success','پشتیبان بازیابی شد. برای امنیت، ورود مجدد همه کاربران لازم است.');
        redirect(url('login'));
    }catch(Throwable $e){
        log_exception($e,'backup_restore');
        // Preserve the user's error toast if promotion failed or DB rolled back.
        if(session_status()!==PHP_SESSION_ACTIVE)@session_start();
        try{AuthEpochGuard::enforce(dirname(__DIR__));}catch(Throwable $epochError){log_exception($epochError,'backup_restore_session');}
        flash('error','بازیابی انجام نشد یا نیازمند بررسی ایمنی است. جزئیات فنی در گزارش سامانه ثبت شد.');
    }
    redirect(url('backup'));
}
function handle_font_retry(): never { verify_csrf();$ok=FontManager::ensureLocal(4,true);flash($ok?'success':'warning',$ok?'فونت فارسی وزیرمتن روی سرور آماده شد.':'دریافت فونت انجام نشد؛ رابط با فونت جایگزین امن ادامه می‌دهد.');redirect(url('health')); }
function handle_font_upload(): never { verify_csrf();try{FontManager::installUploaded($_FILES['font_file']??[]);flash('success','فونت وزیرمتن روی همین سرور نصب شد.');}catch(Throwable $e){log_exception($e,'font_upload');flash('error',$e instanceof RuntimeException?$e->getMessage():'نصب فونت انجام نشد.');}redirect(url('health')); }
function handle_support_download(): never { verify_csrf();$health=health_snapshot();$payload=['format'=>'sokna-v2-support','created_at'=>date(DATE_ATOM),'health'=>$health,'cafe_schema'=>cafe_api_schema_diagnostics(),'latest_update'=>UpdateManagerV2::latestOperation(),'features'=>[],'counts'=>[]];foreach(FeatureRegistry::definitions() as $key=>$meta)$payload['features'][$key]=feature_enabled($key);foreach(['rooms','guests','reservations','payments','reservation_services','cafe_charges'] as $t){try{$payload['counts'][$t]=(int)db()->query('SELECT COUNT(*) FROM '.table($t))->fetchColumn();}catch(Throwable $e){$payload['counts'][$t]=null;}}$json=json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="sokna-support-'.date('Ymd-His').'.json"');echo $json;exit; }
function handle_update_upload(): never
{
    verify_csrf();require_recent_password('updates');
    if(empty($_FILES['update_package']['tmp_name'])){flash('error','بسته به‌روزرسانی انتخاب نشده است.');redirect(url('updates'));}
    if((int)($_FILES['update_package']['size']??0)>150_000_000){flash('error','حجم فایل Update بیش از حد مجاز است.');redirect(url('updates'));}
    try{audit('update_prepare_requested','settings',null,['current_version'=>UpdateManagerV2::currentVersion()]);$operation=UpdateManagerV2::prepare((string)$_FILES['update_package']['tmp_name'],(int)current_user()['id']);redirect(url('updates',['operation'=>$operation]));}
    catch(Throwable $e){log_exception($e,'update_prepare');flash('error',$e instanceof RuntimeException?$e->getMessage():'آماده‌سازی به‌روزرسانی انجام نشد.');redirect(url('updates'));}
}
function handle_update_continue(): never
{
    verify_csrf();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    try{$operation=(string)($_POST['operation']??'');echo json_encode(['ok'=>true,'update'=>UpdateManagerV2::continue($operation,(int)current_user()['id'])],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    catch(Throwable $e){log_exception($e,'update_continue');http_response_code(422);echo json_encode(['ok'=>false,'message'=>'ادامه به‌روزرسانی انجام نشد. صفحه را تازه کنید و دوباره تلاش کنید.'],JSON_UNESCAPED_UNICODE);}
    exit;
}
function handle_update_recover(): never
{
    verify_csrf();require_recent_password('updates');$operation=(string)($_POST['operation']??'');
    try{UpdateManagerV2::requestRollback($operation,(int)current_user()['id']);flash('info','بازیابی نسخه قبلی شروع شد.');}
    catch(Throwable $e){log_exception($e,'update_recover');flash('error','شروع بازیابی انجام نشد.');}
    redirect(url('updates',['operation'=>$operation]));
}

function handle_update_rollback_previous(): never
{
    verify_csrf();require_recent_password('updates');
    if(empty($_POST['rollback_ack'])){flash('error','برای بازگشت باید احتمال از دست رفتن تغییرات بعد از Snapshot را تأیید کنید.');redirect(url('updates'));}
    try{$candidate=UpdateManagerV2::previousRollbackCandidate();if(!$candidate)throw new RuntimeException('نسخه قابل بازگشت در دسترس نیست.');audit('update_rollback_requested','settings',null,['from'=>$candidate['to'],'to'=>$candidate['from']]);UpdateManagerV2::requestRollback((string)$candidate['operation'],(int)current_user()['id']);redirect(url('updates',['operation'=>(string)$candidate['operation']]));}
    catch(Throwable $e){log_exception($e,'update_rollback_previous');flash('error',$e instanceof RuntimeException?$e->getMessage():'شروع بازگشت به نسخه قبلی انجام نشد.');redirect(url('updates'));}
}
