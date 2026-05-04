<?php

declare(strict_types=1);

namespace DvraMembership\Support;

use DvraMembership\Repository\ReportsRepository;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Session-backed keyholders report sort (bare /reports/keyholders URL; exports use the same snapshot). */
final class KeyholdersReportParams
{
    private const SESSION_KEY = 'dvra_keyholders_report_sort';

    /** @param array{sort_by: string, sort_dir: string} $parsed */
    public static function persistFromParsed(array $parsed): void
    {
        SessionQuerySnapshot::write(self::SESSION_KEY, [
            'sort_by' => $parsed['sort_by'],
            'sort_dir' => $parsed['sort_dir'],
        ]);
    }

    /**
     * Flat input for parseKeyholdersQuery (empty before first visit writes defaults).
     *
     * @return array<string, string|int|float>
     */
    public static function persistedSortInput(): array
    {
        return SessionQuerySnapshot::read(self::SESSION_KEY);
    }

    /**
     * @param array<string, mixed> $parsedBody
     *
     * @return array<string, string|int|float>
     */
    public static function mergedFlatAfterPost(array $parsedBody): array
    {
        return SessionQuerySnapshot::mergePost(self::SESSION_KEY, $parsedBody, null);
    }

    /** @return array{sort_by: string, sort_dir: string} */
    public static function resolveForExport(Request $request): array
    {
        unset($request);

        return ReportsRepository::parseKeyholdersQuery(self::persistedSortInput());
    }
}
