<?php

declare(strict_types=1);

namespace DvraMembership\Tests;

use DvraMembership\Repository\MemberListRepository;
use DvraMembership\Support\MemberListExportParams;
use PHPUnit\Framework\TestCase;

final class MemberListExportParamsTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testMergedQueryFillsMissingCurrentOnlyFromSession(): void
    {
        $_SESSION = [];
        MemberListExportParams::persistFromParsed(MemberListRepository::parseListQuery([
            'sort_by' => 'last_name',
            'sort_dir' => 'asc',
            'current_only' => 'no',
            'search' => '',
        ]));

        $resolved = MemberListExportParams::resolveMergedExportQueryFromFlat(
            [
                'sort_by' => 'call_sign',
                'sort_dir' => 'desc',
            ],
            'sort_by=call_sign&sort_dir=desc'
        );

        $this->assertSame('no', $resolved['current_only']);
        $this->assertSame('call_sign', $resolved['sort_by']);
        $this->assertSame('desc', $resolved['sort_dir']);
    }

    public function testExplicitExportQueryOverridesSessionCurrentScope(): void
    {
        MemberListExportParams::persistFromParsed(MemberListRepository::parseListQuery([
            'current_only' => 'no',
            'sort_by' => 'last_name',
            'sort_dir' => 'asc',
        ]));

        $resolved = MemberListExportParams::resolveMergedExportQueryFromFlat(
            [
                'sort_by' => 'last_name',
                'sort_dir' => 'asc',
                'current_only' => 'yes',
            ],
            'current_only=yes&sort_by=last_name&sort_dir=asc'
        );

        $this->assertSame('yes', $resolved['current_only']);
    }

    public function testRawQueryPinsCurrentOnlyWhenMissingFromParsedParams(): void
    {
        $_SESSION = [];
        $resolved = MemberListExportParams::resolveMergedExportQueryFromFlat(
            [
                'sort_by' => 'last_name',
                'sort_dir' => 'asc',
            ],
            'sort_by=last_name&sort_dir=asc&current_only=no'
        );

        $this->assertSame('no', $resolved['current_only']);
    }

    public function testBuildExportQueryStringAlwaysIncludesCurrentOnly(): void
    {
        $p = MemberListRepository::parseListQuery([
            'sort_by' => 'last_name',
            'sort_dir' => 'asc',
        ]);
        $qs = MemberListRepository::buildExportQueryString($p);

        parse_str(trim($qs, '?'), $out);

        $this->assertSame('yes', $out['current_only'] ?? null);
        $this->assertSame('last_name', $out['sort_by']);
    }
}
