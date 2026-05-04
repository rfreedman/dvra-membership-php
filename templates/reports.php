<?php
$h = static fn (?string $s): string => \DvraMembership\Support\View::e($s);
$base = $base ?? '';
?>
<div class="standard-page-scroll">
  <div class="member-page-header">
    <h2>Reports</h2>
  </div>
  <p class="muted">Choose a report:</p>

  <div class="reference-block">
    <p><a href="<?= $h($base . '/reports/payments') ?>">Payment report</a></p>
    <p><a href="<?= $h($base . '/reports/keyholders') ?>">Keyholders report</a></p>
    <p><a href="<?= $h($base . '/reports/roster-by-name') ?>">Roster by name</a></p>
    <p><a href="<?= $h($base . '/reports/roster-by-callsign') ?>">Roster by callsign</a></p>
  </div>
</div>
