<?php

declare(strict_types=1);

namespace DvraMembership\Support;

final class View
{
    public static function e(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $name, array $data = []): string
    {
        $path = dirname(__DIR__, 2) . '/templates/' . $name . '.php';
        if (!is_readable($path)) {
            return '<p>Missing template: ' . self::e($name) . '</p>';
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return (string) ob_get_clean();
    }
}
