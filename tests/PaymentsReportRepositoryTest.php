<?php

declare(strict_types=1);

namespace DvraMembership\Tests;

use DvraMembership\Repository\PaymentsReportRepository;
use DvraMembership\Support\PaymentsReportExport;
use DvraMembership\Support\Schema;
use PDO;
use PHPUnit\Framework\TestCase;

final class PaymentsReportRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::ensure($this->pdo);

        $this->pdo->exec("INSERT INTO license_classes (name, label) VALUES ('EXTRA', '')");
        $this->pdo->exec("INSERT INTO membership_types (name, label) VALUES ('Annual', '')");
        $mtId = (int) $this->pdo->query('SELECT id FROM membership_types')->fetchColumn();
        $lcId = (int) $this->pdo->query('SELECT id FROM license_classes')->fetchColumn();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->pdo->prepare(
            'INSERT INTO members (last_name, first_name, call_sign, membership_type_id, license_class_id, arrl_member, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?)'
        )->execute(['A', 'One', 'N1AAA', $mtId, $lcId, $now, $now]);
        $m1 = (int) $this->pdo->lastInsertId();

        $ins = static function (PDO $pdo, int $memberId, string $paymentDate): void {
            $pdo->prepare(
                'INSERT INTO payments (member_id, payment_date, paid_through, membership_type_id, notes, created_at)
                 VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
            )->execute([$memberId, $paymentDate, $paymentDate, null, '']);
        };

        $ins($this->pdo, $m1, '2025-06-01');
        $ins($this->pdo, $m1, '2026-06-01');
        $ins($this->pdo, $m1, '2026-06-02');
    }

    public function testNoFiltersReturnsAllPaymentsOrdered(): void
    {
        $repo = new PaymentsReportRepository($this->pdo);
        $p = PaymentsReportRepository::parsePaymentReportQuery([]);
        self::assertSame(3, $repo->countRows($p));
        $rows = $repo->listPaymentReportRows($p);
        self::assertCount(3, $rows);
        self::assertSame('2026-06-02', $rows[0]['payment_date']);
        self::assertSame('2025-06-01', $rows[2]['payment_date']);
    }

    public function testPaymentDateRange(): void
    {
        $repo = new PaymentsReportRepository($this->pdo);
        $p = PaymentsReportRepository::parsePaymentReportQuery([
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]);
        self::assertSame(2, $repo->countRows($p));
    }

    public function testPaidThroughRange(): void
    {
        $repo = new PaymentsReportRepository($this->pdo);
        $p = PaymentsReportRepository::parsePaymentReportQuery([
            'paid_through_start' => '2026-01-01',
            'paid_through_end' => '2026-12-31',
        ]);
        self::assertSame(2, $repo->countRows($p));
    }

    public function testExportSmoke(): void
    {
        $repo = new PaymentsReportRepository($this->pdo);
        $rows = $repo->listPaymentReportRows(PaymentsReportRepository::parsePaymentReportQuery([]));
        self::assertNotSame('', PaymentsReportExport::toCsvBinary($rows));
        self::assertGreaterThan(100, strlen(PaymentsReportExport::toXlsxBinary($rows)));
        self::assertNotSame('', PaymentsReportExport::toPdfBinary($rows));
    }

    public function testSortByPaymentDateAscending(): void
    {
        $repo = new PaymentsReportRepository($this->pdo);
        $p = PaymentsReportRepository::parsePaymentReportQuery([
            'sort_by' => 'payment_date',
            'sort_dir' => 'asc',
        ]);
        $rows = $repo->listPaymentReportRows($p);
        self::assertSame(
            ['2025-06-01', '2026-06-01', '2026-06-02'],
            array_map(static fn (array $r): string => (string) $r['payment_date'], $rows)
        );
    }

    public function testParsePaymentReportQueryIgnoresLegacyNotesSort(): void
    {
        $p = PaymentsReportRepository::parsePaymentReportQuery([
            'sort_by' => 'notes',
            'sort_dir' => 'desc',
        ]);
        self::assertSame('report_default', $p['sort_by']);
        self::assertSame('desc', $p['sort_dir']);
    }

    public function testMembershipTypeExportUsesLabelWhenNameMirrorsNumericId(): void
    {
        $this->pdo->exec(
            "INSERT INTO membership_types (id, name, label) VALUES (42, '42', 'Supporter level')"
        );
        $mId = (int) $this->pdo->query('SELECT id FROM members ORDER BY id ASC LIMIT 1')->fetchColumn();
        $this->pdo->prepare(
            'INSERT INTO payments (member_id, payment_date, paid_through, membership_type_id, notes, created_at)
             VALUES (?, ?, ?, 42, ?, CURRENT_TIMESTAMP)'
        )->execute([$mId, '2026-07-01', '2026-07-01', '']);

        $repo = new PaymentsReportRepository($this->pdo);
        $rows = $repo->listPaymentReportRows(PaymentsReportRepository::parsePaymentReportQuery([]));
        $target = null;
        foreach ($rows as $row) {
            if (($row['payment_date'] ?? '') === '2026-07-01') {
                $target = $row;
                break;
            }
        }
        self::assertNotNull($target);
        self::assertSame('Supporter level', $target['membership_type']);
        $csv = PaymentsReportExport::toCsvBinary($rows);
        self::assertStringContainsString('Supporter level', $csv);
    }

    public function testMembershipTypeBlankWhenLabelEmptyAndNameEqualsTypeId(): void
    {
        $this->pdo->exec("INSERT INTO membership_types (id, name, label) VALUES (99, '99', '')");
        $mId = (int) $this->pdo->query('SELECT id FROM members ORDER BY id ASC LIMIT 1')->fetchColumn();
        $this->pdo->prepare(
            'INSERT INTO payments (member_id, payment_date, paid_through, membership_type_id, notes, created_at)
             VALUES (?, ?, ?, 99, ?, CURRENT_TIMESTAMP)'
        )->execute([$mId, '2026-08-01', '2026-08-01', '']);

        $repo = new PaymentsReportRepository($this->pdo);
        $rows = $repo->listPaymentReportRows(PaymentsReportRepository::parsePaymentReportQuery([]));
        $row = null;
        foreach ($rows as $r) {
            if (($r['payment_date'] ?? '') === '2026-08-01') {
                $row = $r;
                break;
            }
        }
        self::assertNotNull($row);
        self::assertSame('', (string) ($row['membership_type'] ?? ''));
    }
}
