<?php

declare(strict_types=1);

namespace DvraMembership\Tests;

use DvraMembership\Repository\AdminAccountRepository;
use DvraMembership\Repository\ReferenceDataRepository;
use DvraMembership\Support\Schema;
use PDO;
use PHPUnit\Framework\TestCase;

final class ReferenceDataAdminAccountRepositoryTest extends TestCase
{
    /** @var non-empty-string */
    private static function ym(int $year, int $month = 6): string
    {
        return sprintf('%04d-%02d-01', $year, $month);
    }

    /**
     * In-memory PDO with FK on, seeded member + membership type.
     *
     * @return array{pdo: PDO, membershipTypeId: int, repo: ReferenceDataRepository}
     */
    private static function sqliteWithMemberAndMt(): array
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::ensure($pdo);
        $pdo->exec("INSERT INTO membership_types (name) VALUES ('TestType')");
        $mtId = (int) $pdo->query('SELECT id FROM membership_types LIMIT 1')->fetchColumn();

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $ins = $pdo->prepare(
            'INSERT INTO members (last_name, first_name, phone, membership_type_id, license_class_id, arrl_member, created_at, updated_at)
             VALUES (?, ?, ?, ?, NULL, 0, ?, ?)'
        );
        $ins->execute(['Last', 'First', '', $mtId, $now, $now]);

        return [
            'pdo' => $pdo,
            'membershipTypeId' => $mtId,
            'repo' => new ReferenceDataRepository($pdo),
        ];
    }

    public function testMembershipTypeBlockedWhenPaymentYearIsCurrentYear(): void
    {
        $ctx = self::sqliteWithMemberAndMt();
        $pdo = $ctx['pdo'];
        $mtId = $ctx['membershipTypeId'];
        $repo = $ctx['repo'];
        $memberId = (int) $pdo->query('SELECT id FROM members LIMIT 1')->fetchColumn();
        $y = (int) (new \DateTimeImmutable('today'))->format('Y');
        $ins = $pdo->prepare(
            'INSERT INTO payments (member_id, payment_date, paid_through, membership_type_id, notes, form_number)
             VALUES (?, ?, ?, ?, NULL, NULL)'
        );
        $ins->execute([$memberId, self::ym($y), self::ym($y - 1), $mtId]);

        self::assertTrue($repo->membershipTypeBlockedByPayments($mtId));
        try {
            $repo->deleteMembershipTypeOrFail($mtId);
            self::fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('payments in the current or future years', $e->getMessage());
        }
    }

    public function testMembershipTypeBlockedWhenPaidThroughYearIsCurrentYear(): void
    {
        $ctx = self::sqliteWithMemberAndMt();
        $pdo = $ctx['pdo'];
        $mtId = $ctx['membershipTypeId'];
        $repo = $ctx['repo'];
        $memberId = (int) $pdo->query('SELECT id FROM members LIMIT 1')->fetchColumn();
        $y = (int) (new \DateTimeImmutable('today'))->format('Y');
        $ins = $pdo->prepare(
            'INSERT INTO payments (member_id, payment_date, paid_through, membership_type_id, notes, form_number)
             VALUES (?, ?, ?, ?, NULL, NULL)'
        );
        $ins->execute([$memberId, self::ym($y - 10), self::ym($y), $mtId]);

        self::assertTrue($repo->membershipTypeBlockedByPayments($mtId));
        try {
            $repo->deleteMembershipTypeOrFail($mtId);
            self::fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('payments in the current or future years', $e->getMessage());
        }
    }

    public function testMembershipTypeNotBlockedWhenOnlyPastYearPayments(): void
    {
        $ctx = self::sqliteWithMemberAndMt();
        $pdo = $ctx['pdo'];
        $mtId = $ctx['membershipTypeId'];
        $repo = $ctx['repo'];
        $memberId = (int) $pdo->query('SELECT id FROM members LIMIT 1')->fetchColumn();
        $pastY = ((int) (new \DateTimeImmutable('today'))->format('Y')) - 3;
        $ins = $pdo->prepare(
            'INSERT INTO payments (member_id, payment_date, paid_through, membership_type_id, notes, form_number)
             VALUES (?, ?, ?, ?, NULL, NULL)'
        );
        $ins->execute([$memberId, self::ym($pastY), self::ym($pastY), $mtId]);

        self::assertFalse($repo->membershipTypeBlockedByPayments($mtId));

        // Clear FK from members so we only assert the payments-year gate (not FK from members→types).
        $pdo->prepare('UPDATE members SET membership_type_id = NULL WHERE id = ?')->execute([$memberId]);

        $repo->deleteMembershipTypeOrFail($mtId);
        $c = $pdo->prepare('SELECT COUNT(*) FROM membership_types WHERE id = ?');
        self::assertNotFalse($c);
        $c->execute([$mtId]);

        self::assertSame(0, (int) $c->fetchColumn(), 'Membership type row should be deleted');
    }

    public function testCannotDeleteTheOnlyAdministrator(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::ensure($pdo);
        $repo = new AdminAccountRepository($pdo);
        $hash = password_hash('x', PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)')->execute(['solo', $hash]);
        $id = (int) $pdo->query('SELECT id FROM admin_users')->fetchColumn();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('only administrator');

        $repo->deleteAdminUserOrFail($id);
    }
}
