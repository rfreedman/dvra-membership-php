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
 * Rows from PaymentsReportRepository::listPaymentReportRows (aligned with Python payment export).
 */
final class PaymentsReportExport
{
    /** @var list<string> Keys on each $row map (snake_case) */
    private const ROW_KEYS = [
        'member_id',
        'member_name',
        'call_sign',
        'payment_date',
        'paid_through',
        'membership_type',
        'form_number',
        'notes',
    ];

    /** @var list<string> Spreadsheet row 1 only; order matches ROW_KEYS */
    private const DISPLAY_HEADERS = [
        'Member ID',
        'Member name',
        'Call sign',
        'Payment date',
        'Paid through',
        'Membership type',
        'Form #',
        'Notes',
    ];

    /** @param list<array<string, mixed>> $rows */
    public static function toCsvBinary(array $rows): string
    {
        $ss = self::spreadsheetFromRows(self::DISPLAY_HEADERS, $rows, 'Report');
        ob_start();
        $w = new CsvWriter($ss);
        $w->setUseBOM(true);
        $w->save('php://output');

        return (string) ob_get_clean();
    }

    /** @param list<array<string, mixed>> $rows */
    public static function toXlsxBinary(array $rows): string
    {
        $ss = self::spreadsheetFromRows(self::DISPLAY_HEADERS, $rows, 'Payment report');
        ob_start();
        (new Xlsx($ss))->save('php://output');

        return (string) ob_get_clean();
    }

    /** @param list<array<string, mixed>> $rows */
    public static function toPdfBinary(array $rows): string
    {
        $pdf = new FPDF('L', 'mm', 'Letter');
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 8, self::latin1Approx('Payment report (filtered export)'));
        $pdf->Ln(10);
        $pdf->SetFont('Helvetica', '', 8);
        $headers = ['Member', 'Call', 'Pay date', 'Paid through', 'Type', 'Form #', 'Notes'];
        $colWidths = [50, 20, 24, 24, 30, 16, 100];
        foreach ($headers as $i => $h) {
            $pdf->Cell($colWidths[$i], 7, self::latin1Approx($h), 1);
        }
        $pdf->Ln();
        $pdf->SetFont('Helvetica', '', 7);
        foreach ($rows as $r) {
            $cells = [
                substr(self::cell($r['member_name'] ?? null), 0, 52),
                substr(self::cell($r['call_sign'] ?? null), 0, 12),
                self::cell($r['payment_date'] ?? null),
                self::cell($r['paid_through'] ?? null),
                substr(self::cell($r['membership_type'] ?? null), 0, 24),
                substr(self::cell($r['form_number'] ?? null), 0, 12),
                substr(self::cell($r['notes'] ?? null), 0, 400),
            ];
            foreach ($cells as $i => $cell) {
                $pdf->Cell($colWidths[$i], 6, self::latin1Approx($cell), 1);
            }
            $pdf->Ln();
        }

        return (string) $pdf->Output('S');
    }

    /** Python-style: payments-report-YYYYMMDD-HHMMSS */
    public static function fileStem(): string
    {
        return 'payments-report-' . (new \DateTimeImmutable())->format('Ymd-His');
    }

    /**
     * @param list<string> $headerTitles
     * @param list<array<string, mixed>> $rows
     */
    private static function spreadsheetFromRows(array $headerTitles, array $rows, string $sheetTitle): Spreadsheet
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle(substr($sheetTitle, 0, 31));
        foreach ($headerTitles as $i => $title) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . '1', $title);
        }
        $keys = self::ROW_KEYS;
        $rowIndex = 2;
        foreach ($rows as $r) {
            foreach ($keys as $c => $key) {
                $coord = Coordinate::stringFromColumnIndex($c + 1) . $rowIndex;
                $sheet->setCellValueExplicit($coord, self::cell($r[$key] ?? null), DataType::TYPE_STRING);
            }
            ++$rowIndex;
        }

        return $ss;
    }

    private static function cell(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return is_scalar($value) ? trim((string) $value) : '';
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
