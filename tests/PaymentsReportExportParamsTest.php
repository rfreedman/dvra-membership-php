<?php

declare(strict_types=1);

namespace DvraMembership\Tests;

use DvraMembership\Repository\PaymentsReportRepository;
use DvraMembership\Support\PaymentsReportExportParams;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class PaymentsReportExportParamsTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testExportUsesPersistedSessionOnly(): void
    {
        PaymentsReportExportParams::persistFromParsed(PaymentsReportRepository::parsePaymentReportQuery([
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]));

        $request = (new ServerRequestFactory())->createServerRequest(
            'GET',
            'http://localhost/reports/payments/export.csv?start_date=2099-01-01'
        );

        $resolved = PaymentsReportExportParams::resolveForExport($request);

        self::assertSame('2025-01-01', $resolved['start_date']);
        self::assertSame('2025-12-31', $resolved['end_date']);
        self::assertSame('2025-01-01', $resolved['start_filter']);
        self::assertSame('2025-12-31', $resolved['end_filter']);
        self::assertSame('report_default', $resolved['sort_by']);
        self::assertSame('asc', $resolved['sort_dir']);
    }

    public function testBuildExportQueryOnlyNonEmptyKeys(): void
    {
        $p = PaymentsReportRepository::parsePaymentReportQuery([
            'start_date' => '2026-02-01',
            'end_date' => '',
        ]);
        $qs = trim(PaymentsReportRepository::buildExportQueryString($p), '?');
        parse_str($qs, $out);
        self::assertSame('2026-02-01', $out['start_date']);
        self::assertArrayNotHasKey('end_date', $out);
        self::assertSame('report_default', $out['sort_by']);
        self::assertSame('asc', $out['sort_dir']);
    }

    public function testMergedFlatAfterPostClearsWhenFlagSet(): void
    {
        PaymentsReportExportParams::persistFromParsed(PaymentsReportRepository::parsePaymentReportQuery([
            'start_date' => '2025-01-01',
        ]));
        $flat = PaymentsReportExportParams::mergedFlatAfterPaymentReportPost([
            'clear_filters' => '1',
            'start_date' => '2099-01-01',
        ]);
        self::assertSame([], $flat);
    }
}
