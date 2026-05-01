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
