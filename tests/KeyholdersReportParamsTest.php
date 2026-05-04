<?php

declare(strict_types=1);

namespace DvraMembership\Tests;

use DvraMembership\Repository\ReportsRepository;
use DvraMembership\Support\KeyholdersReportParams;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class KeyholdersReportParamsTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testExportUsesPersistedSortIgnoringRequestQuery(): void
    {
        KeyholdersReportParams::persistFromParsed(ReportsRepository::parseKeyholdersQuery([
            'sort_by' => 'key_number',
            'sort_dir' => 'desc',
        ]));

        $request = (new ServerRequestFactory())->createServerRequest(
            'GET',
            'http://localhost/reports/keyholders/export.csv?sort_by=name&sort_dir=asc'
        );

        $resolved = KeyholdersReportParams::resolveForExport($request);

        self::assertSame('key_number', $resolved['sort_by']);
        self::assertSame('desc', $resolved['sort_dir']);
    }
}
