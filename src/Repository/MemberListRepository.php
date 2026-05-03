<?php

declare(strict_types=1);

namespace DvraMembership\Repository;

use DateTimeImmutable;
use PDO;

/**
 * Members list query + filter logic aligned with Python app/crud.py (list_members, count_members).
 */
final class MemberListRepository
{
    private const SORT_FIELDS = [
        'last_name',
        'first_name',
        'call_sign',
        'email',
        'phone',
        'address_street',
        'address_city',
        'address_state',
        'address_zip',
        'license_class',
        'membership_type',
        'arrl_member',
        'key_number',
        'paid_through',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, scalar|array> $qp PSR-7 getQueryParams() result
     * @return array{
     *   sort_by: string,
     *   sort_dir: string,
     *   search: string,
     *   membership_type_id: int|null,
     *   arrl: string,
     *   has_key: string,
     *   current_only: string,
     * }
     */
    public static function parseListQuery(array $qp): array
    {
        $search = isset($qp['search']) ? trim((string) $qp['search']) : '';

        $sortByRaw = isset($qp['sort_by']) ? (string) $qp['sort_by'] : 'last_name';
        $sortDirRaw = isset($qp['sort_dir']) ? (string) $qp['sort_dir'] : 'asc';
        [$sortBy, $sortDir] = self::normalizeSort($sortByRaw, $sortDirRaw);

        $midRaw = $qp['membership_type_id'] ?? '';
        $membershipTypeId = null;
        if (\is_scalar($midRaw) && trim((string) $midRaw) !== '') {
            $n = filter_var(trim((string) $midRaw), FILTER_VALIDATE_INT);
            $membershipTypeId = $n !== false ? $n : null;
        }

        $arrl = isset($qp['arrl']) ? trim((string) $qp['arrl']) : '';
        $hasKey = isset($qp['has_key']) ? trim((string) $qp['has_key']) : '';

        $co = $qp['current_only'] ?? null;
        if (\is_array($co)) {
            $co = end($co) ?: 'yes';
        }
        $currentOnly = isset($co) && \is_string($co) && $co !== '' ? $co : 'yes';
        if ($currentOnly !== 'yes' && $currentOnly !== 'no') {
            $currentOnly = 'yes';
        }

        return [
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'search' => $search,
            'membership_type_id' => $membershipTypeId,
            'arrl' => $arrl === 'yes' || $arrl === 'no' ? $arrl : '',
            'has_key' => $hasKey === 'yes' || $hasKey === 'no' ? $hasKey : '',
            'current_only' => $currentOnly,
        ];
    }

    /**
     * @return array{name: ?string, label: ?string, id: int}[]
     */
    public function listMembershipTypes(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, label FROM membership_types ORDER BY name ASC'
        );
        if (!$stmt) {
            return [];
        }
        /** @var list<array{id: string|int, name: mixed, label: mixed}> $rows */
        $rows = $stmt->fetchAll();
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

    /**
     * @param array{search: string, membership_type_id: int|null, arrl: string, has_key: string, current_only: string} $f
     */
    public function countMembers(array $f): int
    {
        [$where, $bind] = self::filterClause($f, $this->todayIso());
        $sql = 'SELECT COUNT(*) FROM members m WHERE ' . $where;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Rows shaped for Tabulator + JSON embedding (already encoded separately by caller).
     *
     * @param array{sort_by: string, sort_dir: string, search: string, membership_type_id: int|null, arrl: string, has_key: string, current_only: string} $p
     * @return list<array<string, mixed>>
     */
    public function listRowsForTabulator(array $p, string $urlBase): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->fetchFilteredMemberJoinRows($p);

        return array_map(fn (array $r): array => array_merge($this->mapJoinedRowForUi($r), [
            'actions_html' => self::actionsHtml((int) $r['id'], self::normalizedBase($urlBase)),
        ]), $rows);
    }

    /**
     * Same row set/order as the members grid minus Actions (for CSV / Excel / PDF).
     *
     * @param array{sort_by: string, sort_dir: string, search: string, membership_type_id: int|null, arrl: string, has_key: string, current_only: string} $p
     * @return list<array<string, mixed>>
     */
    public function listRowsForExport(array $p): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->fetchFilteredMemberJoinRows($p);

        return array_map(fn (array $r): array => $this->mapJoinedRowForUi($r), $rows);
    }

