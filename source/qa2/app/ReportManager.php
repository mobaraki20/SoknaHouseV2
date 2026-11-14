<?php
declare(strict_types=1);

final class ReportManager
{
    private const SNAPSHOT_ACTION='report_pace_snapshot_v1';
    private const SNAPSHOT_MONTHS=6;
    private const MONTH_NAMES=[1=>'فروردین',2=>'اردیبهشت',3=>'خرداد',4=>'تیر',5=>'مرداد',6=>'شهریور',7=>'مهر',8=>'آبان',9=>'آذر',10=>'دی',11=>'بهمن',12=>'اسفند'];

    public static function currentJalaliMonth(int $offset=0,?string $baseIso=null): array
    {
        $d=new DateTimeImmutable($baseIso?:sokna_today());
        $j=Jalali::toJalali((int)$d->format('Y'),(int)$d->format('n'),(int)$d->format('j'));
        $serial=((int)$j['jy']*12)+((int)$j['jm']-1)+$offset;$jy=intdiv($serial,12);$jm=($serial%12)+1;
        [$start,$end]=Jalali::monthRange($jy,$jm);
        return ['jy'=>$jy,'jm'=>$jm,'key'=>sprintf('%04d-%02d',$jy,$jm),'label'=>(self::MONTH_NAMES[$jm]??('ماه '.$jm)).' '.fa_digits($jy),'start'=>$start,'end'=>$end];
    }

    public static function monthFromRange(string $from,string $end): ?array
    {
        try{$d=new DateTimeImmutable($from);}catch(Throwable $e){return null;}$j=Jalali::toJalali((int)$d->format('Y'),(int)$d->format('n'),(int)$d->format('j'));$m=self::monthByJalali((int)$j['jy'],(int)$j['jm']);return $m['start']===$from&&$m['end']===$end?$m:null;
    }

    public static function monthByJalali(int $jy,int $jm): array
    {
        $serial=($jy*12)+($jm-1);$jy=intdiv($serial,12);$jm=($serial%12)+1;if($jm<1){$jm+=12;$jy--;}
        [$start,$end]=Jalali::monthRange($jy,$jm);return ['jy'=>$jy,'jm'=>$jm,'key'=>sprintf('%04d-%02d',$jy,$jm),'label'=>(self::MONTH_NAMES[$jm]??('ماه '.$jm)).' '.fa_digits($jy),'start'=>$start,'end'=>$end];
    }

