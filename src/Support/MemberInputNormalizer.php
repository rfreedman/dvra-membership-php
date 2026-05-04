<?php

declare(strict_types=1);

namespace DvraMembership\Support;

/**
 * Mirrors Python pydantic validators on MemberCreate/MemberUpdate (schemas.py).
 */
final class MemberInputNormalizer
{
    public static function normalizeCallSign(?string $raw): ?string
    {
        $s = $raw !== null ? strtoupper(trim($raw)) : '';

        return $s !== '' ? $s : null;
    }

    public static function stripOptional(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = trim($raw);

        return $s !== '' ? $s : null;
    }

    /**
     * US 10-digit NANP only: strip non-digits, drop a single leading country code 1, format NXX-NXX-XXXX.
     * Returns null when empty or when digits are not exactly 10 (after optional leading 1).
     */
    public static function normalizePhoneUsTenDigit(?string $raw): ?string
    {
        $s = $raw !== null ? trim($raw) : '';
        if ($s === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $s);
        if ($digits === null || $digits === '') {
            return null;
        }
        if (\strlen($digits) === 11 && $digits[0] === '1') {
            $digits = substr($digits, 1);
        }
        if (\strlen($digits) !== 10) {
            return null;
        }

        return substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6, 4);
    }

    /** Two-letter USPS-style → uppercase; else cap at 16 chars. */
    public static function normalizeState(?string $raw): ?string
    {
        $s = self::stripOptional($raw);
        if ($s === null) {
            return null;
        }
        $u = strtoupper($s);
        if (preg_match('/^[A-Z]{2}$/', $u) === 1) {
            return $u;
        }

        return substr($u, 0, 16);
    }

    /** Collapse spaces; keep if matches US ZIP patterns. */
    public static function normalizeZip(?string $raw): ?string
    {
        $s = self::stripOptional($raw);
        if ($s === null) {
            return null;
        }
        $compact = str_replace(' ', '', $s);
        if (preg_match('/^[0-9]{5}(?:-[0-9]{4})?$/', $compact) === 1) {
            return $compact;
        }

        return $s;
    }

    /**
     * @return array{ok: bool, date: ?string} ok=false when non-empty raw is not a parseable ISO date string
     */
    public static function parseOptionalPaidThrough(string $raw): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return ['ok' => true, 'date' => null];
        }
        try {
            $d = new \DateTimeImmutable($trimmed);

            return ['ok' => true, 'date' => $d->format('Y-m-d')];
        } catch (\Throwable) {
            return ['ok' => false, 'date' => null];
        }
    }

    /** @return array{last_name: string, first_name: string, call_sign: ?string, email: ?string, phone: ?string, address_street: ?string, address_city: ?string, address_state: ?string, address_zip: ?string, license_class_id: ?int, membership_type_id: ?int, arrl_member: bool, key_number: ?int, paid_through: ?string} phone: US NXX-NXX-XXXX or null; paid_through when caller passed parseOptionalPaidThrough */
    public static function memberCreateFromForm(array $body, ?string $paidThroughIsoOrNull): array
    {
        $keyRaw = isset($body['key_number']) ? trim((string) $body['key_number']) : '';
        $lic = isset($body['license_class']) ? trim((string) $body['license_class']) : '';
        $mt = isset($body['membership_type']) ? trim((string) $body['membership_type']) : '';

        return [
            'last_name' => isset($body['last_name']) ? trim((string) $body['last_name']) : '',
            'first_name' => isset($body['first_name']) ? trim((string) $body['first_name']) : '',
            'call_sign' => self::normalizeCallSign(isset($body['call_sign']) ? (string) $body['call_sign'] : null),
            'email' => self::stripOptional(isset($body['email']) ? (string) $body['email'] : null),
            'phone' => self::normalizePhoneUsTenDigit(isset($body['phone']) ? (string) $body['phone'] : null),
            'address_street' => self::stripOptional(isset($body['address_street']) ? (string) $body['address_street'] : null),
            'address_city' => self::stripOptional(isset($body['address_city']) ? (string) $body['address_city'] : null),
            'address_state' => self::normalizeState(isset($body['address_state']) ? (string) $body['address_state'] : null),
            'address_zip' => self::normalizeZip(isset($body['address_zip']) ? (string) $body['address_zip'] : null),
            'license_class_id' => $lic !== '' && ctype_digit($lic) ? (int) $lic : null,
            'membership_type_id' => $mt !== '' && ctype_digit($mt) ? (int) $mt : null,
            'arrl_member' => (isset($body['arrl_member']) ? (string) $body['arrl_member'] : '') === 'yes',
            'key_number' => $keyRaw !== '' && ctype_digit($keyRaw) ? (int) $keyRaw : null,
            'paid_through' => $paidThroughIsoOrNull,
        ];
    }

    /** Same as create but excludes paid_through; used on edit POST. */
    public static function memberUpdateFromForm(array $body): array
    {
        $parsed = self::memberCreateFromForm(array_merge($body, ['paid_through' => '']), null);

        unset($parsed['paid_through']);

        return $parsed;
    }

    /**
     * Payment create/edit form (PaymentCreate).
     *
     * @param array<string, mixed> $body
     * @return array{ok: bool, error: string, data?: array{payment_date: string, paid_through: string, membership_type_id: ?int, notes: ?string, form_number: ?string}}
     */
    public static function paymentFromForm(array $body): array
    {
        $pdRaw = isset($body['payment_date']) ? trim((string) $body['payment_date']) : '';
        $ptRaw = isset($body['paid_through']) ? trim((string) $body['paid_through']) : '';
        if ($pdRaw === '' || $ptRaw === '') {
            return ['ok' => false, 'error' => 'Payment date and paid-through date are required.'];
        }
        try {
            $paymentDate = (new \DateTimeImmutable($pdRaw))->format('Y-m-d');
            $paidThrough = (new \DateTimeImmutable($ptRaw))->format('Y-m-d');
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Invalid payment or paid-through date.'];
        }

        $mtRaw = isset($body['membership_type']) ? trim((string) $body['membership_type']) : '';
        $membershipTypeId = $mtRaw !== '' && ctype_digit($mtRaw) ? (int) $mtRaw : null;

        $notesRaw = isset($body['notes']) ? trim((string) $body['notes']) : '';
        $notes = $notesRaw !== '' ? $notesRaw : null;

        $fnRaw = isset($body['form_number']) ? trim((string) $body['form_number']) : '';
        $formNumber = $fnRaw !== '' ? $fnRaw : null;

        return [
            'ok' => true,
            'error' => '',
            'data' => [
                'payment_date' => $paymentDate,
                'paid_through' => $paidThrough,
                'membership_type_id' => $membershipTypeId,
                'notes' => $notes,
                'form_number' => $formNumber,
            ],
        ];
    }

    public static function referenceLabel(?string $name, ?string $label): string
    {
        $lab = trim((string) ($label ?? ''));
        if ($lab !== '') {
            return $lab;
        }

        return trim((string) ($name ?? ''));
    }
}
