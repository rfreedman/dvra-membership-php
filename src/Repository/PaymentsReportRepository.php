<?php

declare(strict_types=1);

namespace DvraMembership\Repository;

use DateTimeImmutable;
use DvraMembership\Support\MemberInputNormalizer;
use PDO;

/**
 * Payment report aligned with Python crud.list_payments + filters; sorting is PHP UI-only.
 */
final class PaymentsReportRepository
{
    /**
     * Built-in default primary keys (before secondary call_sign / payment_date / id tail).
     * Final order: base + secondary suffix (call_sign, payment_date when not primary) + p.id DESC.
     */
    private const ORDER_SQL_REPORT_DEFAULT_BASE = 'm.last_name ASC, m.first_name ASC, (p.paid_through) IS NULL ASC, p.paid_through DESC';

    private const SORT_FIELDS = [
        'report_default',
        'member_name',
        'member_id',
        'call_sign',
        'payment_date',
        'paid_through',
        'membership_type',
        'form_number',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Display strings mirror request (invalid dates still shown); filters use validated dates only.
     *
     * @param array<string, scalar|array> $qp
     * @return array{
     *   start_date: string,
     *   end_date: string,
     *   paid_through_start: string,
     *   paid_through_end: string,
     *   start_filter: ?string,
     *   end_filter: ?string,
     *   pt_start_filter: ?string,
     *   pt_end_filter: ?string,
     *   sort_by: string,
     *   sort_dir: string,
     * }
     */
    public static function parsePaymentReportQuery(array $qp): array
    {
        $sd = self::scalarParam($qp, 'start_date');
        $ed = self::scalarParam($qp, 'end_date');
        $pts = self::scalarParam($qp, 'paid_through_start');
        $pte = self::scalarParam($qp, 'paid_through_end');
        [$sortBy, $sortDir] = self::normalizeSort(
            isset($qp['sort_by']) ? (string) $qp['sort_by'] : 'report_default',
            isset($qp['sort_dir']) ? (string) $qp['sort_dir'] : 'asc',
        );

        return [
            'start_date' => $sd,
            'end_date' => $ed,
            'paid_through_start' => $pts,
            'paid_through_end' => $pte,
            'start_filter' => self::optionalIsoDate($sd),
            'end_filter' => self::optionalIsoDate($ed),
            'pt_start_filter' => self::optionalIsoDate($pts),
            'pt_end_filter' => self::optionalIsoDate($pte),
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
        ];
    }

    /**
     * First click direction for each column when switching sort (otherwise toggle).
     *
     * @return array{0: string, 1: string}
     */
    public static function nextSortChoice(string $currentSortBy, string $currentSortDir, string $clickedField): array
    {
        if ($currentSortBy === $clickedField) {
            return [$clickedField, $currentSortDir === 'asc' ? 'desc' : 'asc'];
        }
        $descFirst = ['payment_date', 'paid_through'];

        return [$clickedField, \in_array($clickedField, $descFirst, true) ? 'desc' : 'asc'];
    }

    /**
     * @param array<string, mixed> $p Output of parsePaymentReportQuery
     * @return array<string, string|int>
     */
    public static function listParamsToQueryInput(array $p): array
    {
        $q = [];
        if (($p['start_date'] ?? '') !== '') {
            $q['start_date'] = (string) $p['start_date'];
        }
        if (($p['end_date'] ?? '') !== '') {
            $q['end_date'] = (string) $p['end_date'];
        }
        if (($p['paid_through_start'] ?? '') !== '') {
            $q['paid_through_start'] = (string) $p['paid_through_start'];
        }
        if (($p['paid_through_end'] ?? '') !== '') {
            $q['paid_through_end'] = (string) $p['paid_through_end'];
        }
        $q['sort_by'] = (string) ($p['sort_by'] ?? 'report_default');
        $q['sort_dir'] = (string) ($p['sort_dir'] ?? 'asc');

        return $q;
    }

    /** @param array<string, mixed> $p */
    public static function buildExportQueryString(array $p): string
    {
        $qs = http_build_query(self::listParamsToQueryInput($p));

        return $qs === '' ? '' : ('?' . $qs);
    }

    /** @param array<string, mixed> $p From parsePaymentReportQuery */
    public function countRows(array $p): int
    {
        [$where, $bind] = self::filterClause($p);
        $sql = 'SELECT COUNT(*) FROM payments p INNER JOIN members m ON m.id = p.member_id WHERE ' . $where;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Rows for HTML table + export shaping (combined member name matches Python CSV "member_name" cell).
     *
     * @param array<string, mixed> $p
     * @return list<array<string, mixed>>
     */
    public function listPaymentReportRows(array $p): array
    {
        [$where, $bind] = self::filterClause($p);

        $sql = <<<SQL
            SELECT p.id AS payment_id,
                   p.member_id AS member_id,
                   p.payment_date AS payment_date,
                   p.paid_through AS paid_through,
                   p.form_number AS form_number,
                   p.notes AS notes,
                   m.last_name AS last_name,
                   m.first_name AS first_name,
                   m.call_sign AS call_sign,
                   mt.id AS mt_id,
                   mt.name AS mt_name,
                   mt.label AS mt_label
            FROM payments p
            INNER JOIN members m ON m.id = p.member_id
            LEFT JOIN membership_types mt ON mt.id = COALESCE(p.membership_type_id, m.membership_type_id)
            WHERE {$where}
            ORDER BY {$this->orderBySql((string) $p['sort_by'], (string) $p['sort_dir'])}
            SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);
        /** @var list<array<string, mixed>> $raw */
        $raw = $stmt->fetchAll();

        return array_map(fn (array $r): array => self::mapRow($r), $raw);
    }

    /**
     * Prefer reference label column; fallback to internal name unless name is a bogus duplicate of the type row id
     * (some imports store the numeric id in `name` and the human text only in `label`).
     *
     * @param array<string, mixed> $r Joined fetch row
     */
    public static function mapRow(array $r): array
    {
        $ln = isset($r['last_name']) ? (string) $r['last_name'] : '';
        $fn = isset($r['first_name']) ? (string) $r['first_name'] : '';
        $memberName = $ln !== '' || $fn !== '' ? "{$ln}, {$fn}" : '';
        $nm = isset($r['mt_name']) && $r['mt_name'] !== null ? (string) $r['mt_name'] : '';
        $lb = isset($r['mt_label']) && $r['mt_label'] !== null ? (string) $r['mt_label'] : '';
        $mtId = isset($r['mt_id']) && $r['mt_id'] !== null && $r['mt_id'] !== ''
            ? (int) $r['mt_id']
            : null;
        $call = isset($r['call_sign']) && $r['call_sign'] !== null ? (string) $r['call_sign'] : '';

        return [
            'payment_id' => (int) $r['payment_id'],
            'member_id' => (int) $r['member_id'],
            'member_name' => $memberName,
            'call_sign' => $call !== '' ? strtoupper(trim($call)) : '',
            'payment_date' => isset($r['payment_date']) ? (string) $r['payment_date'] : '',
            'paid_through' => isset($r['paid_through']) ? (string) $r['paid_through'] : '',
            'membership_type' => self::membershipTypeDisplayLabel($mtId, $nm, $lb),
            'form_number' => isset($r['form_number']) && $r['form_number'] !== null ? (string) $r['form_number'] : '',
            'notes' => isset($r['notes']) && $r['notes'] !== null ? (string) $r['notes'] : '',
        ];
    }

    /** Human-facing membership type for payment report (HTML + exports). */
    private static function membershipTypeDisplayLabel(?int $resolvedTypeRowId, string $name, string $label): string
    {
        $base = MemberInputNormalizer::referenceLabel($name !== '' ? $name : null, $label !== '' ? $label : null);
        if ($base === '' || $resolvedTypeRowId === null || $resolvedTypeRowId < 1) {
            return $base;
        }
        $trimLabel = trim($label);
        $trimName = trim($name);
        if ($trimLabel !== '') {
            return $base;
        }
        if ($trimName !== '' && ctype_digit($trimName) && (int) $trimName === $resolvedTypeRowId) {
            return '';
        }

        return $base;
    }

    /**
     * @param array<string, mixed> $p
     * @return array{0: string, 1: array<int|string>}
     */
    private static function filterClause(array $p): array
    {
        $parts = ['1 = 1'];
        $bind = [];

        if (($p['start_filter'] ?? null) !== null) {
            $parts[] = 'date(p.payment_date) >= date(?)';
            $bind[] = $p['start_filter'];
        }
        if (($p['end_filter'] ?? null) !== null) {
            $parts[] = 'date(p.payment_date) <= date(?)';
            $bind[] = $p['end_filter'];
        }
        if (($p['pt_start_filter'] ?? null) !== null) {
            $parts[] = 'date(p.paid_through) >= date(?)';
            $bind[] = $p['pt_start_filter'];
        }
        if (($p['pt_end_filter'] ?? null) !== null) {
            $parts[] = 'date(p.paid_through) <= date(?)';
            $bind[] = $p['pt_end_filter'];
        }

        return [implode(' AND ', $parts), $bind];
    }

    private static function scalarParam(array $qp, string $key): string
    {
        $v = $qp[$key] ?? '';
        if (\is_array($v)) {
            $v = end($v);
            if ($v === false) {
                return '';
            }
        }

        return \is_scalar($v) ? trim((string) $v) : '';
    }

    /**
     * Stable secondary ordering for filtered/sorted payment report: last+first ASC, call_sign ASC,
     * payment_date DESC — each omitted when that dimension is already the user’s primary sort.
     * report_default already fixes member name + paid_through; do not repeat name here.
     */
    private static function paymentReportSecondaryOrderSuffix(string $sortBy): string
    {
        $parts = [];
        if ($sortBy !== 'member_name' && $sortBy !== 'report_default') {
            $parts[] = 'm.last_name ASC, m.first_name ASC';
        }
        if ($sortBy !== 'call_sign') {
            $parts[] = '(m.call_sign) IS NULL, m.call_sign ASC';
        }
        if ($sortBy !== 'payment_date') {
            $parts[] = '(p.payment_date) IS NULL, p.payment_date DESC';
        }

        return $parts === [] ? '' : (', ' . implode(', ', $parts));
    }

    private function orderBySql(string $sortBy, string $sortDir): string
    {
        $desc = $sortDir === 'desc';
        $dir = $desc ? 'DESC' : 'ASC';
        // Tie-break: follow sort_dir so ascending sorts aren’t flipped to “newest first” per member.
        $tie = ', p.id ' . ($desc ? 'DESC' : 'ASC');
        $sec = self::paymentReportSecondaryOrderSuffix($sortBy);

        return match ($sortBy) {
            'report_default' => self::ORDER_SQL_REPORT_DEFAULT_BASE . $sec . ', p.id DESC',
            'member_id' => "p.member_id {$dir}{$sec}{$tie}",
            'member_name' => "m.last_name {$dir}, m.first_name {$dir}{$sec}{$tie}",
            'call_sign' => "(m.call_sign) IS NULL, m.call_sign {$dir}{$sec}{$tie}",
            'payment_date' => "(p.payment_date) IS NULL, p.payment_date {$dir}{$sec}{$tie}",
            'paid_through' => "(p.paid_through) IS NULL, p.paid_through {$dir}{$sec}{$tie}",
            'membership_type' => "(mt.name) IS NULL, mt.name {$dir}{$sec}{$tie}",
            'form_number' => "(p.form_number) IS NULL, p.form_number {$dir}{$sec}{$tie}",
            default => self::ORDER_SQL_REPORT_DEFAULT_BASE . $sec . ', p.id DESC',
        };
    }

    /** @return array{0: string, 1: string} */
    private static function normalizeSort(string $sortBy, string $sortDir): array
    {
        $sb = \in_array($sortBy, self::SORT_FIELDS, true) ? $sortBy : 'report_default';
        $sd = strtolower($sortDir);

        return [$sb, ($sd === 'desc' ? 'desc' : 'asc')];
    }

    private static function optionalIsoDate(string $text): ?string
    {
        if ($text === '') {
            return null;
        }
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $text);

        return $d !== false && $d->format('Y-m-d') === $text ? $text : null;
    }
}