    public static function occupancy(string $from,string $end): array
    {
        if($end<=$from)return self::emptyOccupancy($from,$end);
        $rooms=get_rooms(false);$roomMap=[];$days=[];
        for($d=new DateTimeImmutable($from);$d<new DateTimeImmutable($end);$d=$d->modify('+1 day'))$days[]=$d->format('Y-m-d');
        foreach($rooms as $room){$id=(int)$room['id'];$roomMap[$id]=['id'=>$id,'name'=>(string)$room['name'],'sellable'=>0,'blocked'=>0,'booked'=>0,'hold'=>0,'physical'=>0];}
        $blocked=[];$st=db()->prepare('SELECT room_id,start_date,end_date FROM '.table('room_blocks').' WHERE start_date<? AND end_date>?');$st->execute([$end,$from]);
        foreach($st->fetchAll() as $row){$rid=(int)$row['room_id'];$a=max($from,(string)$row['start_date']);$b=min($end,(string)$row['end_date']);for($d=new DateTimeImmutable($a);$d<new DateTimeImmutable($b);$d=$d->modify('+1 day'))$blocked[$rid][$d->format('Y-m-d')]=true;}
        $occupied=[];$held=[];$st=db()->prepare("SELECT rr.room_id,rr.check_in,rr.check_out,r.status FROM ".table('reservation_rooms')." rr JOIN ".table('reservations')." r ON r.id=rr.reservation_id WHERE r.status IN ('confirmed','hold') AND rr.check_in<? AND rr.check_out>?");$st->execute([$end,$from]);
        foreach($st->fetchAll() as $row){$rid=(int)$row['room_id'];$a=max($from,(string)$row['check_in']);$b=min($end,(string)$row['check_out']);for($d=new DateTimeImmutable($a);$d<new DateTimeImmutable($b);$d=$d->modify('+1 day')){if((string)$row['status']==='confirmed')$occupied[$rid][$d->format('Y-m-d')]=true;else $held[$rid][$d->format('Y-m-d')]=true;}}
        $unknownLifecycle=0;
        foreach($rooms as $room){$rid=(int)$room['id'];foreach($days as $iso){$operational=self::roomOperationalForReport($room,$iso);if($operational===null){$unknownLifecycle++;continue;}if(!$operational)continue;$roomMap[$rid]['physical']++;if(!empty($blocked[$rid][$iso])){$roomMap[$rid]['blocked']++;continue;}$roomMap[$rid]['sellable']++;if(!empty($occupied[$rid][$iso]))$roomMap[$rid]['booked']++;elseif(!empty($held[$rid][$iso]))$roomMap[$rid]['hold']++;}}
        $total=['sellable'=>0,'blocked'=>0,'booked'=>0,'hold'=>0,'physical'=>0];foreach($roomMap as $r)foreach(array_keys($total) as $k)$total[$k]+=(int)$r[$k];
        $total['occupancy_pct']=self::pct($total['booked'],$total['sellable']);$total['hold_pct']=self::pct($total['hold'],$total['sellable']);$total['engaged_pct']=self::pct($total['booked']+$total['hold'],$total['sellable']);$total['available_room_nights']=max(0,$total['sellable']-$total['booked']-$total['hold']);
        foreach($roomMap as $roomId=>$room){
            $roomMap[$roomId]['occupancy_pct']=self::pct((int)$room['booked'],(int)$room['sellable']);
            $roomMap[$roomId]['hold_pct']=self::pct((int)$room['hold'],(int)$room['sellable']);
        }
        // Do not pollute a selected historical period with rooms that had no lifecycle or activity in it.
        $roomMap=array_filter($roomMap,static fn(array $r):bool=>(int)$r['physical']+(int)$r['booked']+(int)$r['hold']+(int)$r['blocked']>0);
        uasort($roomMap,static fn(array $a,array $b)=>($b['occupancy_pct']<=>$a['occupancy_pct'])?:strcmp($a['name'],$b['name']));
        return ['from'=>$from,'end'=>$end,'total'=>$total,'rooms'=>array_values($roomMap),'unknown_lifecycle_days'=>$unknownLifecycle];
    }

    public static function monthlySeries(int $pastMonths=5,int $futureMonths=3,?string $baseIso=null): array
    {
        $series=[];for($offset=-$pastMonths;$offset<=$futureMonths;$offset++){$m=self::currentJalaliMonth($offset,$baseIso);$o=self::occupancy($m['start'],$m['end']);$series[]=$m+['offset'=>$offset,'occupancy'=>$o['total'],'pace'=>((string)$m['start']>sokna_today())?self::paceComparison($m):null];}return $series;
    }

    public static function averageStayNights(string $from,string $end): float
    {
        $sql='SELECT COALESCE(AVG(DATEDIFF(x.check_out,x.check_in)),0) FROM (SELECT rr.reservation_id,MIN(rr.check_in) check_in,MAX(rr.check_out) check_out FROM '.table('reservation_rooms').' rr JOIN '.table('reservations')." r ON r.id=rr.reservation_id WHERE r.status='confirmed' AND rr.check_in<? AND rr.check_out>? GROUP BY rr.reservation_id) x";
        $st=db()->prepare($sql);$st->execute([$end,$from]);return (float)$st->fetchColumn();
    }

