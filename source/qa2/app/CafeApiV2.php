<?php
declare(strict_types=1);

final class CafeApiException extends RuntimeException
{
    public function __construct(
        public readonly string $apiCode,
        string $message,
        public readonly int $httpStatus = 400
    ) {
        parent::__construct($message);
    }
}

final class CafeApiV2
{
    public const VERSION = '2.1';
    public const CURRENCY = 'TOMAN';

    public static function normalizeText(string $value): string
    {
        return normalize_name_key($value);
    }

    public static function normalizeReservationCode(string $value): string
    {
        return strtoupper(trim(Jalali::toEnglishDigits($value)));
    }

    public static function maskMobile(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') return '';
        $normalized = normalize_phone($phone) ?? '';
        $local = local_phone($normalized);
        if (preg_match('/^09\d{9}$/', $local)) {
            return substr($local, 0, 4) . '***' . substr($local, -4);
        }
        $digits = preg_replace('/\D+/', '', $normalized) ?? '';
        if (strlen($digits) < 7) return str_repeat('*', max(0, strlen($digits) - 2)) . substr($digits, -2);
        return substr($digits, 0, 3) . '***' . substr($digits, -4);
    }

    /** @return array{iso:string,local:string} */
    public static function parseIsoDateTime(mixed $value, string $fieldLabel): array
    {
        if (!is_string($value) || trim($value) === '' || strlen($value) > 50) {
            throw new CafeApiException('invalid_request', $fieldLabel . ' معتبر نیست.', 400);
        }
        $value = trim($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
            throw new CafeApiException('invalid_request', $fieldLabel . ' باید ISO-8601 و دارای منطقه زمانی باشد.', 400);
        }
        try {
            $date = new DateTimeImmutable($value);
        } catch (Throwable) {
            throw new CafeApiException('invalid_request', $fieldLabel . ' معتبر نیست.', 400);
        }
        $timezone = new DateTimeZone(date_default_timezone_get());
        return ['iso'=>$date->format(DATE_ATOM), 'local'=>$date->setTimezone($timezone)->format('Y-m-d H:i:s')];
    }


    private static function textLength(string $value): int
    {
        if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($chars) ? count($chars) : strlen($value);
    }

    private static function rejectUnknownKeys(array $value, array $allowed, string $label): void
    {
        $unknown = array_values(array_diff(array_keys($value), $allowed));
        if ($unknown) throw new CafeApiException('invalid_request', $label . ' شامل فیلد پشتیبانی‌نشده است: ' . implode(', ', $unknown), 400);
    }

    private static function intMoney(mixed $value, string $label, bool $positive = false): int
    {
        if (!is_int($value) || $value < 0 || ($positive && $value < 1)) {
            throw new CafeApiException('invalid_request', $label . ' باید عدد صحیح و برحسب تومان باشد.', 400);
        }
        return $value;
    }

