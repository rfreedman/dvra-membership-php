<?php

declare(strict_types=1);

namespace DvraMembership\Support;

use PDO;

final class PdoFactory
{
    public static function create(Settings $settings): PDO
    {
        $dsn = $settings->get('DATABASE_DSN');
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        if (str_starts_with($dsn, 'sqlite:')) {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        return $pdo;
    }
}