    public static function lodgingPerformance(string $from,string $end): array
    {
        $sql="SELECT r.id reservation_id,r.lodging_amount,r.final_adjustment,r.discount_amount,rr.id room_row_id,rr.room_id,rr.room_name_snapshot,rr.check_in,rr.check_out,rr.guests_count,rr.subtotal FROM ".table('reservations')." r JOIN ".table('reservation_rooms')." rr ON rr.reservation_id=r.id WHERE r.status='confirmed' AND EXISTS (SELECT 1 FROM ".table('reservation_rooms')." x WHERE x.reservation_id=r.id AND x.check_in<? AND x.check_out>?) ORDER BY r.id,rr.id";
        $st=db()->prepare($sql);$st->execute([$end,$from]);$rows=$st->fetchAll();$groups=[];
        foreach($rows as $row)$groups[(int)$row['reservation_id']][]=$row;
        $revenue=0;$roomNights=0;$guestNights=0;$byRoom=[];
        foreach($groups as $group){
            $first=$group[0];
            $base=max(0,checked_money_add(parse_money_amount($first['lodging_amount']),parse_signed_money_amount($first['final_adjustment'])));
            $discount=min($base,parse_money_amount($first['discount_amount']));
            $net=checked_money_subtract($base,$discount);
            $allocationInputs=[];
            foreach($group as $row){
                $nights=max(1,(int)(new DateTimeImmutable((string)$row['check_in']))->diff(new DateTimeImmutable((string)$row['check_out']))->days);
                $allocationInputs[]=['subtotal'=>parse_money_amount($row['subtotal']),'nights'=>$nights];
            }
            // Match the committed reservation allocation rule exactly, including its residual on the final room.
            $allocationShares=reservation_effective_allocation($allocationInputs,$net);
            foreach($group as $i=>$row){
                $a=max($from,(string)$row['check_in']);$b=min($end,(string)$row['check_out']);if($b<=$a)continue;
                $stayStart=new DateTimeImmutable((string)$row['check_in']);
                $selected=(int)(new DateTimeImmutable($a))->diff(new DateTimeImmutable($b))->days;
                $startOffset=(int)$stayStart->diff(new DateTimeImmutable($a))->days;
                $totalNights=$allocationInputs[$i]['nights'];
                $selectedRevenue=checked_money_night_slice((int)$allocationShares[$i]['effective_subtotal'],$totalNights,$startOffset,$selected);
                $revenue=checked_money_add($revenue,$selectedRevenue);
                $roomNights+=$selected;$guestNights+=$selected*max(1,(int)$row['guests_count']);
                $rid=(int)$row['room_id'];
                if(!isset($byRoom[$rid]))$byRoom[$rid]=['room_id'=>$rid,'name'=>(string)$row['room_name_snapshot'],'revenue'=>0,'room_nights'=>0,'adr'=>0];
                $byRoom[$rid]['revenue']=checked_money_add($byRoom[$rid]['revenue'],$selectedRevenue);
                $byRoom[$rid]['room_nights']+=$selected;
            }
        }
        foreach($byRoom as &$room)$room['adr']=$room['room_nights']>0?checked_money_average_rounded($room['revenue'],$room['room_nights']):0;
        unset($room);
        return ['revenue'=>$revenue,'room_nights'=>$roomNights,'guest_nights'=>$guestNights,'adr'=>$roomNights>0?checked_money_average_rounded($revenue,$roomNights):0,'rooms'=>$byRoom];
    }

    public static function cancellations(string $from,string $end): array
    {
        $st=db()->prepare("SELECT r.id,r.cancellation_reason,rr.check_in,rr.check_out FROM ".table('reservations')." r JOIN ".table('reservation_rooms')." rr ON rr.reservation_id=r.id WHERE r.status='cancelled' AND rr.check_in<? AND rr.check_out>?");$st->execute([$end,$from]);$seen=[];$reasons=[];$released=0;$noShow=[];foreach($st->fetchAll() as $row){$id=(int)$row['id'];$reason=(string)($row['cancellation_reason']??'other');$seen[$id]=true;$reasons[$reason][$id]=true;if($reason==='guest_no_show')$noShow[$id]=true;$a=max($from,(string)$row['check_in']);$b=min($end,(string)$row['check_out']);if($b>$a)$released+=(int)(new DateTimeImmutable($a))->diff(new DateTimeImmutable($b))->days;}$reasonCounts=[];foreach($reasons as $key=>$ids)$reasonCounts[$key]=count($ids);return ['count'=>count($seen),'no_show'=>count($noShow),'released_room_nights'=>$released,'reasons'=>$reasonCounts];
    }

    public static function averageLeadTimeDays(string $from,string $end): float
    {
        $sql="SELECT COALESCE(AVG(GREATEST(0,DATEDIFF(x.check_in,DATE(x.created_at)))),0) FROM (SELECT r.id,r.created_at,MIN(rr.check_in) check_in FROM ".table('reservations')." r JOIN ".table('reservation_rooms')." rr ON rr.reservation_id=r.id WHERE r.status='confirmed' AND rr.check_in<? AND rr.check_out>? GROUP BY r.id,r.created_at) x";$st=db()->prepare($sql);$st->execute([$end,$from]);return round((float)$st->fetchColumn(),1);
    }