    /**
     * @param array{sort_by: string, sort_dir: string, search: string, membership_type_id: int|null, arrl: string, has_key: string, current_only: string} $p
     * @return list<array<string, mixed>>
     */
    private function fetchFilteredMemberJoinRows(array $p): array
    {
        [$where, $bind] = self::filterClause([
            'search' => $p['search'],
            'membership_type_id' => $p['membership_type_id'],
            'arrl' => $p['arrl'],
            'has_key' => $p['has_key'],
            'current_only' => $p['current_only'],
        ], $this->todayIso());

        $order = self::orderBySql($p['sort_by'], $p['sort_dir']);

        $sql = <<<SQL
            SELECT m.id AS id,
                   m.last_name AS last_name,
                   m.first_name AS first_name,
                   m.call_sign AS call_sign,
                   m.email AS email,
                   m.phone AS phone,
                   m.address_street AS address_street,
                   m.address_city AS address_city,
                   m.address_state AS address_state,
                   m.address_zip AS address_zip,
                   m.arrl_member AS arrl_member,
                   m.key_number AS key_number,
                   m.paid_through AS paid_through,
                   lc.name AS lc_name, lc.label AS lc_label,
                   mt.name AS mt_name, mt.label AS mt_label
            FROM members m
            LEFT JOIN license_classes lc ON m.license_class_id = lc.id
            LEFT JOIN membership_types mt ON m.membership_type_id = mt.id
            WHERE {$where}
            ORDER BY {$order}
            SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);

