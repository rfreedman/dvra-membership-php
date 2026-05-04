<?php

declare(strict_types=1);

namespace DvraMembership\Support;

use DvraMembership\Repository\MemberListRepository;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Session-backed members list filters and sort: POST / updates the snapshot; GET / reads it;
 * exports use the same snapshot (bare export URLs). Client-side Tabulator sort POSTs to
 * mergeClientSortIntoSession without a full page reload.
 */
final class MemberListExportParams
{
    private const SESSION_KEY = 'dvra_members_last_export_query_input';

    /**
     * Snapshot the fully-resolved list state (canonical keys for parseListQuery).
     *
     * @param array<string, mixed> $parsed Output of MemberListRepository::parseListQuery
     */
    public static function persistFromParsed(array $parsed): void
    {
        SessionQuerySnapshot::write(self::SESSION_KEY, MemberListRepository::listParamsToQueryInput($parsed));
    }

    /**
     * Saved list keys for parseListQuery (empty until the first successful snapshot).
     *
     * @return array<string, string|int|float>
     */
    public static function persistedMembersListQueryInput(): array
    {
        $s = SessionQuerySnapshot::read(self::SESSION_KEY);

        return self::snapshotIsValid($s) ? $s : [];
    }

    /**
     * Merge POST onto the snapshot, or clear to defaults when reset_list_filters is set.
     *
     * @param array<string, mixed> $parsedBody $request->getParsedBody()
     *
     * @return array<string, string|int|float>
     */
    public static function mergedFlatAfterMemberListPost(array $parsedBody): array
    {
        return SessionQuerySnapshot::mergePost(self::SESSION_KEY, $parsedBody, 'reset_list_filters');
    }

    /**
     * Apply Tabulator column sort to the session (sort_by / sort_dir only) for export alignment.
     *
     * @param array<string, mixed> $parsedBody
     */
    public static function mergeClientSortIntoSession(array $parsedBody): void
    {
        $snap = SessionQuerySnapshot::read(self::SESSION_KEY);
        if (!self::snapshotIsValid($snap)) {
            $snap = MemberListRepository::listParamsToQueryInput(MemberListRepository::parseListQuery([]));
        }
        $flat = SessionQuerySnapshot::flattenScalarMap($parsedBody);
        foreach (['sort_by', 'sort_dir'] as $k) {
            if (\array_key_exists($k, $flat)) {
                $snap[$k] = $flat[$k];
            }
        }
        self::persistFromParsed(MemberListRepository::parseListQuery($snap));
    }

    /** @param array<string, mixed>|null $snapshot From listParamsToQueryInput */
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
        unset($request);

        return MemberListRepository::parseListQuery(self::persistedMembersListQueryInput());
    }
}
