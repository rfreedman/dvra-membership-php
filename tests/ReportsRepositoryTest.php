<?php

declare(strict_types=1);

namespace DvraMembership\Tests;

use DvraMembership\Repository\ReportsRepository;
use DvraMembership\Support\Schema;
use DvraMembership\Support\StaticRosterExports;
use PDO;
use PHPUnit\Framework\TestCase;

final class ReportsRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::ensure($this->pdo);

        $this->pdo->exec("INSERT INTO license_classes (name) VALUES ('EXTRA')");
        $this->pdo->exec("INSERT INTO membership_types (name) VALUES ('Annual')");
        $mtId = (int) $this->pdo->query('SELECT id FROM membership_types')->fetchColumn();
        $lcId = (int) $this->pdo->query('SELECT id FROM license_classes')->fetchColumn();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $future = (new \DateTimeImmutable('+1 year'))->format('Y-m-d');

        $this->pdo->prepare(
            'INSERT INTO members (last_name, first_name, call_sign, key_number, email, paid_through, membership_type_id, license_class_id, arrl_member, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)'
        )->execute(['Zulu', 'Zed', 'N9ZZ', 5, 'z@x.test', $future, $mtId, $lcId, $now, $now]);
        $this->pdo->prepare(
            'INSERT INTO members (last_name, first_name, call_sign, key_number, email, paid_through, membership_type_id, license_class_id, arrl_member, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)'
        )->execute(['Baker', 'Ben', 'N1BB', 3, 'b@test', $future, $mtId, $lcId, $now, $now]);
        $this->pdo->prepare(
            'INSERT INTO members (last_name, first_name, call_sign, key_number, email, paid_through, membership_type_id, license_class_id, arrl_member, created_at, updated_at)
             VALUES (?, ?, ?, NULL, NULL, ?, ?, ?, 0, ?, ?)'
        )->execute(['Alpha', 'Ann', 'N1AA', $future, $mtId, $lcId, $now, $now]);
        $this->pdo->prepare(
            'INSERT INTO members (last_name, first_name, call_sign, key_number, email, paid_through, membership_type_id, license_class_id, arrl_member, created_at, updated_at)
             VALUES (?, ?, ?, NULL, NULL, ?, ?, ?, 0, ?, ?)'
        )->execute(['Old', 'Expired', 'N2OLD', '2010-01-01', $mtId, $lcId, $now, $now]);
    }

    public function testKeyholdersOrderedAndExportable(): void
    {
        $repo = new ReportsRepository($this->pdo);
        $kh = $repo->listKeyholders();
        self::assertCount(2, $kh);
        self::assertNotSame('', StaticRosterExports::keyholdersCsvBinary($kh));
    }

    public function testKeyholdersDefaultSortByNameAscending(): void
    {
        $repo = new ReportsRepository($this->pdo);
        $kh = $repo->listKeyholders();
        self::assertSame('Baker', $kh[0]['last_name']);
        self::assertSame('Zulu', $kh[1]['last_name']);
    }

    public function testKeyholdersSortByKeyNumberDescending(): void
    {
        $repo = new ReportsRepository($this->pdo);
        $kh = $repo->listKeyholders('key_number', 'desc');
        self::assertSame(5, $kh[0]['key_number']);
        self::assertSame(3, $kh[1]['key_number']);
    }

    public function testParseKeyholdersQueryInvalidSortFallsBackToName(): void
    {
        $p = ReportsRepository::parseKeyholdersQuery(['sort_by' => 'nope', 'sort_dir' => 'desc']);
        self::assertSame('name', $p['sort_by']);
        self::assertSame('desc', $p['sort_dir']);
    }

    public function testRosterByNameCurrentOnly(): void
    {
        $repo = new ReportsRepository($this->pdo);
        $r = $repo->rosterByName();
        self::assertCount(3, $r);
        self::assertSame('Alpha', $r[0]['last_name']);
        self::assertSame('Baker', $r[1]['last_name']);
        self::assertSame('Zulu', $r[2]['last_name']);
    }

    public function testRosterByCallsignNullsLast(): void
    {
        $mtId = (int) $this->pdo->query('SELECT id FROM membership_types')->fetchColumn();
        $lcId = (int) $this->pdo->query('SELECT id FROM license_classes')->fetchColumn();
        $this->pdo->prepare(
            'INSERT INTO members (last_name, first_name, call_sign, paid_through, membership_type_id, license_class_id, arrl_member, created_at, updated_at)
             VALUES (?, ?, NULL, ?, ?, ?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        )->execute([
            'No',
            'Call',
            (new \DateTimeImmutable('+2 years'))->format('Y-m-d'),
            $mtId,
            $lcId,
        ]);

        $repo = new ReportsRepository($this->pdo);
        $rows = $repo->rosterByCallsign();
        $last = $rows[\count($rows) - 1];
        self::assertSame('', $last['call_sign']);
    }
}
