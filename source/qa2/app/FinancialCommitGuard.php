<?php
declare(strict_types=1);

/**
 * Review-only persistent idempotency candidate.
 * MUST be called in the SAME open DB transaction as the financial side effect.
 * Requires schema migration to create the unique financial_commit_keys table.
 */
final class FinancialCommitGuard
{
    public static function claim(PDO $pdo, string $token, string $fingerprint, string $operation, int $reservationId): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)
            || !preg_match('/^[a-f0-9]{64}$/', $fingerprint)
            || !in_array($operation, ['payment','refund','service_add'], true)
            || $reservationId < 1) return 'invalid';
        if (!$pdo->inTransaction()) throw new LogicException('کلید تکرار باید در تراکنش مالی ثبت شود.');
        try {
            $sql = 'INSERT INTO '.table('financial_commit_keys').' (client_token, fingerprint, operation_type, reservation_id) VALUES (?,?,?,?)';
            $pdo->prepare($sql)->execute([$token,$fingerprint,$operation,$reservationId]);
            return 'new';
        } catch (PDOException $e) {
            // Only a duplicate-key violation is a replay candidate. Never mask other DB faults.
            if (($e->errorInfo[0]??$e->getCode()) !== '23000' || (int)($e->errorInfo[1]??0) !== 1062) throw $e;
            // Locking read sees the committed winner, even under REPEATABLE READ.
            $query=$pdo->prepare('SELECT fingerprint,operation_type,reservation_id FROM '.table('financial_commit_keys').' WHERE client_token=? FOR UPDATE');
            $query->execute([$token]);
            $existing=$query->fetch(PDO::FETCH_ASSOC);
            if (!is_array($existing)) throw new RuntimeException('وضعیت ثبت عملیات مالی قطعی نیست؛ دوباره تلاش نکنید.');
            return hash_equals((string)$existing['fingerprint'],$fingerprint)
                && (string)$existing['operation_type']===$operation
                && (int)$existing['reservation_id']===$reservationId ? 'replay' : 'conflict';
        }
    }
}
