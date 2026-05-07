<?php

declare(strict_types=1);

namespace DvraMembership\Repository;

use PDO;

final class AdminAccountRepository
{
    /** @internal */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array{id: int, username: string}> */
    public function listAdminUsers(): array
    {
        $stmt = $this->pdo->query('SELECT id, username FROM admin_users ORDER BY username ASC');
        if (!$stmt) {
            return [];
        }
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'username' => (string) $r['username'],
            ];
        }

        return $out;
    }

    public function createAdminUser(string $username, string $rawPassword): void
    {
        $hash = password_hash($rawPassword, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
        $stmt->execute([trim($username), $hash]);
    }

    public function updateAdminPassword(int $userId, string $rawPassword): void
    {
        $hash = password_hash($rawPassword, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $userId]);
    }

    /** @throws \RuntimeException When that would delete the sole admin row. */
    public function deleteAdminUserOrFail(int $userId): void
    {
        $cnt = (int) $this->pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        if ($cnt <= 1) {
            throw new \RuntimeException('Cannot delete the only administrator account.');
        }
        $stmt = $this->pdo->prepare('DELETE FROM admin_users WHERE id = ?');
        $stmt->execute([$userId]);
    }

    /** @return list<array{id: int, username: string, display_name: string|null}> */
    public function listManagers(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, username, display_name FROM managers ORDER BY username ASC'
        );
        if (!$stmt) {
            return [];
        }
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'username' => (string) $r['username'],
                'display_name' => isset($r['display_name']) && $r['display_name'] !== ''
                    ? (string) $r['display_name']
                    : null,
            ];
        }

        return $out;
    }

    public function createManager(string $username, string $rawPassword, ?string $displayName): void
    {
        $hash = password_hash($rawPassword, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare(
            'INSERT INTO managers (username, password_hash, display_name) VALUES (?, ?, ?)'
        );
        $stmt->execute([
            trim($username),
            $hash,
            $displayName !== null && trim($displayName) !== '' ? trim($displayName) : null,
        ]);
    }

    public function updateManagerPassword(int $managerId, string $rawPassword): void
    {
        $hash = password_hash($rawPassword, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('UPDATE managers SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $managerId]);
    }

    public function updateManagerProfile(int $managerId, ?string $displayName): void
    {
        $stmt = $this->pdo->prepare('UPDATE managers SET display_name = ? WHERE id = ?');
        $stmt->execute([
            $displayName !== null && trim($displayName) !== '' ? trim($displayName) : null,
            $managerId,
        ]);
    }

    public function deleteManager(int $managerId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM managers WHERE id = ?');
        $stmt->execute([$managerId]);
    }
}