    public static function repeatGuestStats(string $from,string $end): array
    {
        $sql="SELECT r.guest_id,MIN(rr.check_in) current_check_in FROM ".table('reservations')." r JOIN ".table('reservation_rooms')." rr ON rr.reservation_id=r.id WHERE r.status='confirmed' AND rr.check_in<? AND rr.check_out>? GROUP BY r.guest_id";$st=db()->prepare($sql);$st->execute([$end,$from]);$rows=$st->fetchAll();$repeat=0;$q=db()->prepare("SELECT 1 FROM ".table('reservations')." r JOIN ".table('reservation_rooms')." rr ON rr.reservation_id=r.id WHERE r.status='confirmed' AND r.guest_id=? AND rr.check_out<=? LIMIT 1");foreach($rows as $row){$q->execute([(int)$row['guest_id'],(string)$row['current_check_in']]);if($q->fetchColumn())$repeat++;}return ['unique_guests'=>count($rows),'repeat_guests'=>$repeat,'repeat_pct'=>self::pct($repeat,count($rows))];
    }

    public static function weekdayOccupancy(string $from,string $end): array
    {
        $names=[6=>'شنبه',0=>'یکشنبه',1=>'دوشنبه',2=>'سه‌شنبه',3=>'چهارشنبه',4=>'پنجشنبه',5=>'جمعه'];$order=[6,0,1,2,3,4,5];$data=[];foreach($order as $w)$data[$w]=['label'=>$names[$w],'booked'=>0,'sellable'=>0,'occupancy_pct'=>0.0];for($d=new DateTimeImmutable($from);$d<new DateTimeImmutable($end);$d=$d->modify('+1 day')){$iso=$d->format('Y-m-d');$o=self::occupancy($iso,$d->modify('+1 day')->format('Y-m-d'));$w=(int)$d->format('w');$data[$w]['booked']+=(int)$o['total']['booked'];$data[$w]['sellable']+=(int)$o['total']['sellable'];}foreach($data as &$row)$row['occupancy_pct']=self::pct((int)$row['booked'],(int)$row['sellable']);unset($row);return array_values($data);
    }

    public static function services(string $from,string $end): array
    {
        $st=db()->prepare("SELECT rs.name_snapshot,rs.people_count,rs.total,COALESCE(rs.service_date,DATE(rs.created_at)) report_date FROM ".table('reservation_services')." rs WHERE rs.status<>'cancelled' AND COALESCE(rs.service_date,DATE(rs.created_at))>=? AND COALESCE(rs.service_date,DATE(rs.created_at))<?");$st->execute([$from,$end]);$rows=$st->fetchAll();$count=count($rows);$people=0;$revenue=0;$names=[];foreach($rows as $r){$people+=(int)$r['people_count'];$revenue=checked_money_add($revenue,parse_money_amount($r['total']));$name=(string)$r['name_snapshot'];$names[$name]=($names[$name]??0)+1;}arsort($names);return ['count'=>$count,'people'=>$people,'revenue'=>$revenue,'top_name'=>$names?(string)array_key_first($names):'—','top_count'=>$names?(int)reset($names):0];
    }

    public static function capturePaceSnapshotSafe(): void
    {
        try{self::capturePaceSnapshot();}catch(Throwable $e){log_exception($e,'report_pace_snapshot');}
    }

    public static function capturePaceSnapshot(): void
    {
        $today=sokna_today();$dir=dirname(__DIR__).'/storage/reporting';if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('report_snapshot_storage_unavailable');$path=$dir.'/pace-'.$today.'.json';if(is_file($path))return;
        $months=[];for($i=0;$i<=self::SNAPSHOT_MONTHS;$i++){$m=self::currentJalaliMonth($i);$o=self::occupancy($m['start'],$m['end']);$months[$m['key']]=['month_start'=>$m['start'],'month_end'=>$m['end'],'sellable'=>(int)$o['total']['sellable'],'booked'=>(int)$o['total']['booked'],'hold'=>(int)$o['total']['hold'],'blocked'=>(int)$o['total']['blocked']];}
        $payload=['format'=>'sokna-report-pace-v2','snapshot_date'=>$today,'created_at'=>date(DATE_ATOM),'months'=>$months];$json=json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$tmp=$path.'.tmp-'.bin2hex(random_bytes(3));if(file_put_contents($tmp,$json,LOCK_EX)===false)throw new RuntimeException('report_snapshot_write_failed');if(!@rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('report_snapshot_commit_failed');}
    }

