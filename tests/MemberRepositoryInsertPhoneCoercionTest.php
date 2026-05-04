<?php

declare(strict_types=1);

namespace DvraMembership\Tests;

use DvraMembership\Repository\MemberRepository;
use DvraMembership\Support\Schema;
use PDO;
use PHPUnit\Framework\TestCase;

final class MemberRepositoryInsertPhoneCoercionTest extends TestCase
{
    public function testInsertMemberCoercesPhoneWhenRowBypassesFormNormalizer(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::ensure($pdo);
        $pdo->exec("INSERT INTO membership_types (name, label) VALUES ('Annual', '')");
        $mtId = (int) $pdo->query('SELECT id FROM membership_types LIMIT 1')->fetchColumn();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $data = [
            'last_name' => 'Z',
            'first_name' => 'T',
            'call_sign' => null,
            'email' => null,
            'phone' => '(201) 555-0199',
            'address_street' => null,
            'address_city' => null,
            'address_state' => null,
            'address_zip' => null,
            'license_class_id' => null,
            'membership_type_id' => $mtId,
            'arrl_member' => false,
            'key_number' => null,
            'paid_through' => null,
        ];

        $repo = new MemberRepository($pdo);
        $id = $repo->insertMember($data);
        $stmt = $pdo->prepare('SELECT phone FROM members WHERE id = ?');
        $stmt->execute([$id]);
        self::assertSame('201-555-0199', $stmt->fetchColumn());
    }
}
