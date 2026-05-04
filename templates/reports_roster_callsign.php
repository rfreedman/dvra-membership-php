<?php
$h = static fn (?string $s): string => \DvraMembership\Support\View::e($s);
/** @var int $total */
/** @var list<array{call_sign: string, last_name: string, first_name: string}> $rows */
$base = $base ?? '';
?>
<div class="grid-page">
  <div class="grid-page-top">
    <div class="member-page-header">
      <h2>Roster by callsign</h2>
    </div>
    <p class="muted">Current members only (paid through on or after today — same as the members list). Sorted by call sign.</p>
    <div class="results-and-export-row report-export-row--after-heading">
      <p class="results-meta"><?= $h((string) $total) ?> members</p>
      <p class="muted export-links">Export this roster:
        <a href="<?= $h($base . '/reports/roster-by-callsign/export.xlsx') ?>">Excel</a>
        <span aria-hidden="true">·</span>
        <a href="<?= $h($base . '/reports/roster-by-callsign/export.csv') ?>">CSV</a>
        <span aria-hidden="true">·</span>
        <a href="<?= $h($base . '/reports/roster-by-callsign/export.pdf') ?>">PDF</a>
      </p>
    </div>
  </div>
  <div class="grid-page-body">
    <div class="data-table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th scope="col">Call sign</th>
            <th scope="col">Last name</th>
            <th scope="col">First name</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $member): ?>
          <tr>
            <td><?= $h($member['call_sign'] !== '' ? $member['call_sign'] : '—') ?></td>
            <td><?= $h((string) $member['last_name']) ?></td>
            <td><?= $h((string) $member['first_name']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
