<?php

declare(strict_types=1);

namespace DvraMembership\Repository;

use DvraMembership\Support\MemberInputNormalizer;
use PDO;
final class MemberRepository
{
    /** @internal */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array{id: int, name: string|null, label: string|null}> */
    public function listLicenseClasses(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, label FROM license_classes ORDER BY name ASC');
        if (!$stmt) {
            return [];
        }
        /** @var list<array{id: mixed, name: mixed, label: mixed}> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'name' => isset($r['name']) ? (string) $r['name'] : null,
                'label' => isset($r['label']) && $r['label'] !== null ? (string) $r['label'] : null,
            ];
        }

        return $out;
    }

    /** @return list<array{id: int, name: string|null, label: string|null}> */
    public function listMembershipTypes(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, label FROM membership_types ORDER BY name ASC');
        if (!$stmt) {
            return [];
        }
        /** @var list<array{id: mixed, name: mixed, label: mixed}> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'name' => isset($r['name']) ? (string) $r['name'] : null,
                'label' => isset($r['label']) && $r['label'] !== null ? (string) $r['label'] : null,
            ];
        }

        return $out;
    }

    /** Row for templates; snake_case keys. */
    public function findMemberById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, last_name, first_name, call_sign, email, phone,
                    address_street, address_city, address_state, address_zip,
                    license_class_id, membership_type_id, arrl_member, key_number, paid_through
             FROM members WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return self::normalizeMemberRow($row);
    }

    /**
     * @param array<mixed> $row
     * @return array<string, mixed>
     */
    private static function normalizeMemberRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'last_name' => (string) $row['last_name'],
            'first_name' => (string) $row['first_name'],
            'call_sign' => isset($row['call_sign']) && $row['call_sign'] !== null ? (string) $row['call_sign'] : null,
            'email' => isset($row['email']) && $row['email'] !== null ? (string) $row['email'] : null,
            'phone' => isset($row['phone']) && $row['phone'] !== null ? (string) $row['phone'] : null,
            'address_street' => isset($row['address_street']) && $row['address_street'] !== null ? (string) $row['address_street'] : null,
            'address_city' => isset($row['address_city']) && $row['address_city'] !== null ? (string) $row['address_city'] : null,
            'address_state' => isset($row['address_state']) && $row['address_state'] !== null ? (string) $row['address_state'] : null,
            'address_zip' => isset($row['address_zip']) && $row['address_zip'] !== null ? (string) $row['address_zip'] : null,
            'license_class_id' => isset($row['license_class_id']) && $row['license_class_id'] !== null ? (int) $row['license_class_id'] : null,
            'membership_type_id' => isset($row['membership_type_id']) && $row['membership_type_id'] !== null ? (int) $row['membership_type_id'] : null,
            'arrl_member' => (bool) ($row['arrl_member'] ?? 0),
            'key_number' => isset($row['key_number']) && $row['key_number'] !== null ? (int) $row['key_number'] : null,
            'paid_through' => isset($row['paid_through']) && $row['paid_through'] !== null ? (string) $row['paid_through'] : null,
        ];
    }

    /** Non-null call sign match (stored uppercase). */
    public function findIdByNonnullCallSign(string $callSign): ?int
    {
        $n = MemberInputNormalizer::normalizeCallSign($callSign);
        if ($n === null) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM members WHERE call_sign = ? LIMIT 1');
        $stmt->execute([$n]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            return null;
        }

        return (int) $id;
    }

    public function existsNameWithoutCallSign(string $lastName, string $firstName, ?int $excludeMemberId): bool
    {
        $sql = 'SELECT 1 FROM members
                WHERE lower(trim(last_name)) = lower(trim(?))
                  AND lower(trim(first_name)) = lower(trim(?))
                  AND call_sign IS NULL
                LIMIT 1';
        $params = [$lastName, $firstName];
        if ($excludeMemberId !== null) {
            $sql = 'SELECT 1 FROM members
                    WHERE lower(trim(last_name)) = lower(trim(?))
                      AND lower(trim(first_name)) = lower(trim(?))
                      AND call_sign IS NULL
                      AND id != ?
                    LIMIT 1';
            $params[] = $excludeMemberId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function findMemberIdByKeyNumber(int $keyNumber, ?int $excludeMemberId): ?int
    {
        $sql = 'SELECT id FROM members WHERE key_number = ? LIMIT 1';
        $params = [$keyNumber];
        if ($excludeMemberId !== null) {
            $sql = 'SELECT id FROM members WHERE key_number = ? AND id != ? LIMIT 1';
            $params[] = $excludeMemberId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /** @throws DuplicateMemberKeyNumber */
    public function enforceUniqueKeyNumber(?int $keyNumber, ?int $excludeMemberId): void
    {
        if ($keyNumber === null) {
            return;
        }
        if ($this->findMemberIdByKeyNumber($keyNumber, $excludeMemberId) !== null) {
            throw new DuplicateMemberKeyNumber('Another member already holds that key number.');
        }
    }

    /**
     * Ensures phone is NXX-NXX-XXXX or null for any code path (forms, spreadsheet/API import) that
     * passes raw strings into insertMember / updateMember.
     *
     * @param array<string, mixed> $data
     */
    private static function coerceMemberRowPhoneForPersist(array &$data): void
    {
        $raw = $data['phone'] ?? null;
        $asString = $raw !== null && $raw !== '' ? (string) $raw : null;
        $data['phone'] = MemberInputNormalizer::normalizePhoneUsTenDigit($asString);
    }

    /** @param array $data Output of MemberInputNormalizer::memberCreateFromForm */
    public function insertMember(array $data): int
    {
        self::coerceMemberRowPhoneForPersist($data);
        $this->enforceUniqueKeyNumber($data['key_number'], null);

        $stmt = $this->pdo->prepare(
            'INSERT INTO members (
                last_name, first_name, call_sign, email, phone,
                address_street, address_city, address_state, address_zip,
                license_class_id, membership_type_id, arrl_member, key_number, paid_through,
                created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            $data['last_name'],
            $data['first_name'],
            $data['call_sign'],
            $data['email'],
            $data['phone'],
            $data['address_street'],
            $data['address_city'],
            $data['address_state'],
            $data['address_zip'],
            $data['license_class_id'],
            $data['membership_type_id'],
            $data['arrl_member'] ? 1 : 0,
            $data['key_number'],
            $data['paid_through'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @throws DuplicateMemberKeyNumber */
    public function updateMember(int $id, array $data): void
    {
        self::coerceMemberRowPhoneForPersist($data);
        $this->enforceUniqueKeyNumber($data['key_number'], $id);

        $stmt = $this->pdo->prepare(
            'UPDATE members SET
                last_name = ?, first_name = ?, call_sign = ?, email = ?, phone = ?,
                address_street = ?, address_city = ?, address_state = ?, address_zip = ?,
                license_class_id = ?, membership_type_id = ?, arrl_member = ?, key_number = ?,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([
            $data['last_name'],
            $data['first_name'],
            $data['call_sign'],
            $data['email'],
            $data['phone'],
            $data['address_street'],
            $data['address_city'],
            $data['address_state'],
            $data['address_zip'],
            $data['license_class_id'],
            $data['membership_type_id'],
            $data['arrl_member'] ? 1 : 0,
            $data['key_number'],
            $id,
        ]);
    }

    public function deleteMemberById(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM members WHERE id = ?');

        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    /**
     * @return list<array{id: int, payment_date: string, paid_through: string, membership_type_id: ?int, form_number: ?string, notes: ?string, membership_type_display: string}>
     */
    public function listPaymentsForMember(int $memberId): array
    {
        $sql = <<<SQL
            SELECT p.id, p.payment_date, p.paid_through, p.membership_type_id, p.form_number, p.notes,
                   mt.name AS mt_name, mt.label AS mt_label
            FROM payments p
            LEFT JOIN membership_types mt ON p.membership_type_id = mt.id
            WHERE p.member_id = ?
            ORDER BY p.payment_date DESC, p.id DESC
            SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$memberId]);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $nm = isset($r['mt_name']) ? (string) $r['mt_name'] : '';
            $lb = isset($r['mt_label']) && $r['mt_label'] !== null ? (string) $r['mt_label'] : '';
            $out[] = [
                'id' => (int) $r['id'],
                'payment_date' => isset($r['payment_date']) ? (string) $r['payment_date'] : '',
                'paid_through' => isset($r['paid_through']) ? (string) $r['paid_through'] : '',
                'membership_type_id' => isset($r['membership_type_id']) && $r['membership_type_id'] !== null
                    ? (int) $r['membership_type_id'] : null,
                'form_number' => isset($r['form_number']) && $r['form_number'] !== null ? (string) $r['form_number'] : null,
                'notes' => isset($r['notes']) && $r['notes'] !== null ? (string) $r['notes'] : null,
                'membership_type_display' => MemberInputNormalizer::referenceLabel($nm !== '' ? $nm : null, $lb !== '' ? $lb : null),
            ];
        }

        return $out;
    }

    /**
     * Set each non-empty members.phone to NXX-NXX-XXXX when digits form a US 10-digit number (optional leading 1).
     * Values that do not normalize are set to NULL.
     *
     * @return int Rows updated (including clears)
     */
    public function normalizeStoredMemberPhonesToUsTenDigit(): int
    {
        $stmt = $this->pdo->query(
            "SELECT id, phone FROM members WHERE phone IS NOT NULL AND TRIM(phone) <> ''"
        );
        if ($stmt === false) {
            return 0;
        }
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $upd = $this->pdo->prepare('UPDATE members SET phone = ? WHERE id = ?');
        $changed = 0;
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $raw = (string) $row['phone'];
            $next = MemberInputNormalizer::normalizePhoneUsTenDigit($raw);
            if ($next !== $raw) {
                $upd->execute([$next, $id]);
                ++$changed;
            }
        }

        return $changed;
    }
}
