<?php

declare(strict_types=1);

namespace DvraMembership\Support;

use DvraMembership\Repository\PaymentsReportRepository;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Session-backed payment report filters and sort: the HTML page uses a bare URL; POST updates this snapshot
 * and redirects. Exports read the same snapshot (no query string required).
 */
final class PaymentsReportExportParams
{
    private const SESSION_KEY = 'dvra_payments_report_filters';

    /** @param array<string, mixed> $parsed Output of PaymentsReportRepository::parsePaymentReportQuery */
    public static function persistFromParsed(array $parsed): void
    {
        SessionQuerySnapshot::write(self::SESSION_KEY, PaymentsReportRepository::listParamsToQueryInput($parsed));
    }

    /**
     * Flat map suitable for parsePaymentReportQuery (empty when the user has not saved anything yet).
     *
     * @return array<string, string|int|float>
     */
    public static function persistedPaymentReportQueryInput(): array
    {
        return SessionQuerySnapshot::read(self::SESSION_KEY);
    }

    /**
     * Merge POST fields onto the saved snapshot (or replace with defaults when clear_filters is set).
     *
     * @param array<string, mixed> $parsedBody $request->getParsedBody()
     *
     * @return array<string, string|int|float>
     */
    public static function mergedFlatAfterPaymentReportPost(array $parsedBody): array
    {
        return SessionQuerySnapshot::mergePost(self::SESSION_KEY, $parsedBody, 'clear_filters');
    }

    /** @return array<string, mixed> */
    public static function resolveForExport(Request $request): array
    {
        unset($request);

        return PaymentsReportRepository::parsePaymentReportQuery(self::persistedPaymentReportQueryInput());
    }
}
