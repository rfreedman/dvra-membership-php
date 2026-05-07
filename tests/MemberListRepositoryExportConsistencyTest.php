<?php

declare(strict_types=1);

namespace DvraMembership\Tests;

use DvraMembership\Repository\MemberListRepository;
use DvraMembership\Support\MemberExport;
use DvraMembership\Support\Schema;
use PDO;
use PHPUnit\Framework\TestCase;

final class MemberListRepositoryExportConsistencyTest extends TestCase
{
    private PDO $pdo;

    /** @var int|null */
    private static ?int $mtId = null;

    /** @var int|null */
    private static ?int $lcId = null;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::ensure($this->pdo);

        $this->pdo->exec("INSERT INTO license_classes (name) VALUES ('EXTRA')");
        $this->pdo->exec("INSERT INTO membership_types (name) VALUES ('Annual')");
        /** @var int|false $lid */
        $lid = $this->pdo->query('SELECT id FROM license_classes LIMIT 1')->fetchColumn();
        /** @var int|false $tid */
        $tid = $this->pdo->query('SELECT id FROM membership_types LIMIT 1')->fetchColumn();
        self::$lcId = \is_numeric($lid) ? (int) $lid : null;
        self::$mtId = \is_numeric($tid) ? (int) $tid : null;

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $soon = (new \DateTimeImmutable('+1 year'))->format('Y-m-d');
        $ins = $this->pdo->prepare(
            <<<'SQL'
            INSERT INTO members (
                last_name, first_name, call_sign,
                membership_type_id, license_class_id, arrl_member, paid_through, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, 0, ?, ?, ?
            )
            SQL
        );
        foreach (
            [
                ['CurrentOk', 'N1CUR', $soon],
                ['OldExpired', 'N8OLD', '2010-01-01'],
                ['NoDate', 'N9NON', null],
            ] as [$ln, $cs, $paid]
        ) {
            $ins->execute([
                $ln,
                'Tester',
                $cs,
                self::$mtId,
                self::$lcId,
                $paid,
                $now,
                $now,
            ]);
        }
    }

    public function testExportRowCountMatchesCountMembersForCurrentOnlyYes(): void
    {
        $repo = new MemberListRepository($this->pdo);
        $p = MemberListRepository::parseListQuery([
            'current_only' => 'yes',
            'sort_by' => 'last_name',
            'sort_dir' => 'asc',
        ]);
        $filter = [
            'search' => $p['search'],
            'membership_type_id' => $p['membership_type_id'],
            'arrl' => $p['arrl'],
            'current_only' => $p['current_only'],
        ];
        $n = $repo->countMembers($filter);
        $rows = $repo->listRowsForExport($p);
        $this->assertSame($n, \count($rows));
        $this->assertSame(1, $n);
    }

    public function testExportRowCountMatchesCountMembersForAllMembers(): void
    {
        $repo = new MemberListRepository($this->pdo);
        $p = MemberListRepository::parseListQuery([
            'current_only' => 'no',
            'sort_by' => 'last_name',
            'sort_dir' => 'asc',
        ]);
        $filter = [
            'search' => $p['search'],
            'membership_type_id' => $p['membership_type_id'],
            'arrl' => $p['arrl'],
            'current_only' => $p['current_only'],
        ];
        $n = $repo->countMembers($filter);
        $rows = $repo->listRowsForExport($p);
        $this->assertSame($n, \count($rows));
        $this->assertSame(3, $n);
    }

    public function testListRowsForTabulatorAndExportShareRowShapeMinusActions(): void
    {
        $repo = new MemberListRepository($this->pdo);
        $p = MemberListRepository::parseListQuery([
            'current_only' => 'yes',
            'sort_by' => 'last_name',
            'sort_dir' => 'asc',
        ]);
        $tab = $repo->listRowsForTabulator($p, '');
        $exp = $repo->listRowsForExport($p);
        $this->assertCount(1, $tab);
        $this->assertCount(1, $exp);
        unset($tab[0]['actions_html']);
        $this->assertSame($exp[0], $tab[0]);
    }

    /** Exercise MemberExport render paths for non-empty payloads */
    public function testMemberExportSerializesCsvAndXlsxAndPdfWithoutError(): void
    {
        $repo = new MemberListRepository($this->pdo);
        $p = MemberListRepository::parseListQuery([
            'current_only' => 'yes',
            'sort_by' => 'last_name',
            'sort_dir' => 'asc',
        ]);
        $rows = $repo->listRowsForExport($p);
        self::assertNotSame('', MemberExport::toCsvBinary($rows));
        self::assertGreaterThan(200, strlen(MemberExport::toXlsxBinary($rows)));
        self::assertNotSame('', MemberExport::toPdfBinary($rows));
    }
}
