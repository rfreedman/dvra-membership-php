<?php

use DvraMembership\Support\ReportSortHeaderHtml;

$h = static fn (?string $s): string => \DvraMembership\Support\View::e($s);
/** @var int $total */
/** @var list<array{id: int, key_number: int, call_sign: string, last_name: string, first_name: string, email: string}> $rows */
/** @var string $sort_by */
/** @var string $sort_dir */
/** @var array<string, array{sort_by: string, sort_dir: string}> $keyholdersSortPost */
/** @var string $keyholdersPostAction */
/** @var string $exportQuery */
$base = $base ?? '';
$postAction = (string) ($keyholdersPostAction ?? ($base . '/reports/keyholders'));
$name = static fn (array $m): string => $m['last_name'] . ', ' . $m['first_name'];
?>
<div class="grid-page">
  <div class="grid-page-top">
    <div class="member-page-header">
      <h2>Keyholders</h2>
    </div>
    <div class="results-and-export-row report-export-row--after-heading">
      <p class="results-meta"><?= $h((string) $total) ?> member<?= (int) $total !== 1 ? 's' : '' ?> with a key number</p>
      <p class="muted export-links">Export this report:
        <a id="keyholders-report-exp-xlsx" href="<?= $h($base . '/reports/keyholders/export.xlsx' . $exportQuery) ?>">Excel</a>
        <span aria-hidden="true">·</span>
        <a id="keyholders-report-exp-csv" href="<?= $h($base . '/reports/keyholders/export.csv' . $exportQuery) ?>">CSV</a>
        <span aria-hidden="true">·</span>
        <a id="keyholders-report-exp-pdf" href="<?= $h($base . '/reports/keyholders/export.pdf' . $exportQuery) ?>">PDF</a>
      </p>
    </div>
    <p class="muted">Members with a key number assigned.</p>
  </div>
  <div class="grid-page-body">
    <div class="data-table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <?php $sb = (string) $sort_by;
            $sd = (string) $sort_dir;
            $sp = $keyholdersSortPost ?? [];
            echo ReportSortHeaderHtml::sortableTh('key_number', 'Key #', $sb, $sd, $sp, $postAction);
            echo ReportSortHeaderHtml::sortableTh('call_sign', 'Call sign', $sb, $sd, $sp, $postAction);
            echo ReportSortHeaderHtml::sortableTh('name', 'Name', $sb, $sd, $sp, $postAction);
            echo ReportSortHeaderHtml::sortableTh('email', 'Email', $sb, $sd, $sp, $postAction);
?>
            <th scope="col" class="actions-cell"><span class="visually-hidden">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $member): ?>
          <?php $id = (int) $member['id']; ?>
          <tr>
            <td><?= $h((string) $member['key_number']) ?></td>
            <td><?= $h($member['call_sign'] !== '' ? $member['call_sign'] : '—') ?></td>
            <td><?= $h($name($member)) ?></td>
            <td><?= $h($member['email'] !== '' ? $member['email'] : '—') ?></td>
            <td><a href="<?= $h($base . '/members/' . $id . '/view') ?>">View</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function () {
  var base = "<?= htmlspecialchars($base, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>";
  function syncKeyholdersExports() {
    ["xlsx", "csv", "pdf"].forEach(function (ext) {
      var a = document.getElementById("keyholders-report-exp-" + ext);
      if (a) a.href = base + "/reports/keyholders/export." + ext;
    });
  }
  syncKeyholdersExports();
})();
</script>
