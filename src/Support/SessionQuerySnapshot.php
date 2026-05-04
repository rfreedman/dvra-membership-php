<?php

declare(strict_types=1);

namespace DvraMembership\Support;

/**
 * Shared session read/write + POST overlay merge for list/report pages that use a bare URL
 * (POST + redirect stores state; GET and exports read the snapshot).
 */
final class SessionQuerySnapshot
{
    /**
     * @return array<string, string|int|float>
     */
    public static function read(string $sessionKey): array
    {
        $s = $_SESSION[$sessionKey] ?? null;

        return \is_array($s) ? $s : [];
    }

    /**
     * @param array<string, string|int|float> $flat
     */
    public static function write(string $sessionKey, array $flat): void
    {
        $_SESSION[$sessionKey] = $flat;
    }

    /**
     * Merge POST body onto the saved snapshot. When $clearField is set and the body contains that key with value "1",
     * returns an empty array so callers can reset to defaults.
     *
     * @param array<string, mixed> $parsedBody
     *
     * @return array<string, string|int|float>
     */
    public static function mergePost(string $sessionKey, array $parsedBody, ?string $clearField): array
    {
        $flat = self::flattenScalarMap($parsedBody);
        if ($clearField !== null && (($flat[$clearField] ?? '') === '1')) {
            return [];
        }
        if ($clearField !== null) {
            unset($flat[$clearField]);
        }
        $snap = self::read($sessionKey);
        $merged = $snap;
        foreach ($flat as $k => $v) {
            $merged[$k] = $v;
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $qp
     *
     * @return array<string, string|int|float>
     */
    public static function flattenScalarMap(array $qp): array
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
}
