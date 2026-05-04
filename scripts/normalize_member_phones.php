#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * One-off / maintenance: rewrite members.phone to NXX-NXX-XXXX (US 10-digit) or NULL.
 *
 * Usage: php scripts/normalize_member_phones.php
 * Uses DATABASE_DSN from the environment / defaults (see Settings).
 */

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use DvraMembership\Repository\MemberRepository;
use DvraMembership\Support\PdoFactory;
use DvraMembership\Support\Schema;
use DvraMembership\Support\Settings;

$settings = new Settings();
$pdo = PdoFactory::create($settings);
Schema::ensure($pdo);
$repo = new MemberRepository($pdo);
$n = $repo->normalizeStoredMemberPhonesToUsTenDigit();
fwrite(STDOUT, "Updated {$n} member row(s).\n");