        return $stmt->fetchAll();
    }

    /**
     * Mirrors Tabulator-visible fields (excluding Actions HTML).
     *
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function mapJoinedRowForUi(array $r): array
    {
        $id = (int) $r['id'];
        $lcName = isset($r['lc_name']) ? (string) $r['lc_name'] : '';
        $lcLabel = isset($r['lc_label']) && $r['lc_label'] !== null ? (string) $r['lc_label'] : '';
        $mtName = isset($r['mt_name']) ? (string) $r['mt_name'] : '';
        $mtLabel = isset($r['mt_label']) && $r['mt_label'] !== null ? (string) $r['mt_label'] : '';

        $call = isset($r['call_sign']) && $r['call_sign'] !== null ? (string) $r['call_sign'] : '';
        $callUpper = strtoupper(trim($call));

        return [
            'id' => $id,
            'call_sign' => $callUpper,
            'last_name' => isset($r['last_name']) ? (string) $r['last_name'] : '',
            'first_name' => isset($r['first_name']) ? (string) $r['first_name'] : '',
            'email' => isset($r['email']) && $r['email'] !== null ? (string) $r['email'] : '',
            'phone' => isset($r['phone']) && $r['phone'] !== null ? (string) $r['phone'] : '',
            'address_street' => isset($r['address_street']) && $r['address_street'] !== null
                ? (string) $r['address_street'] : '',
            'address_city' => isset($r['address_city']) && $r['address_city'] !== null
                ? (string) $r['address_city'] : '',
            'address_state' => isset($r['address_state']) && $r['address_state'] !== null
                ? (string) $r['address_state'] : '',
            'address_zip' => isset($r['address_zip']) && $r['address_zip'] !== null
                ? (string) $r['address_zip'] : '',
            'license_class' => self::referenceLabel($lcName !== '' ? $lcName : null, $lcLabel !== '' ? $lcLabel : null),
            'membership_type' => self::referenceLabel($mtName !== '' ? $mtName : null, $mtLabel !== '' ? $mtLabel : null),
            'arrl_member' => (bool) ($r['arrl_member'] ?? 0),
            'key_number' => isset($r['key_number']) && $r['key_number'] !== null ? (int) $r['key_number'] : null,
            'paid_through' => isset($r['paid_through']) && $r['paid_through'] !== null
                ? (string) $r['paid_through'] : '',
        ];
    }

    public static function tabulatorJsonFromRows(array $rows): string
    {
        $raw = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

        return $raw !== false ? $raw : '[]';
    }

    /** @internal */
    public static function normalizedBase(string $basePathSetting): string
    {
        $b = trim($basePathSetting);
        if ($b === '') {
            return '';
        }

        return rtrim($b, '/');
    }

    private static function referenceLabel(?string $name, ?string $label): string
    {
        $l = trim((string) ($label ?? ''));
        if ($l !== '') {
            return $l;
        }

        return trim((string) ($name ?? ''));
    }

    private static function actionsHtml(int $memberId, string $base): string
    {
        $p = htmlspecialchars($base, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<a href="' . $p . '/members/' . $memberId . '/view">Edit</a>'
            . '<span aria-hidden="true"> · </span>'
            . '<a href="' . $p . '/members/' . $memberId . '/payments">Payments</a>';
    }

    private function todayIso(): string
    {
        return (new DateTimeImmutable('today'))->format('Y-m-d');
    }

    /**
     * @return array{0: string, 1: array<int|string>}
     */
    private static function filterClause(array $f, string $todayIso): array
    {
        $parts = ['1 = 1'];
        $bind = [];

        $search = $f['search'] ?? '';
        if ($search !== '') {
            $needle = '%' . self::escapeLike($search) . '%';
            /** Search-only fields: names, call sign, email (no phone or mailing address columns). */
            $likeCols = [
                'COALESCE(m.last_name, \'\')',
                'COALESCE(m.first_name, \'\')',
                'COALESCE(m.call_sign, \'\')',
                'COALESCE(m.email, \'\')',
            ];
            $sub = [];
            foreach ($likeCols as $expr) {
                $sub[] = "LOWER({$expr}) LIKE LOWER(?) ESCAPE '\\'";
                $bind[] = $needle;
            }
            $parts[] = '(' . implode(' OR ', $sub) . ')';
        }

        $mtid = $f['membership_type_id'] ?? null;
        if ($mtid !== null) {
            $parts[] = 'm.membership_type_id = ?';
            $bind[] = $mtid;
        }

        $arrl = $f['arrl'] ?? '';
        if ($arrl === 'yes') {
            $parts[] = 'm.arrl_member = 1';
        } elseif ($arrl === 'no') {
            $parts[] = 'm.arrl_member = 0';
        }

        $hasKey = $f['has_key'] ?? '';
        if ($hasKey === 'yes') {
            $parts[] = 'm.key_number IS NOT NULL';
        } elseif ($hasKey === 'no') {
            $parts[] = 'm.key_number IS NULL';
        }

        $currentOnlyFlag = strtolower(trim((string) ($f['current_only'] ?? 'yes')));
        if ($currentOnlyFlag !== 'no') {
            $parts[] = '(m.paid_through IS NOT NULL AND date(m.paid_through) >= date(?))';
            $bind[] = $todayIso;
        }

        return [implode(' AND ', $parts), $bind];
    }

    /**
     * Canonical GET query representing the parsed list/export parameters (deterministic ordering for links).
     *
     * @param array{sort_by: string, sort_dir: string, search: string, membership_type_id: int|null, arrl: string, has_key: string, current_only: string} $p
     */
    public static function listParamsToQueryInput(array $p): array
    {
        $q = [
            'sort_by' => $p['sort_by'],
            'sort_dir' => $p['sort_dir'],
            'current_only' => $p['current_only'],
            'search' => $p['search'],
            'arrl' => $p['arrl'],
            'has_key' => $p['has_key'],
        ];
        if ($p['membership_type_id'] !== null) {
            $q['membership_type_id'] = $p['membership_type_id'];
        }

        return $q;
    }

    /** @param array<string, mixed> $p Same shape as parseListQuery output */
    public static function buildExportQueryString(array $p): string
    {
        $qs = http_build_query(self::listParamsToQueryInput($p));

        return $qs === '' ? '' : ('?' . $qs);
    }

    private static function escapeLike(string $needle): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function normalizeSort(string $sortBy, string $sortDir): array
    {
        $sb = \in_array($sortBy, self::SORT_FIELDS, true) ? $sortBy : 'last_name';
        $sd = strtolower($sortDir);

        return [$sb, ($sd === 'desc' ? 'desc' : 'asc')];
    }

    /** Nulls-last for nullable sorts; plain sort for last_name / first_name; arrl_member unqualified. */
    private static function orderBySql(string $sortBy, string $sortDir): string
    {
        $desc = $sortDir === 'desc';
        $dir = $desc ? 'DESC' : 'ASC';
        $tail = ', m.id ASC';

        return match ($sortBy) {
            'last_name' => "m.last_name {$dir}{$tail}",
            'first_name' => "m.first_name {$dir}{$tail}",
            'call_sign' => "(m.call_sign) IS NULL, m.call_sign {$dir}{$tail}",
            'email' => "(m.email) IS NULL, m.email {$dir}{$tail}",
            'phone' => "(m.phone) IS NULL, m.phone {$dir}{$tail}",
            'address_street' => "(m.address_street) IS NULL, m.address_street {$dir}{$tail}",
            'address_city' => "(m.address_city) IS NULL, m.address_city {$dir}{$tail}",
            'address_state' => "(m.address_state) IS NULL, m.address_state {$dir}{$tail}",
            'address_zip' => "(m.address_zip) IS NULL, m.address_zip {$dir}{$tail}",
            'paid_through' => "(m.paid_through) IS NULL, m.paid_through {$dir}{$tail}",
            'key_number' => "(m.key_number) IS NULL, m.key_number {$dir}{$tail}",
            'membership_type' => "(mt.name) IS NULL, mt.name {$dir}{$tail}",
            'license_class' => "(lc.name) IS NULL, lc.name {$dir}{$tail}",
            'arrl_member' => "m.arrl_member {$dir}{$tail}",
            default => "m.last_name {$dir}{$tail}",
        };
    }
}
