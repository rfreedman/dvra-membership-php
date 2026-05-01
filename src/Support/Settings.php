<?php

declare(strict_types=1);

namespace DvraMembership\Support;

/**
 * Environment-style settings (aligned with Python app env vars).
 */
final class Settings
{
    /** @var array<string, string> */
    private array $data;

    public function __construct()
    {
        $root = dirname(__DIR__, 2);
        $this->data = [
            'DATABASE_DSN' => getenv('DATABASE_DSN') ?: 'sqlite:' . $root . '/var/dvra_membership.sqlite',
            'DVRA_ADMIN_USERNAME' => self::nz(getenv('DVRA_ADMIN_USERNAME'), 'admin'),
            'DVRA_ADMIN_PASSWORD' => self::nz(getenv('DVRA_ADMIN_PASSWORD'), 'admin123'),
            'BASE_PATH' => self::nz(getenv('BASE_PATH'), ''),
        ];
    }

    private static function nz(string|false $v, string $default): string
    {
        if ($v === false || trim($v) === '') {
            return $default;
        }
        return trim($v);
    }

    public function get(string $key): string
    {
        return $this->data[$key] ?? '';
    }
}
