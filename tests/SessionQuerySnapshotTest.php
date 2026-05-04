<?php

declare(strict_types=1);

namespace DvraMembership\Tests;

use DvraMembership\Support\SessionQuerySnapshot;
use PHPUnit\Framework\TestCase;

final class SessionQuerySnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testMergePostClearsWhenFlagSet(): void
    {
        SessionQuerySnapshot::write('t1', ['a' => '1', 'b' => '2']);
        $out = SessionQuerySnapshot::mergePost('t1', ['clr' => '1', 'a' => '9'], 'clr');
        self::assertSame([], $out);
    }

    public function testMergePostOverlaysOntoSnapshot(): void
    {
        SessionQuerySnapshot::write('t2', ['sort_by' => 'name', 'sort_dir' => 'asc']);
        $out = SessionQuerySnapshot::mergePost('t2', ['sort_by' => 'email', 'sort_dir' => 'desc'], null);
        self::assertSame('email', $out['sort_by']);
        self::assertSame('desc', $out['sort_dir']);
    }
}
