<?php
declare(strict_types=1);

final class LedgerService
{
    public static function summary(int $reservationId): array
    {
        $r=db()->prepare('SELECT lodging_amount,final_adjustment,discount_amount,cancellation_penalty,status FROM '.table('reservations').' WHERE id=? LIMIT 1');
        $r->execute([$reservationId]);$reservation=$r->fetch();
        if(!$reservation)return self::empty();
        $services=self::sum('SELECT COALESCE(SUM(total),0) FROM '.table('reservation_services')." WHERE reservation_id=? AND status<>'cancelled'",[$reservationId]);
        $cafe=self::sum('SELECT COALESCE(SUM(amount),0) FROM '.table('cafe_charges')." WHERE reservation_id=? AND status='posted'",[$reservationId]);
        $payments=self::sum('SELECT COALESCE(SUM(CASE WHEN kind=\'payment\' THEN amount ELSE -amount END),0) FROM '.table('payments').' WHERE reservation_id=? AND voided_at IS NULL',[$reservationId]);
        return self::calculate($reservation,$services,$cafe,$payments);
    }
    public static function calculate(array $reservation,int $services,int $cafe,int $payments): array
    {
        $lodging=max(0,checked_money_add(parse_money_amount($reservation['lodging_amount']??0),parse_signed_money_amount($reservation['final_adjustment']??0)));
        $discount=min($lodging,max(0,parse_money_amount($reservation['discount_amount']??0)));
        $lodgingNet=max(0,$lodging-$discount);
        $cancelled=(string)($reservation['status']??'')==='cancelled';
        $chargeableLodging=$cancelled?0:$lodgingNet;
        $penalty=$cancelled?max(0,parse_money_amount($reservation['cancellation_penalty']??0)):0;
        $gross=checked_money_add(checked_money_add(checked_money_add($chargeableLodging,$services),$cafe),$penalty);
        $balance=checked_money_subtract($gross,$payments);
        return [
            'lodging_before_discount'=>$lodging,
            'discount'=>$discount,
            'lodging'=>$lodgingNet,
            'lodging_chargeable'=>$chargeableLodging,
            'services'=>$services,
            'cafe'=>$cafe,
            'penalty'=>$penalty,
            'total'=>$gross,
            'paid'=>$payments,
            'balance'=>$balance,
            'state'=>$balance>0?'due':($balance<0?'credit':'settled'),
        ];
    }
    public static function empty(): array { return ['lodging_before_discount'=>0,'discount'=>0,'lodging'=>0,'lodging_chargeable'=>0,'services'=>0,'cafe'=>0,'penalty'=>0,'total'=>0,'paid'=>0,'balance'=>0,'state'=>'settled']; }
    private static function sum(string $sql,array $args): int { $s=db()->prepare($sql);$s->execute($args);return parse_signed_money_amount($s->fetchColumn()); }
}
