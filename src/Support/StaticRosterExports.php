<?php

declare(strict_types=1);

namespace DvraMembership\Support;

use FPDF;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Keyholders + roster exports (Python /reports/keyholders, roster-by-name, roster-by-callsign). */
final class StaticRosterExports
{
    /**
     * @param list<array{id: int, key_number: int, call_sign: string, last_name: string, first_name: string, email: string}> $rows
     */
    public static function keyholdersCsvBinary(array $rows): string
    {
        return self::deliverCsv(
            ['Member ID', 'Key #', 'Call sign', 'Last name', 'First name', 'Email'],
            array_map(static fn (array $r): array => [
                $r['id'],
                $r['key_number'],
                $r['call_sign'],
                $r['last_name'],
                $r['first_name'],
                $r['email'],
            ], $rows)
        );
    }

    /** @param list<array{id: int, key_number: int, call_sign: string, last_name: string, first_name: string, email: string}> $rows */
    public static function keyholdersXlsxBinary(array $rows): string
    {
        return self::deliverXlsx(
            ['Member ID', 'Key #', 'Call sign', 'Last name', 'First name', 'Email'],
            array_map(static fn (array $r): array => [
                $r['id'],
                $r['key_number'],
                $r['call_sign'],
                $r['last_name'],
                $r['first_name'],
                $r['email'],
            ], $rows),
            'Keyholders'
        );
    }

    /** @param list<array{id: int, key_number: int, call_sign: string, last_name: string, first_name: string, email: string}> $rows */
    public static function keyholdersPdfBinary(array $rows): string
    {
        return self::landscapePdf(
            'Keyholders report',
            ['Member ID', 'Key #', 'Call', 'Last name', 'First name', 'Email'],
            [22, 18, 28, 42, 42, 120],
            array_map(static fn (array $m): array => [
                (string) $m['id'],
                (string) $m['key_number'],
                substr($m['call_sign'], 0, 14),
                substr($m['last_name'], 0, 40),
                substr($m['first_name'], 0, 40),
                substr($m['email'], 0, 80),
            ], $rows)
        );
    }

    /** @param list<array{last_name: string, first_name: string, call_sign: string}> $rows */
    public static function rosterByNameCsvBinary(array $rows): string
    {
        return self::deliverCsv(
            ['Last name', 'First name', 'Call sign'],
            array_map(static fn (array $r): array => [$r['last_name'], $r['first_name'], $r['call_sign']], $rows)
        );
    }

    /** @param list<array{last_name: string, first_name: string, call_sign: string}> $rows */
    public static function rosterByNameXlsxBinary(array $rows): string
    {
        return self::deliverXlsx(
            ['Last name', 'First name', 'Call sign'],
            array_map(static fn (array $r): array => [$r['last_name'], $r['first_name'], $r['call_sign']], $rows),
            'Roster by name'
        );
    }

    /** @param list<array{last_name: string, first_name: string, call_sign: string}> $rows */
    public static function rosterByNamePdfBinary(array $rows): string
    {
        return self::landscapePdf(
            'Roster by name',
            ['Last name', 'First name', 'Call sign'],
            [70, 70, 130],
            array_map(static fn (array $m): array => [
                substr($m['last_name'], 0, 48),
                substr($m['first_name'], 0, 48),
                substr($m['call_sign'], 0, 20),
            ], $rows)
        );
    }

    /** @param list<array{call_sign: string, last_name: string, first_name: string}> $rows */
    public static function rosterByCallsignCsvBinary(array $rows): string
    {
        return self::deliverCsv(
            ['Call sign', 'Last name', 'First name'],
            array_map(static fn (array $r): array => [$r['call_sign'], $r['last_name'], $r['first_name']], $rows)
        );
    }

    /** @param list<array{call_sign: string, last_name: string, first_name: string}> $rows */
    public static function rosterByCallsignXlsxBinary(array $rows): string
    {
        return self::deliverXlsx(
            ['Call sign', 'Last name', 'First name'],
            array_map(static fn (array $r): array => [$r['call_sign'], $r['last_name'], $r['first_name']], $rows),
            'Roster by callsign'
        );
    }

    /** @param list<array{call_sign: string, last_name: string, first_name: string}> $rows */
    public static function rosterByCallsignPdfBinary(array $rows): string
    {
        return self::landscapePdf(
            'Roster by callsign',
            ['Call sign', 'Last name', 'First name'],
            [40, 70, 160],
            array_map(static fn (array $m): array => [
                substr($m['call_sign'], 0, 14),
                substr($m['last_name'], 0, 48),
                substr($m['first_name'], 0, 48),
            ], $rows)
        );
    }

    public static function filenameStem(string $base): string
    {
        return $base . '-' . (new \DateTimeImmutable())->format('Ymd-His');
    }

    /**
     * @param list<string> $headers @param list<list<mixed>> $rows
     */
    private static function deliverCsv(array $headers, array $rows): string
    {
        $ss = self::spreadsheetFromMatrix($headers, $rows, 'Sheet1');
        ob_start();
        $w = new CsvWriter($ss);
        $w->setUseBOM(true);
        $w->save('php://output');

        return (string) ob_get_clean();
    }

    /** @param list<string> $headers @param list<list<mixed>> $rows */
    private static function deliverXlsx(array $headers, array $rows, string $title): string
    {
        $ss = self::spreadsheetFromMatrix($headers, $rows, substr($title, 0, 31));
        ob_start();
        (new Xlsx($ss))->save('php://output');

        return (string) ob_get_clean();
    }

    /** @param list<string> $headers @param list<list<string>> $dataRows */
    private static function landscapePdf(string $heading, array $headers, array $widths, array $dataRows): string
    {
        $pdf = new FPDF('L', 'mm', 'Letter');
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 8, self::latin1Approx($heading));
        $pdf->Ln(10);
        $pdf->SetFont('Helvetica', 'B', 8);
        foreach ($headers as $i => $h) {
            $pdf->Cell((float) $widths[$i], 7, self::latin1Approx($h), 1);
        }
        $pdf->Ln();
        $pdf->SetFont('Helvetica', '', 7);
        foreach ($dataRows as $row) {
            foreach ($row as $i => $cell) {
                $pdf->Cell((float) $widths[$i], 6, self::latin1Approx($cell), 1);
            }
            $pdf->Ln();
        }

        return (string) $pdf->Output('S');
    }

    /** @param list<string> $headerRow @param list<list<mixed>> $body */
    private static function spreadsheetFromMatrix(array $headerRow, array $body, string $sheetTitle): Spreadsheet
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $safeTitle = preg_match('/[<>:\\/\\\\?*\[\]]/', $sheetTitle) ? 'Report' : $sheetTitle;
        $sheet->setTitle(substr((string) ($safeTitle ?? 'Report'), 0, 31));
        foreach ($headerRow as $i => $h) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . '1', (string) $h);
        }
        $rowIndex = 2;
        foreach ($body as $line) {
            foreach ($line as $c => $val) {
                $coord = Coordinate::stringFromColumnIndex($c + 1) . $rowIndex;
                $sheet->setCellValueExplicit($coord, is_scalar($val) ? trim((string) $val) : '', DataType::TYPE_STRING);
            }
            ++$rowIndex;
        }

        return $ss;
    }

    private static function latin1Approx(string $s): string
    {
        $c = iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
        if ($c === false) {
            return preg_replace('/[^\x09\x20-\x7E]/', '?', $s) ?? '?';
        }

        return $c;
    }
}