    public static function paceComparison(array $month): array
    {
        $todayIso=sokna_today();if((string)($month['start']??'')<=$todayIso)return ['available'=>false,'reason'=>'not_future'];$today=new DateTimeImmutable($todayIso);$jToday=Jalali::toJalali((int)$today->format('Y'),(int)$today->format('n'),(int)$today->format('j'));$targetJy=(int)$jToday['jy']-1;$targetJm=(int)$jToday['jm'];$targetJd=min((int)$jToday['jd'],Jalali::monthLength($targetJy,$targetJm));$g=Jalali::toGregorian($targetJy,$targetJm,$targetJd);$target=sprintf('%04d-%02d-%02d',$g['gy'],$g['gm'],$g['gd']);$prevKey=sprintf('%04d-%02d',(int)$month['jy']-1,(int)$month['jm']);$dir=dirname(__DIR__).'/storage/reporting';
        for($i=0;$i<=14;$i++){$date=(new DateTimeImmutable($target))->modify('-'.$i.' days')->format('Y-m-d');$path=$dir.'/pace-'.$date.'.json';if(!is_file($path))continue;$p=json_decode((string)@file_get_contents($path),true);$snap=is_array($p)?($p['months'][$prevKey]??null):null;if(!is_array($snap))continue;return ['available'=>true,'target_date'=>$target,'snapshot_at'=>(string)($p['created_at']??$date),'previous_pct'=>self::pct((int)($snap['booked']??0),(int)($snap['sellable']??0)),'previous_booked'=>(int)($snap['booked']??0),'previous_sellable'=>(int)($snap['sellable']??0)];}
        // Legacy fallback for installations that already collected v1 snapshots in the audit log.
        try{$st=db()->prepare('SELECT payload_json,created_at FROM '.table('audit_logs').' WHERE action=? AND entity_type=? AND created_at<=? AND created_at>=? ORDER BY created_at DESC,id DESC LIMIT 40');$st->execute([self::SNAPSHOT_ACTION,'reporting',$target.' 23:59:59',(new DateTimeImmutable($target))->modify('-14 days')->format('Y-m-d').' 00:00:00']);foreach($st->fetchAll() as $row){$p=json_decode((string)$row['payload_json'],true);$snap=$p['months'][$prevKey]??null;if(!is_array($snap))continue;return ['available'=>true,'target_date'=>$target,'snapshot_at'=>(string)$row['created_at'],'previous_pct'=>self::pct((int)($snap['booked']??0),(int)($snap['sellable']??0)),'previous_booked'=>(int)($snap['booked']??0),'previous_sellable'=>(int)($snap['sellable']??0)];}}catch(Throwable $e){}
        return ['available'=>false,'reason'=>'no_snapshot','target_date'=>$target];
    }

    private static function roomOperationalForReport(array $room,string $iso): ?bool
    {
        $from=trim((string)($room['available_from']??''));$retired=trim((string)($room['retired_from']??''));if($from!==''&&$iso<$from)return false;if($retired!==''&&$iso>=$retired)return false;if((int)($room['active']??0)===1)return true;if($retired!=='')return true;return null;
    }
    private static function pct(int $part,int $whole): float{return $whole>0?round(($part/$whole)*100,1):0.0;}
    private static function emptyOccupancy(string $from,string $end): array{return ['from'=>$from,'end'=>$end,'total'=>['sellable'=>0,'blocked'=>0,'booked'=>0,'hold'=>0,'physical'=>0,'occupancy_pct'=>0.0,'hold_pct'=>0.0,'engaged_pct'=>0.0,'available_room_nights'=>0],'rooms'=>[],'unknown_lifecycle_days'=>0];}
}