    private static function optionalClientPayloadHash(array $payload, array $core): ?string
    {
        if (!array_key_exists('payload_hash', $payload) || $payload['payload_hash'] === null) return null;
        if (!is_string($payload['payload_hash'])) {
            throw new CafeApiException('invalid_request', 'payload_hash معتبر نیست.', 400);
        }
        if (trim($payload['payload_hash']) === '') return null;
        $provided = strtolower(trim($payload['payload_hash']));
        if (!preg_match('/^[a-f0-9]{64}$/', $provided)) {
            throw new CafeApiException('invalid_request', 'payload_hash معتبر نیست.', 400);
        }
        ksort($core);
        $json = json_encode($core, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
        $expected = hash('sha256', $json);
        if (!hash_equals($expected, $provided)) {
            throw new CafeApiException('payload_hash_mismatch', 'اثر انگشت Payload با محتوای فاکتور تطابق ندارد.', 422);
        }
        return $provided;
    }

    private static function taxAmount(int $taxableAmount, int $rateBps): int
    {
        if ($taxableAmount <= 0 || $rateBps <= 0) return 0;
        $rateBps = min(10000, $rateBps);
        $whole = intdiv($taxableAmount, 10000) * $rateBps;
        $remainder = $taxableAmount % 10000;
        return $whole + intdiv(($remainder * $rateBps) + 5000, 10000);
    }

    /**
     * Accepts the legacy House invoice v1 plus the current Café settlement snapshots v2/v3.
     * API 2.1 is additive: legacy v1 clients remain valid.
     *
     * @return array{
     *   external_order_id:string,reservation_code:string,amount:int,currency:string,
     *   invoice:array<string,mixed>,ordered_at_local:string,invoice_json:string,payload_hash:string,
     *   requested_at_iso:?string,requested_at_local:?string,client_payload_hash:?string
     * }
     */
    public static function validateChargePayload(array $payload): array
    {
        self::rejectUnknownKeys($payload, ['external_order_id','reservation_code','amount','currency','invoice','payload_hash','requested_at'], 'درخواست charge');
        $externalOrderId = trim((string)($payload['external_order_id'] ?? ''));
        $reservationCode = self::normalizeReservationCode((string)($payload['reservation_code'] ?? ''));
        $amount = self::intMoney($payload['amount'] ?? null, 'مبلغ نهایی', true);
        $currency = strtoupper(trim((string)($payload['currency'] ?? '')));
        $invoiceRaw = $payload['invoice'] ?? null;

        if ($externalOrderId === '' || strlen($externalOrderId) > 120 || !preg_match('/^[A-Za-z0-9._:-]+$/', $externalOrderId)) {
            throw new CafeApiException('invalid_request', 'external_order_id معتبر نیست.', 400);
        }
        if ($reservationCode === '' || strlen($reservationCode) > 40) {
            throw new CafeApiException('invalid_request', 'کد رزرو معتبر نیست.', 400);
        }
        if ($currency !== self::CURRENCY) {
            throw new CafeApiException('unsupported_currency', 'واحد پول API فقط TOMAN است.', 422);
        }
        if (!is_array($invoiceRaw) || array_is_list($invoiceRaw)) {
            throw new CafeApiException('invalid_request', 'Snapshot فاکتور معتبر نیست.', 400);
        }

        $version = $invoiceRaw['version'] ?? null;
        if (!is_int($version) || !in_array($version, [1,2,3], true)) {
            throw new CafeApiException('unsupported_invoice_version', 'نسخه Snapshot فاکتور پشتیبانی نمی‌شود.', 422);
        }
        $commonInvoiceKeys = ['version','number','issued_at','table_name','subtotal','discount','total','items'];
        $v2InvoiceKeys = ['account_subtotal','account_discount','account_total','paid_before','complimentary','remaining_after'];
        $v3InvoiceKeys = ['account_net','account_taxable','account_tax','paid_tax_before','net','taxable','tax','remaining_tax_after'];
        $allowedInvoiceKeys = $commonInvoiceKeys;
        if ($version >= 2) $allowedInvoiceKeys = array_merge($allowedInvoiceKeys, $v2InvoiceKeys);
        if ($version >= 3) $allowedInvoiceKeys = array_merge($allowedInvoiceKeys, $v3InvoiceKeys);
        self::rejectUnknownKeys($invoiceRaw, $allowedInvoiceKeys, 'Snapshot فاکتور');

        $number = trim((string)($invoiceRaw['number'] ?? ''));
        $tableName = trim((string)($invoiceRaw['table_name'] ?? ''));
        if ($number === '' || self::textLength($number) > 80) {
            throw new CafeApiException('invalid_request', 'شماره فاکتور معتبر نیست.', 400);
        }
        if ($tableName === '' || self::textLength($tableName) > 120) {
            throw new CafeApiException('invalid_request', 'نام میز معتبر نیست.', 400);
        }
        $issuedAt = self::parseIsoDateTime($invoiceRaw['issued_at'] ?? null, 'زمان صدور فاکتور');
        $requestedAt = null;
        if (array_key_exists('requested_at', $payload) && $payload['requested_at'] !== null) {
            if (!is_string($payload['requested_at'])) {
                throw new CafeApiException('invalid_request', 'زمان درخواست ثبت هزینه معتبر نیست.', 400);
            }
            if (trim($payload['requested_at']) !== '') {
                $requestedAt = self::parseIsoDateTime($payload['requested_at'], 'زمان درخواست ثبت هزینه');
            }
        }
        $subtotal = self::intMoney($invoiceRaw['subtotal'] ?? null, 'جمع اقلام');
        $discount = self::intMoney($invoiceRaw['discount'] ?? null, 'تخفیف');
        $total = self::intMoney($invoiceRaw['total'] ?? null, 'جمع نهایی', true);
        $rawItems = $invoiceRaw['items'] ?? null;
        if (!is_array($rawItems) || !array_is_list($rawItems) || count($rawItems) < 1 || count($rawItems) > 200) {
            throw new CafeApiException('invalid_request', 'فهرست اقلام فاکتور معتبر نیست.', 400);
        }

        $items = [];
        $sumGross = $sumDiscount = $sumNet = $sumTaxable = $sumTax = $sumFinal = 0;
        foreach ($rawItems as $index => $rawItem) {
            if (!is_array($rawItem) || array_is_list($rawItem)) {
                throw new CafeApiException('invalid_request', 'ساختار یکی از اقلام فاکتور معتبر نیست.', 400);
            }
            $allowedItemKeys = ['name','quantity','unit_price','line_total','note'];
            if ($version >= 2) $allowedItemKeys = array_merge($allowedItemKeys, ['order_item_id','line_discount','line_net']);
            if ($version >= 3) $allowedItemKeys = array_merge($allowedItemKeys, ['taxable_amount','tax_rate_bps','tax_amount','line_final']);
            self::rejectUnknownKeys($rawItem, $allowedItemKeys, 'قلم فاکتور');

            $name = trim((string)($rawItem['name'] ?? ''));
            $quantity = $rawItem['quantity'] ?? null;
            $unitPrice = $rawItem['unit_price'] ?? null;
            $lineTotal = $rawItem['line_total'] ?? null;
            $note = $rawItem['note'] ?? null;
            if ($name === '' || self::textLength($name) > 180 || !is_int($quantity) || $quantity < 1 || $quantity > 1000) {
                throw new CafeApiException('invalid_request', 'نام یا تعداد یکی از اقلام معتبر نیست.', 400);
            }
            $unitPrice = self::intMoney($unitPrice, 'قیمت واحد');
            $lineTotal = self::intMoney($lineTotal, 'مبلغ ردیف');
            if ($unitPrice > intdiv(PHP_INT_MAX, $quantity) || $lineTotal !== $unitPrice * $quantity) {
                throw new CafeApiException('invoice_total_mismatch', 'مبلغ یکی از ردیف‌ها با تعداد و قیمت واحد برابر نیست.', 422);
            }
            if ($note !== null) {
                if (!is_string($note) || self::textLength($note) > 500) {
                    throw new CafeApiException('invalid_request', 'یادداشت یکی از اقلام معتبر نیست.', 400);
                }
                $note = trim($note);
                if ($note === '') $note = null;
            }

            $item = [
                'name'=>$name,'quantity'=>$quantity,'unit_price'=>$unitPrice,'line_total'=>$lineTotal,'note'=>$note,'sort_order'=>$index,
            ];
            $lineDiscount = 0;
            $lineNet = $lineTotal;
            $lineTaxable = 0;
            $lineTax = 0;
            $lineFinal = $lineNet;
            if ($version >= 2) {
                $orderItemId = $rawItem['order_item_id'] ?? null;
                if (!is_int($orderItemId) || $orderItemId < 1) throw new CafeApiException('invalid_request', 'شناسه قلم فاکتور معتبر نیست.', 400);
                $lineDiscount = self::intMoney($rawItem['line_discount'] ?? null, 'تخفیف ردیف');
                $lineNet = self::intMoney($rawItem['line_net'] ?? null, 'خالص ردیف');
                if ($lineDiscount > $lineTotal || $lineTotal - $lineDiscount !== $lineNet) {
                    throw new CafeApiException('invoice_total_mismatch', 'خالص یکی از ردیف‌ها با مبلغ و تخفیف آن تطابق ندارد.', 422);
                }
                $item += ['order_item_id'=>$orderItemId,'line_discount'=>$lineDiscount,'line_net'=>$lineNet];
            }
            if ($version >= 3) {
                $lineTaxable = self::intMoney($rawItem['taxable_amount'] ?? null, 'مبلغ مشمول مالیات ردیف');
                $rate = $rawItem['tax_rate_bps'] ?? null;
                if (!is_int($rate) || $rate < 0 || $rate > 10000) throw new CafeApiException('invalid_request', 'نرخ مالیات یکی از اقلام معتبر نیست.', 400);
                $lineTax = self::intMoney($rawItem['tax_amount'] ?? null, 'مالیات ردیف');
                $lineFinal = self::intMoney($rawItem['line_final'] ?? null, 'جمع نهایی ردیف');
                if ($lineTaxable > $lineNet || $lineTax !== self::taxAmount($lineTaxable, $rate) || $lineFinal !== $lineNet + $lineTax) {
                    throw new CafeApiException('invoice_total_mismatch', 'مالیات یا جمع نهایی یکی از ردیف‌ها معتبر نیست.', 422);
                }
                $item += ['taxable_amount'=>$lineTaxable,'tax_rate_bps'=>$rate,'tax_amount'=>$lineTax,'line_final'=>$lineFinal];
            }
            $items[] = $item;
            foreach ([[$lineTotal,'gross'],[$lineDiscount,'discount'],[$lineNet,'net'],[$lineTaxable,'taxable'],[$lineTax,'tax'],[$lineFinal,'final']] as [$value,$kind]) {
                if ($value < 0) throw new CafeApiException('invalid_request', 'مبلغ فاکتور معتبر نیست.', 400);
                if ($kind === 'gross') $sumGross += $value;
                elseif ($kind === 'discount') $sumDiscount += $value;
                elseif ($kind === 'net') $sumNet += $value;
                elseif ($kind === 'taxable') $sumTaxable += $value;
                elseif ($kind === 'tax') $sumTax += $value;
                else $sumFinal += $value;
            }
        }

        if ($sumGross !== $subtotal || ($version >= 2 && $sumDiscount !== $discount)) {
            throw new CafeApiException('invoice_total_mismatch', 'جمع اقلام یا تخفیف فاکتور با ردیف‌ها تطابق ندارد.', 422);
        }
        if ($version === 1) {
            if ($discount > $subtotal || $subtotal - $discount !== $total || $amount !== $total) {
                throw new CafeApiException('invoice_total_mismatch', 'مبلغ فاکتور، تخفیف یا جمع نهایی با amount تطابق ندارد.', 422);
            }
        } elseif ($version === 2) {
            if ($sumNet !== $total || $subtotal - $discount !== $total || $amount !== $total) {
                throw new CafeApiException('invoice_total_mismatch', 'جمع خالص فاکتور با amount تطابق ندارد.', 422);
            }
        } else {
            $net = self::intMoney($invoiceRaw['net'] ?? null, 'خالص فاکتور');
            $taxable = self::intMoney($invoiceRaw['taxable'] ?? null, 'جمع مشمول مالیات');
            $tax = self::intMoney($invoiceRaw['tax'] ?? null, 'مالیات فاکتور');
            if ($sumNet !== $net || $sumTaxable !== $taxable || $sumTax !== $tax || $sumFinal !== $total || $subtotal - $discount !== $net || $net + $tax !== $total || $amount !== $total) {
                throw new CafeApiException('invoice_total_mismatch', 'جمع خالص، مالیات یا مبلغ نهایی فاکتور تطابق ندارد.', 422);
            }
        }

        $invoice = [
            'version'=>$version,'number'=>$number,'issued_at'=>$issuedAt['iso'],'table_name'=>$tableName,
        ];
        if ($version >= 2) {
            $accountSubtotal = self::intMoney($invoiceRaw['account_subtotal'] ?? null, 'جمع حساب');
            $accountDiscount = self::intMoney($invoiceRaw['account_discount'] ?? null, 'تخفیف حساب');
            $accountTotal = self::intMoney($invoiceRaw['account_total'] ?? null, 'جمع نهایی حساب');
            $paidBefore = self::intMoney($invoiceRaw['paid_before'] ?? null, 'پرداخت قبلی');
            $remainingAfter = self::intMoney($invoiceRaw['remaining_after'] ?? null, 'مانده پس از پرداخت');
            $complimentary = $invoiceRaw['complimentary'] ?? null;
            if (!is_bool($complimentary)) throw new CafeApiException('invalid_request', 'وضعیت مهمان‌شدن حساب معتبر نیست.', 400);
            if ($accountDiscount > $accountSubtotal || $paidBefore > $accountTotal || $remainingAfter > $accountTotal) {
                throw new CafeApiException('invoice_total_mismatch', 'جمع‌های سطح حساب معتبر نیست.', 422);
            }
            if ($version === 2 && $accountSubtotal - $accountDiscount !== $accountTotal) {
                throw new CafeApiException('invoice_total_mismatch', 'جمع نهایی حساب با تخفیف حساب تطابق ندارد.', 422);
            }
            if ($paidBefore + $total + $remainingAfter !== $accountTotal) {
                throw new CafeApiException('invoice_total_mismatch', 'پرداخت قبلی، این فاکتور و مانده با جمع حساب تطابق ندارند.', 422);
            }
            $invoice += [
                'account_subtotal'=>$accountSubtotal,'account_discount'=>$accountDiscount,'account_total'=>$accountTotal,
                'paid_before'=>$paidBefore,'subtotal'=>$subtotal,'discount'=>$discount,'total'=>$total,
                'complimentary'=>$complimentary,'remaining_after'=>$remainingAfter,
            ];
            if ($version >= 3) {
                $accountNet = self::intMoney($invoiceRaw['account_net'] ?? null, 'خالص حساب');
                $accountTaxable = self::intMoney($invoiceRaw['account_taxable'] ?? null, 'مشمول مالیات حساب');
                $accountTax = self::intMoney($invoiceRaw['account_tax'] ?? null, 'مالیات حساب');
                $paidTaxBefore = self::intMoney($invoiceRaw['paid_tax_before'] ?? null, 'مالیات پرداخت‌شده قبلی');
                $net = self::intMoney($invoiceRaw['net'] ?? null, 'خالص فاکتور');
                $taxable = self::intMoney($invoiceRaw['taxable'] ?? null, 'مشمول مالیات فاکتور');
                $tax = self::intMoney($invoiceRaw['tax'] ?? null, 'مالیات فاکتور');
                $remainingTaxAfter = self::intMoney($invoiceRaw['remaining_tax_after'] ?? null, 'مالیات مانده');
                if ($accountSubtotal - $accountDiscount !== $accountNet || $accountNet + $accountTax !== $accountTotal || $paidTaxBefore + $tax + $remainingTaxAfter !== $accountTax || $accountTaxable > $accountNet) {
                    throw new CafeApiException('invoice_total_mismatch', 'جمع‌های مالیاتی سطح حساب معتبر نیست.', 422);
                }
                $invoice += [
                    'account_net'=>$accountNet,'account_taxable'=>$accountTaxable,'account_tax'=>$accountTax,'paid_tax_before'=>$paidTaxBefore,
                    'net'=>$net,'taxable'=>$taxable,'tax'=>$tax,'remaining_tax_after'=>$remainingTaxAfter,
                ];
            }
        } else {
            $invoice += ['subtotal'=>$subtotal,'discount'=>$discount,'total'=>$total];
        }
        $invoice['items'] = array_map(static function(array $item): array {
            $copy = $item; unset($copy['sort_order']); return $copy;
        }, $items);

        // The Café hashes the exact outbound core payload before transport. Verify against
        // those wire values, then continue with the normalized House representation below.
        $coreForClientHash = [
            'external_order_id'=>$payload['external_order_id'] ?? '',
            'reservation_code'=>$payload['reservation_code'] ?? '',
            'amount'=>$payload['amount'] ?? null,
            'currency'=>$payload['currency'] ?? '',
            'invoice'=>$invoiceRaw,
        ];
        $clientPayloadHash = self::optionalClientPayloadHash($payload, $coreForClientHash);
        $canonical = [
            'external_order_id'=>$externalOrderId,'reservation_code'=>$reservationCode,'amount'=>$amount,'currency'=>self::CURRENCY,'invoice'=>$invoice,
        ];
        $invoiceJson = json_encode($invoice, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $payloadJson = json_encode($canonical, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);

        $validatedInvoice = $invoice;
        $validatedInvoice['items'] = $items;
        return [
            'external_order_id'=>$externalOrderId,'reservation_code'=>$reservationCode,'amount'=>$amount,'currency'=>self::CURRENCY,
            'invoice'=>$validatedInvoice,'ordered_at_local'=>$issuedAt['local'],'invoice_json'=>$invoiceJson,
            'payload_hash'=>$clientPayloadHash ?? hash('sha256', $payloadJson),
            'requested_at_iso'=>$requestedAt['iso'] ?? null,'requested_at_local'=>$requestedAt['local'] ?? null,'client_payload_hash'=>$clientPayloadHash,
        ];
    }

    public static function isSameCharge(array $existing, array $validated): bool
    {
        $existingHash = trim((string)($existing['payload_hash'] ?? ''));
        return (string)($existing['reservation_code'] ?? '') === (string)($validated['reservation_code'] ?? '')
            && (int)($existing['amount'] ?? -1) === (int)($validated['amount'] ?? -2)
            && (string)($existing['currency'] ?? '') === (string)($validated['currency'] ?? '')
            && $existingHash !== ''
            && hash_equals($existingHash, (string)($validated['payload_hash'] ?? ''));
    }

    /** @return array{external_order_id:string,reason:string,requested_at_iso:string,requested_at_local:string} */
    public static function validateVoidPayload(array $payload): array
    {
        self::rejectUnknownKeys($payload, ['external_order_id','reason','requested_at'], 'درخواست void');
        $externalOrderId = trim((string)($payload['external_order_id'] ?? ''));
        $reason = trim((string)($payload['reason'] ?? ''));
        if ($externalOrderId === '' || strlen($externalOrderId) > 120 || !preg_match('/^[A-Za-z0-9._:-]+$/', $externalOrderId)) {
            throw new CafeApiException('invalid_request', 'external_order_id معتبر نیست.', 400);
        }
        if ($reason === '' || self::textLength($reason) > 500) {
            throw new CafeApiException('invalid_request', 'دلیل برگشت هزینه الزامی و حداکثر ۵۰۰ نویسه است.', 400);
        }
        $requestedAt = self::parseIsoDateTime($payload['requested_at'] ?? null, 'زمان درخواست برگشت');
        return [
            'external_order_id'=>$externalOrderId,
            'reason'=>$reason,
            'requested_at_iso'=>$requestedAt['iso'],
            'requested_at_local'=>$requestedAt['local'],
        ];
    }

    public static function chargeBlockReason(string $status, ?string $checkIn, ?string $checkOut, bool $hasActiveAllocation, ?string $today = null): ?string
    {
        if($today===null){
            $today=(new DateTimeImmutable('now',new DateTimeZone('Asia/Tehran')))->format('Y-m-d');
        }
        if($status==='hold')return 'رزرو هنوز قطعی نشده است.';
        if($status==='cancelled')return 'رزرو لغو شده است.';
        if($status!=='confirmed')return 'وضعیت رزرو اجازه ثبت هزینه را نمی‌دهد.';
        if($checkIn===null||$checkOut===null)return 'بازه اقامت رزرو کامل نیست.';
        if($today<$checkIn)return 'تاریخ ورود رزرو هنوز نرسیده است.';
        // روز خروج جزو بازه مجاز ثبت هزینه نیست.
        if($today>=$checkOut)return 'بازه اقامت پایان یافته است.';
        if(!$hasActiveAllocation)return 'رزرو در تاریخ جاری اتاق فعال ندارد.';
        return null;
    }
}
