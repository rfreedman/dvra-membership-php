<?php

declare(strict_types=1);

namespace DvraMembership\Tests;

use DvraMembership\Repository\MemberListRepository;
use DvraMembership\Support\MemberListExportParams;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class MemberListExportParamsTest extends TestCase
{
    private const SESSION_KEY = 'dvra_members_last_export_query_input';

    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testExportUsesPersistedSessionOnly(): void
    {
        MemberListExportParams::persistFromParsed(MemberListRepository::parseListQuery([
            'sort_by' => 'last_name',
            'sort_dir' => 'asc',
            'current_only' => 'no',
            'search' => '',
            'membership_type_id' => null,
            'arrl' => '',
        ]));

        $request = (new ServerRequestFactory())->createServerRequest(
            'GET',
            'http://localhost/members/export.csv?current_only=yes&sort_by=call_sign&sort_dir=desc'
        );

        $resolved = MemberListExportParams::resolveForExport($request);

        self::assertSame('no', $resolved['current_only']);
        self::assertSame('last_name', $resolved['sort_by']);
        self::assertSame('asc', $resolved['sort_dir']);
    }

    public function testMergeClientSortIntoSessionUpdatesSort(): void
    {
        MemberListExportParams::persistFromParsed(MemberListRepository::parseListQuery([
            'sort_by' => 'last_name',
            'sort_dir' => 'asc',
            'current_only' => 'yes',
            'search' => 'x',
            'membership_type_id' => null,
            'arrl' => '',
        ]));

        MemberListExportParams::mergeClientSortIntoSession([
            'sort_by' => 'call_sign',
            'sort_dir' => 'desc',
        ]);

        $snap = $_SESSION[self::SESSION_KEY] ?? [];
        self::assertSame('call_sign', $snap['sort_by'] ?? null);
        self::assertSame('desc', $snap['sort_dir'] ?? null);
        self::assertSame('x', $snap['search'] ?? null);
        self::assertSame('yes', $snap['current_only'] ?? null);
    }

    public function testBuildExportQueryStringAlwaysIncludesCurrentOnly(): void
    {
        $p = MemberListRepository::parseListQuery([
            'sort_by' => 'last_name',
            'sort_dir' => 'asc',
        ]);
        $qs = MemberListRepository::buildExportQueryString($p);

        parse_str(trim($qs, '?'), $out);

        self::assertSame('yes', $out['current_only'] ?? null);
        self::assertSame('last_name', $out['sort_by']);
    }
}
