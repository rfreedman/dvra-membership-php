<?php

declare(strict_types=1);

namespace DvraMembership\Support;

use PDO;

final class AdminSeed
{
    public static function ensureBootstrapAdmin(PDO $pdo, Settings $settings): void
    {
        $n = (int) $pdo->query('SELECT COUNT(*) AS c FROM admin_users')->fetch()['c'];
        if ($n > 0) {
            return;
        }
        $user = $settings->get('DVRA_ADMIN_USERNAME');
        $pass = $settings->get('DVRA_ADMIN_PASSWORD');
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
        $stmt->execute([$user, $hash]);
    }
}
