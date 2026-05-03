<?php

declare(strict_types=1);

namespace DvraMembership\Support;

use FPDF;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exports rows shaped like MemberListRepository::listRowsForExport (no Actions, no trailing id column).
 */
final class MemberExport
{
    /** @var list<string> Column order matches members grid (excluding Actions); keys in row arrays */
    private const EXPORT_FIELD_KEYS = [
        'call_sign',
        'last_name',
        'first_name',
        'email',
        'phone',
        'address_street',
        'address_city',
        'address_state',
        'address_zip',
        'license_class',
        'membership_type',
        'arrl_member',
        'key_number',
        'paid_through',
    ];

    /** @var list<string> Header labels aligned with EXPORT_FIELD_KEYS */
    private const HEADERS = [
        'Call sign',
        'Last name',
        'First name',
        'Email',
        'Phone',
        'Street',
        'City',
        'St',
        'ZIP',
        'License class',
        'Membership type',
        'ARRL',
        'Key #',
        'Paid through',
    ];

    /** @param list<array<string, mixed>> $rows */
    public static function toCsvBinary(array $rows): string
    {
        $ss = self::spreadsheetFromRows($rows);
        ob_start();
        $w = new CsvWriter($ss);
        $w->setUseBOM(true);
        $w->save('php://output');

        return (string) ob_get_clean();
    }

    /** @param list<array<string, mixed>> $rows */
    public static function toXlsxBinary(array $rows): string
    {
        $ss = self::spreadsheetFromRows($rows);
        ob_start();
        (new Xlsx($ss))->save('php://output');

        return (string) ob_get_clean();
    }

    /** @param list<array<string, mixed>> $rows */
    public static function toPdfBinary(array $rows): string
    {
        /** @disregard unsafe new FPDF (global class from Composer) */
        $pdf = new FPDF('L', 'mm', 'Letter');
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 7);

        $colCount = \count(self::EXPORT_FIELD_KEYS);
        $w = [];
        foreach (range(1, $colCount) as $_) {
            $w[] = 266 / $colCount;
        }

        foreach (self::HEADERS as $i => $h) {
            $pdf->Cell($w[$i], 6, self::latin1Approx($h), 1);
        }
        $pdf->Ln();
        foreach ($rows as $r) {
            foreach (self::EXPORT_FIELD_KEYS as $i => $key) {
                $cell = self::latin1Approx(self::stringifyExportCell($key, $r[$key] ?? null));
                if (strlen($cell) > 80) {
                    $cell = substr($cell, 0, 77) . '...';
                }
                $pdf->Cell($w[$i], 5, $cell, 1);
            }
            $pdf->Ln();
        }

        return $pdf->Output('S');
    }

    public static function fileStem(): string
    {
        return 'members-' . (new \DateTimeImmutable())->format('Y-m-d-His');
    }

    /** @param list<array<string, mixed>> $rows */
    private static function spreadsheetFromRows(array $rows): Spreadsheet
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        foreach (self::HEADERS as $c => $header) {
            $coord = Coordinate::stringFromColumnIndex($c + 1) . '1';
            $sheet->setCellValue($coord, $header);
        }
        $rowIndex = 2;
        foreach ($rows as $r) {
            foreach (self::EXPORT_FIELD_KEYS as $c => $key) {
                $coord = Coordinate::stringFromColumnIndex($c + 1) . $rowIndex;
                $sheet->setCellValueExplicit($coord, self::stringifyExportCell($key, $r[$key] ?? null), DataType::TYPE_STRING);
            }
            ++$rowIndex;
        }

        return $ss;
    }

    /** @internal */
    public static function stringifyExportCell(string $fieldKey, mixed $value): string
    {
        if ($fieldKey === 'arrl_member') {
            return self::boolish($value) ? 'yes' : 'no';
        }
        if ($fieldKey === 'key_number') {
            return ($value !== null && $value !== '') ? (string) (int) $value : '';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function boolish(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }
        if (\is_int($value) || \is_float($value)) {
            return (int) $value !== 0;
        }
        $s = strtolower(trim((string) $value));

        return $s === '1' || $s === 'true' || $s === 'yes' || $s === 'on';
    }

    private static function latin1Approx(string $s): string
    {
        if ($s === '') {
            return '';
        }
        $c = iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
        if ($c === false) {
            return preg_replace('/[^\x09\x20-\x7E]/', '?', $s) ?? '?';
        }

        return $c;
    }
}
