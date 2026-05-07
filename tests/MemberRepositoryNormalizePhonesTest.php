<?php

declare(strict_types=1);

namespace DvraMembership\Tests;

use DvraMembership\Repository\MemberRepository;
use DvraMembership\Support\Schema;
use PDO;
use PHPUnit\Framework\TestCase;

final class MemberRepositoryNormalizePhonesTest extends TestCase
{
    public function testNormalizeStoredMemberPhonesRewritesAndClears(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::ensure($pdo);
        $pdo->exec("INSERT INTO membership_types (name) VALUES ('Annual')");
        $mtId = (int) $pdo->query('SELECT id FROM membership_types LIMIT 1')->fetchColumn();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $ins = $pdo->prepare(
            'INSERT INTO members (last_name, first_name, phone, membership_type_id, license_class_id, arrl_member, created_at, updated_at)
             VALUES (?, ?, ?, ?, NULL, 0, ?, ?)'
        );
        $ins->execute(['A', 'One', '(555) 111-2222', $mtId, $now, $now]);
        $ins->execute(['B', 'Two', '+1 5553334444', $mtId, $now, $now]);
        $ins->execute(['C', 'Three', 'not-a-phone', $mtId, $now, $now]);

        $repo = new MemberRepository($pdo);
        $n = $repo->normalizeStoredMemberPhonesToUsTenDigit();
        self::assertSame(3, $n);

        $phones = $pdo->query('SELECT last_name, phone FROM members ORDER BY last_name')->fetchAll(PDO::FETCH_KEY_PAIR);
        self::assertSame('555-111-2222', $phones['A']);
        self::assertSame('555-333-4444', $phones['B']);
        self::assertNull($phones['C']);
    }
}
