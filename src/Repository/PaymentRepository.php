<?php

declare(strict_types=1);

namespace DvraMembership\Repository;

use PDO;

final class PaymentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** After payment rows change: member paid_through = MAX(payments.paid_through) or NULL. */
    public function syncMemberPaidThroughFromPayments(int $memberId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE members SET paid_through = (
                SELECT MAX(paid_through) FROM payments WHERE member_id = ?
            ), updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        $stmt->execute([$memberId, $memberId]);
    }

    /** @return array{member_id: int}|null */
    public function findPaymentMeta(int $paymentId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT member_id FROM payments WHERE id = ? LIMIT 1');
        $stmt->execute([$paymentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return ['member_id' => (int) $row['member_id']];
    }

    /**
     * @param array{payment_date: string, paid_through: string, membership_type_id: ?int, notes: ?string, form_number: ?string} $data
     */
    public function insertPayment(int $memberId, array $data): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO payments (member_id, payment_date, paid_through, membership_type_id, notes, form_number, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
            );
            $stmt->execute([
                $memberId,
                $data['payment_date'],
                $data['paid_through'],
                $data['membership_type_id'],
                $data['notes'],
                $data['form_number'],
            ]);
            $this->syncMemberPaidThroughFromPayments($memberId);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param array{payment_date: string, paid_through: string, membership_type_id: ?int, notes: ?string, form_number: ?string} $data
     */
    public function updatePayment(int $paymentId, array $data): ?int
    {
        $meta = $this->findPaymentMeta($paymentId);
        if ($meta === null) {
            return null;
        }
        $memberId = $meta['member_id'];

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE payments SET payment_date = ?, paid_through = ?, membership_type_id = ?, notes = ?, form_number = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $data['payment_date'],
                $data['paid_through'],
                $data['membership_type_id'],
                $data['notes'],
                $data['form_number'],
                $paymentId,
            ]);
            $this->syncMemberPaidThroughFromPayments($memberId);
            $this->pdo->commit();

            return $memberId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return ?int member_id when row existed */
    public function deletePayment(int $paymentId): ?int
    {
        $meta = $this->findPaymentMeta($paymentId);
        if ($meta === null) {
            return null;
        }
        $memberId = $meta['member_id'];

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('DELETE FROM payments WHERE id = ?');
            $stmt->execute([$paymentId]);
            $this->syncMemberPaidThroughFromPayments($memberId);
            $this->pdo->commit();

            return $memberId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
