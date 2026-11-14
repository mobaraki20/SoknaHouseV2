<?php
declare(strict_types=1);

final class Jalali
{
    /** Based on the public-domain/MIT jalaali calendar algorithm. */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        $r = self::jalCal($jy);
        $gy = $r['gy'];
        $march = $r['march'];
        $jdn1f = self::g2d($gy, 3, $march);
        $jdn = $jdn1f + (($jm - 1) * 31) - intdiv($jm, 7) * ($jm - 7) + $jd - 1;
        return self::d2g($jdn);
    }

    public static function toJalali(int $gy, int $gm, int $gd): array
    {
        $jdn = self::g2d($gy, $gm, $gd);
        $gy2 = self::d2g($jdn)['gy'];
        $jy = $gy2 - 621;
        $r = self::jalCal($jy);
        $jdn1f = self::g2d($gy2, 3, $r['march']);
        $k = $jdn - $jdn1f;
        if ($k >= 0) {
            if ($k <= 185) {
                return ['jy' => $jy, 'jm' => 1 + intdiv($k, 31), 'jd' => ($k % 31) + 1];
            }
            $k -= 186;
            return ['jy' => $jy, 'jm' => 7 + intdiv($k, 30), 'jd' => ($k % 30) + 1];
        }
        $jy--;
        $k += 179;
        if ($r['leap'] === 1) {
            $k++;
        }
        return ['jy' => $jy, 'jm' => 7 + intdiv($k, 30), 'jd' => ($k % 30) + 1];
    }

    public static function isLeap(int $jy): bool
    {
        return self::jalCal($jy)['leap'] === 0;
    }

    public static function monthLength(int $jy, int $jm): int
    {
        if ($jm <= 6) return 31;
        if ($jm <= 11) return 30;
        return self::isLeap($jy) ? 30 : 29;
    }

    public static function parse(string $value): ?array
    {
        $value = self::toEnglishDigits(trim($value));
        if (!preg_match('~^(\d{4})[\-/](\d{1,2})[\-/](\d{1,2})$~', $value, $m)) {
            return null;
        }
        $jy = (int)$m[1]; $jm = (int)$m[2]; $jd = (int)$m[3];
        // Keep malformed user-provided years out of jalCal's supported range.
        // Otherwise a valid-looking 4-digit year such as 9999 reaches toIso()
        // and throws instead of returning null like other invalid dates.
        if ($jy < 1 || $jy >= 3178 || $jm < 1 || $jm > 12 || $jd < 1 || $jd > self::monthLength($jy, $jm)) {
            return null;
        }
        return ['jy'=>$jy,'jm'=>$jm,'jd'=>$jd];
    }

    public static function toIso(string $jalali): ?string
    {
        $p = self::parse($jalali);
        if (!$p) return null;
        $g = self::toGregorian($p['jy'], $p['jm'], $p['jd']);
        return sprintf('%04d-%02d-%02d', $g['gy'], $g['gm'], $g['gd']);
    }

    public static function fromIso(string $iso, string $separator = '/'): string
    {
        $dt = new DateTimeImmutable($iso);
        $j = self::toJalali((int)$dt->format('Y'), (int)$dt->format('n'), (int)$dt->format('j'));
        return sprintf('%04d%s%02d%s%02d', $j['jy'], $separator, $j['jm'], $separator, $j['jd']);
    }

    public static function addDays(string $jalali, int $days): ?string
    {
        $iso = self::toIso($jalali);
        if (!$iso) return null;
        $d = (new DateTimeImmutable($iso))->modify(($days >= 0 ? '+' : '') . $days . ' days');
        return self::fromIso($d->format('Y-m-d'));
    }

    public static function diffDays(string $fromJalali, string $toJalali): ?int
    {
        $a = self::toIso($fromJalali); $b = self::toIso($toJalali);
        if (!$a || !$b) return null;
        return (int)(new DateTimeImmutable($a))->diff(new DateTimeImmutable($b))->format('%r%a');
    }

    public static function monthRange(int $jy, int $jm): array
    {
        $start = self::toGregorian($jy, $jm, 1);
        $nextJy = $jm === 12 ? $jy + 1 : $jy;
        $nextJm = $jm === 12 ? 1 : $jm + 1;
        $end = self::toGregorian($nextJy, $nextJm, 1);
        return [
            sprintf('%04d-%02d-%02d', $start['gy'],$start['gm'],$start['gd']),
            sprintf('%04d-%02d-%02d', $end['gy'],$end['gm'],$end['gd']),
        ];
    }

    public static function weekdayName(int $jy, int $jm, int $jd): string
    {
        $g = self::toGregorian($jy,$jm,$jd);
        $w = (int)(new DateTimeImmutable(sprintf('%04d-%02d-%02d',$g['gy'],$g['gm'],$g['gd'])))->format('w');
        return ['یک‌شنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنج‌شنبه','جمعه','شنبه'][$w];
    }

    public static function toEnglishDigits(string $text): string
    {
        return strtr($text, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]);
    }

    private static function div(int $a, int $b): int { return intdiv($a, $b); }
    private static function mod(int $a, int $b): int { return $a - intdiv($a, $b) * $b; }

    private static function jalCal(int $jy): array
    {
        $breaks = [-61,9,38,199,426,686,756,818,1111,1181,1210,1635,2060,2097,2192,2262,2324,2394,2456,3178];
        $bl = count($breaks); $gy = $jy + 621; $leapJ = -14; $jp = $breaks[0];
        if ($jy < $jp || $jy >= $breaks[$bl-1]) throw new InvalidArgumentException('Invalid Jalali year');
        for ($i=1; $i<$bl; $i++) {
            $jm = $breaks[$i]; $jump = $jm - $jp;
            if ($jy < $jm) break;
            $leapJ += self::div($jump,33)*8 + self::div(self::mod($jump,33),4);
            $jp = $jm;
        }
        $n = $jy - $jp;
        $leapJ += self::div($n,33)*8 + self::div(self::mod($n,33)+3,4);
        if (self::mod($jump,33) === 4 && $jump-$n === 4) $leapJ++;
        $leapG = self::div($gy,4) - self::div((self::div($gy,100)+1)*3,4) - 150;
        $march = 20 + $leapJ - $leapG;
        if ($jump-$n < 6) $n = $n - $jump + self::div($jump+4,33)*33;
        $leap = self::mod(self::mod($n+1,33)-1,4);
        if ($leap === -1) $leap = 4;
        return ['leap'=>$leap,'gy'=>$gy,'march'=>$march];
    }

    private static function g2d(int $gy, int $gm, int $gd): int
    {
        $d = self::div(($gy + self::div($gm - 8, 6) + 100100) * 1461, 4)
            + self::div(153 * self::mod($gm + 9, 12) + 2, 5)
            + $gd - 34840408;
        $d = $d - self::div(self::div($gy + 100100 + self::div($gm - 8, 6), 100) * 3, 4) + 752;
        return $d;
    }

    private static function d2g(int $jdn): array
    {
        $j = 4 * $jdn + 139361631;
        $j = $j + self::div(self::div(4*$jdn + 183187720,146097)*3,4)*4 - 3908;
        $i = self::div(self::mod($j,1461),4)*5 + 308;
        $gd = self::div(self::mod($i,153),5)+1;
        $gm = self::mod(self::div($i,153),12)+1;
        $gy = self::div($j,1461)-100100+self::div(8-$gm,6);
        return ['gy'=>$gy,'gm'=>$gm,'gd'=>$gd];
    }
}
