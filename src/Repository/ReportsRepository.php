<?php

declare(strict_types=1);

namespace DvraMembership\Repository;

use DateTimeImmutable;
use PDO;

/**
 * Keyholders + current-member rosters (Python list_keyholders, roster_by_name, roster_by_callsign).
 */
final class ReportsRepository
{
    private const KEYHOLDERS_SORT_FIELDS = ['name', 'call_sign', 'key_number', 'email'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, scalar|array> $qp
     *
     * @return array{sort_by: string, sort_dir: string}
     */
    public static function parseKeyholdersQuery(array $qp): array
    {
        $rawSb = isset($qp['sort_by']) ? trim((string) $qp['sort_by']) : 'name';
        $rawSd = isset($qp['sort_dir']) ? strtolower(trim((string) $qp['sort_dir'])) : 'asc';
        $sortDir = $rawSd === 'desc' ? 'desc' : 'asc';
        $sortBy = \in_array($rawSb, self::KEYHOLDERS_SORT_FIELDS, true) ? $rawSb : 'name';

        return ['sort_by' => $sortBy, 'sort_dir' => $sortDir];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function nextKeyholdersSortChoice(string $currentSortBy, string $currentSortDir, string $clickedField): array
    {
        if ($currentSortBy === $clickedField) {
            return [$clickedField, $currentSortDir === 'asc' ? 'desc' : 'asc'];
        }

        return [$clickedField, 'asc'];
    }

    /**
     * @return list<array{id: int, key_number: int, call_sign: string, last_name: string, first_name: string, email: string}>
     */
    public function listKeyholders(string $sortBy = 'name', string $sortDir = 'asc'): array
    {
        [$sortBy, $sortDir] = self::normalizeKeyholdersSort($sortBy, $sortDir);
        $orderBy = self::keyholdersOrderBySql($sortBy, $sortDir);
        $sql = <<<SQL
            SELECT m.id AS id, m.key_number AS key_number, m.call_sign AS call_sign,
                   m.last_name AS last_name, m.first_name AS first_name, m.email AS email
            FROM members m
            WHERE m.key_number IS NOT NULL
            ORDER BY {$orderBy}
            SQL;
        $stmt = $this->pdo->query($sql);
        if (!$stmt) {
            return [];
        }
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return array_map(static function (array $r): array {
            return [
                'id' => (int) $r['id'],
                'key_number' => (int) $r['key_number'],
                'call_sign' => isset($r['call_sign']) && $r['call_sign'] !== null ? strtoupper(trim((string) $r['call_sign'])) : '',
                'last_name' => isset($r['last_name']) ? (string) $r['last_name'] : '',
                'first_name' => isset($r['first_name']) ? (string) $r['first_name'] : '',
                'email' => isset($r['email']) && $r['email'] !== null ? (string) $r['email'] : '',
            ];
        }, $rows);
    }

    /** @return array{0: string, 1: string} */
    private static function normalizeKeyholdersSort(string $sortBy, string $sortDir): array
    {
        $sb = \in_array($sortBy, self::KEYHOLDERS_SORT_FIELDS, true) ? $sortBy : 'name';
        $sd = strtolower($sortDir);

        return [$sb, $sd === 'desc' ? 'desc' : 'asc'];
    }

    private static function keyholdersOrderBySql(string $sortBy, string $sortDir): string
    {
        $d = $sortDir === 'desc' ? 'DESC' : 'ASC';
        $tieName = 'm.last_name ASC, m.first_name ASC, m.id ASC';

        return match ($sortBy) {
            'call_sign' => "(m.call_sign) IS NULL, m.call_sign {$d}, {$tieName}",
            'key_number' => "m.key_number {$d}, {$tieName}",
            'email' => "(m.email IS NULL OR trim(coalesce(m.email, '')) = ''), m.email COLLATE NOCASE {$d}, {$tieName}",
            default => "m.last_name {$d}, m.first_name {$d}, m.id ASC",
        };
    }

    /**
     * Current members: paid_through set and date(paid_through) >= today (Python _current_member_sql_filters).
     *
     * @return list<array{last_name: string, first_name: string, call_sign: string}>
     */
    public function rosterByName(): array
    {
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $sql = <<<'SQL'
            SELECT m.last_name AS last_name, m.first_name AS first_name, m.call_sign AS call_sign
            FROM members m
            WHERE m.paid_through IS NOT NULL AND date(m.paid_through) >= date(?)
            ORDER BY m.last_name ASC, m.first_name ASC
            SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$today]);

        return $this->mapRosterRows($stmt->fetchAll());
    }

    /**
     * @return list<array{call_sign: string, last_name: string, first_name: string}>
     */
    public function rosterByCallsign(): array
    {
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $sql = <<<'SQL'
            SELECT m.call_sign AS call_sign, m.last_name AS last_name, m.first_name AS first_name
            FROM members m
            WHERE m.paid_through IS NOT NULL AND date(m.paid_through) >= date(?)
            ORDER BY (m.call_sign IS NULL), m.call_sign ASC, m.last_name ASC, m.first_name ASC
            SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$today]);

        /** @var list<array<string, mixed>> $raw */
        $raw = $stmt->fetchAll();
        $out = [];
        foreach ($raw as $r) {
            $cs = isset($r['call_sign']) && $r['call_sign'] !== null ? strtoupper(trim((string) $r['call_sign'])) : '';
            $out[] = [
                'call_sign' => $cs,
                'last_name' => isset($r['last_name']) ? (string) $r['last_name'] : '',
                'first_name' => isset($r['first_name']) ? (string) $r['first_name'] : '',
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{last_name: string, first_name: string, call_sign: string}>
     */
    private function mapRosterRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $cs = isset($r['call_sign']) && $r['call_sign'] !== null ? strtoupper(trim((string) $r['call_sign'])) : '';
            $out[] = [
                'last_name' => isset($r['last_name']) ? (string) $r['last_name'] : '',
                'first_name' => isset($r['first_name']) ? (string) $r['first_name'] : '',
                'call_sign' => $cs,
            ];
        }

        return $out;
    }
}
