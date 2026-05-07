<?php

declare(strict_types=1);

namespace DvraMembership\Repository;

use DateTimeImmutable;
use PDO;

final class ReferenceDataRepository
{
    /** @internal */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array{id: int, name: string|null}> */
    public function listLicenseClasses(): array
    {
        $stmt = $this->pdo->query('SELECT id, name FROM license_classes ORDER BY name ASC');
        if (!$stmt) {
            return [];
        }

        return $this->mapReferenceRows($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array{id: int, name: string|null}> */
    public function listMembershipTypes(): array
    {
        $stmt = $this->pdo->query('SELECT id, name FROM membership_types ORDER BY name ASC');
        if (!$stmt) {
            return [];
        }

        return $this->mapReferenceRows($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{id: int, name: string|null}>
     */
    private function mapReferenceRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'name' => isset($r['name']) ? (string) $r['name'] : null,
            ];
        }

        return $out;
    }

    public function createLicenseClass(string $name): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO license_classes (name) VALUES (?)'
        );
        $stmt->execute([trim($name)]);
    }

    public function updateLicenseClass(int $id, string $name): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE license_classes SET name = ? WHERE id = ?'
        );
        $stmt->execute([trim($name), $id]);
    }

    public function deleteLicenseClass(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM license_classes WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function createMembershipType(string $name): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO membership_types (name) VALUES (?)'
        );
        $stmt->execute([trim($name)]);
    }

    public function updateMembershipType(int $id, string $name): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE membership_types SET name = ? WHERE id = ?'
        );
        $stmt->execute([trim($name), $id]);
    }

    /**
     * True when any payment for this type has payment_date.year &gt;= current year
     * or paid_through.year &gt;= current year (parity with Python membership_type_blocked_by_payments).
     */
    public function membershipTypeBlockedByPayments(int $membershipTypeId): bool
    {
        $y = (int) (new DateTimeImmutable('today'))->format('Y');
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM payments
             WHERE membership_type_id = ?
             AND (
               CAST(strftime(\'%Y\', payment_date) AS INTEGER) >= ?
               OR CAST(strftime(\'%Y\', paid_through) AS INTEGER) >= ?
             )
             LIMIT 1'
        );
        $stmt->execute([$membershipTypeId, $y, $y]);

        return $stmt->fetchColumn() !== false;
    }

    /** @throws \RuntimeException When delete is blocked (payments in current/future years). */
    public function deleteMembershipTypeOrFail(int $id): void
    {
        if ($this->membershipTypeBlockedByPayments($id)) {
            throw new \RuntimeException(
                'Cannot delete membership type referenced by payments in the current or future years.'
            );
        }
        $stmt = $this->pdo->prepare('DELETE FROM membership_types WHERE id = ?');
        $stmt->execute([$id]);
    }
}
