<?php

declare(strict_types=1);

namespace DvraMembership\Tests;

use DvraMembership\Support\MemberInputNormalizer;
use PHPUnit\Framework\TestCase;

final class MemberInputNormalizerPhoneTest extends TestCase
{
    public static function phoneCases(): array
    {
        return [
            'null' => [null, null],
            'empty' => ['', null],
            'spaces' => ['   ', null],
            'formatted' => ['(555) 123-4567', '555-123-4567'],
            'digits only' => ['5551234567', '555-123-4567'],
            'plus one spaced' => ['+1 555 123 4567', '555-123-4567'],
            'leading one no plus' => ['15551234567', '555-123-4567'],
            'already dashed' => ['555-123-4567', '555-123-4567'],
            'non us too many digits' => ['+44 20 7946 0958', null],
            'garbage' => ['call me', null],
        ];
    }

    /** @dataProvider phoneCases */
    public function testNormalizePhoneUsTenDigit(?string $in, ?string $expected): void
    {
        self::assertSame($expected, MemberInputNormalizer::normalizePhoneUsTenDigit($in));
    }
}
