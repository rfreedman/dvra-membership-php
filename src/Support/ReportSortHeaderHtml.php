<?php

declare(strict_types=1);

namespace DvraMembership\Support;

/**
 * HTML for payment-report-style sortable table headers (label left, caret right, POST form).
 */
final class ReportSortHeaderHtml
{
    /**
     * @param array<string, array{sort_by: string, sort_dir: string}> $nextChoices field name => next POST sort params
     */
    public static function sortableTh(
        string $field,
        string $labelHuman,
        string $currentSortBy,
        string $currentSortDir,
        array $nextChoices,
        string $formAction,
    ): string {
        $h = static fn (?string $s): string => View::e($s);
        $choice = $nextChoices[$field] ?? ['sort_by' => $currentSortBy, 'sort_dir' => $currentSortDir];
        $ariaSort = (string) $currentSortBy === $field
            ? ' aria-sort="' . ((string) $currentSortDir === 'asc' ? 'ascending' : 'descending') . '"'
            : '';
        $ariaPhrase = 'Sort by ' . $labelHuman;
        $ariaPhrase .= $currentSortBy === $field
            ? ', currently sorted ' . ($currentSortDir === 'asc' ? 'ascending' : 'descending')
            : ', activate to sort';

        return '<th scope="col" class="report-sort-th"'
            . $ariaSort
            . '><form method="post" action="' . $h($formAction) . '" class="report-sort-form">'
            . '<input type="hidden" name="sort_by" value="' . $h($choice['sort_by']) . '">'
            . '<input type="hidden" name="sort_dir" value="' . $h($choice['sort_dir']) . '">'
            . '<button type="submit" class="report-sort-hit" aria-label="' . $h($ariaPhrase) . '">'
            . '<span class="report-sort-label">' . $h($labelHuman) . '</span>'
            . '<span class="report-sort-sorter" aria-hidden="true"><span class="dvra-sorter-arrow"></span></span>'
            . '</button></form></th>';
    }
}
