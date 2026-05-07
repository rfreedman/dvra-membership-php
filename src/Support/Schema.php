<?php

declare(strict_types=1);

namespace DvraMembership\Support;

use PDO;
use RuntimeException;

final class Schema
{
    public static function ensure(PDO $pdo): void
    {
        $path = dirname(__DIR__, 2) . '/database/schema.sqlite.sql';
        if (!is_readable($path)) {
            throw new RuntimeException('Missing schema file: ' . $path);
        }
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Cannot read schema: ' . $path);
        }
        foreach (self::splitStatements($sql) as $stmt) {
            if ($stmt !== '') {
                $pdo->exec($stmt);
            }
        }

        self::migrateDropLicenseAndMembershipLabels($pdo);
    }

    /**
     * Older DB files may still have `label` on reference tables (removed from schema).
     * Requires SQLite 3.35+ ALTER TABLE DROP COLUMN.
     */
    private static function migrateDropLicenseAndMembershipLabels(PDO $pdo): void
    {
        $targets = ['license_classes', 'membership_types'];

        foreach ($targets as $table) {
            if (!self::sqliteTableHasColumn($pdo, $table, 'label')) {
                continue;
            }

            // Whitelist identifiers only — table names above are fixed.
            $pdo->exec('ALTER TABLE ' . $table . ' DROP COLUMN label');
        }
    }

    private static function sqliteTableHasColumn(PDO $pdo, string $table, string $column): bool
    {
        $tables = ['license_classes' => true, 'membership_types' => true];
        if (!isset($tables[$table])) {
            return false;
        }

        $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
        if (!$stmt) {
            return false;
        }

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (($row['name'] ?? '') === $column) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function splitStatements(string $sql): array
    {
        $lines = preg_split('/\R/', $sql) ?: [];
        $out = [];
        $buf = '';
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '' || str_starts_with($t, '--')) {
                continue;
            }
            $buf .= ' ' . $line;
            if (str_ends_with(rtrim($line), ';')) {
                $out[] = trim(rtrim($buf, " \t\n\r\0\x0B;"));
                $buf = '';
            }
        }
        $buf = trim($buf);
        if ($buf !== '') {
            $out[] = $buf;
        }
        return $out;
    }
}
