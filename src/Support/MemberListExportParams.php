<?php

declare(strict_types=1);

namespace DvraMembership\Support;

use DvraMembership\Repository\MemberListRepository;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Determines export/list parameters from GET + optional session fallback so exports cannot drift from the UI
 * when the browser omits default query keys (especially current_only=yes).
 */
final class MemberListExportParams
{
    private const SESSION_KEY = 'dvra_members_last_export_query_input';

    /**
     * Snapshot the fully-resolved GET state after rendering the members page (canonical keys).
     *
     * @param array<string, mixed> $parsed Output of MemberListRepository::parseListQuery
     */
    public static function persistFromParsed(array $parsed): void
    {
        $_SESSION[self::SESSION_KEY] = MemberListRepository::listParamsToQueryInput($parsed);
    }

    /** @param array<string, mixed> $snapshot From listParamsToQueryInput */
    public static function snapshotIsValid(?array $snapshot): bool
    {
        if ($snapshot === null || $snapshot === []) {
            return false;
        }
        foreach (['sort_by', 'sort_dir', 'current_only'] as $k) {
            if (!isset($snapshot[$k]) || (!\is_string($snapshot[$k]) && !\is_int($snapshot[$k]) && !\is_float($snapshot[$k]))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array Same shape as MemberListRepository::parseListQuery
     */
    public static function resolveForExport(Request $request): array
    {
        $http = self::flattenQueryParams($request->getQueryParams());
        $raw = $request->getUri()->getQuery();

        return self::resolveMergedExportQueryFromFlat($http, $raw);
    }

    /**
     * @param array<string, string|int|float> $httpFlat
     *
     * @return array Same shape as MemberListRepository::parseListQuery
     */
    public static function resolveMergedExportQueryFromFlat(array $httpFlat, string $rawUriQuery): array
    {
        $merged = self::flattenQueryParams(array_map(static fn (mixed $v): mixed => $v, $httpFlat));
        self::pinCurrentOnlyFromRawQuery($merged, $rawUriQuery);

        $snap = isset($_SESSION[self::SESSION_KEY]) && \is_array($_SESSION[self::SESSION_KEY])
            ? $_SESSION[self::SESSION_KEY]
            : null;
        if (self::snapshotIsValid($snap)) {
            /** @var array<string, mixed> $snap */
            foreach ($snap as $k => $v) {
                if (!\array_key_exists($k, $merged)) {
                    $merged[$k] = $v;
                }
            }
            self::pinCurrentOnlyFromRawQuery($merged, $rawUriQuery);
        }

        return MemberListRepository::parseListQuery($merged);
    }

    /**
     * PSR query params repeat as arrays; Slim normalizes arrays for repeated keys — take scalar / last element when needed.
     *
     * @param array<string, mixed> $qp
     * @return array<string, string|int|float>
     */
    private static function flattenQueryParams(array $qp): array
    {
        $flat = [];
        foreach ($qp as $k => $v) {
            if (\is_array($v)) {
                $v = end($v);
                if ($v === false) {
                    continue;
                }
            }
            if (\is_scalar($v)) {
                $flat[(string) $k] = $v;
            }
        }

        return $flat;
    }

    /** @param array<string, string|int|float> $mergeTarget */
    private static function pinCurrentOnlyFromRawQuery(array &$mergeTarget, string $rawQuery): void
    {
        if (\array_key_exists('current_only', $mergeTarget)) {
            return;
        }
        if ($rawQuery === '') {
            return;
        }
        if (preg_match_all('/(^|&)current_only=(yes|no)(?=(&|$))/i', str_replace('&amp;', '&', $rawQuery), $matches)) {
            $last = strtolower((string) end($matches[2]));
            $mergeTarget['current_only'] = $last;
        }
    }
}
