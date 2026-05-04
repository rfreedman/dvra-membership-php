<?php

use DvraMembership\Support\ReportSortHeaderHtml;

$h = static fn (?string $s): string => \DvraMembership\Support\View::e($s);
/** @var int $total */
/** @var list<array<string, mixed>> $rows */
/** @var string $start_date */
/** @var string $end_date */
/** @var string $paid_through_start */
/** @var string $paid_through_end */
/** @var string $sort_by */
/** @var string $sort_dir */
/** @var array<string, array{sort_by: string, sort_dir: string}> $paymentsReportSortPost */
/** @var string $paymentsReportPostAction */
/** @var string $exportQuery */
$base = $base ?? '';
$postAction = (string) ($paymentsReportPostAction ?? ($base . '/reports/payments'));
?>
<div class="grid-page">
  <div class="grid-page-top">
    <div class="member-page-header">
      <h2>Payment report</h2>
    </div>

    <form method="post" action="<?= $h($postAction) ?>" class="filter-form" id="payments-report-filter-form">
      <input type="hidden" name="sort_by" value="<?= $h((string) $sort_by) ?>">
      <input type="hidden" name="sort_dir" value="<?= $h((string) $sort_dir) ?>">
      <div class="filter-grid">
        <label>Payment start date <input type="date" name="start_date" value="<?= $h((string) $start_date) ?>"></label>
        <label>Payment end date <input type="date" name="end_date" value="<?= $h((string) $end_date) ?>"></label>
        <label>Paid through start <input type="date" name="paid_through_start" value="<?= $h((string) $paid_through_start) ?>"></label>
        <label>Paid through end <input type="date" name="paid_through_end" value="<?= $h((string) $paid_through_end) ?>"></label>
      </div>
      <div class="filter-form-actions">
        <button type="submit">Apply filters</button>
        <button type="submit" name="clear_filters" value="1" class="filter-clear">Clear filters</button>
      </div>
    </form>

    <div class="results-and-export-row">
      <p class="results-meta"><?= $h((string) $total) ?> payment<?= (int) $total !== 1 ? 's' : '' ?></p>
      <p class="muted export-links">Export matching payments:
        <a id="payments-report-exp-xlsx" href="<?= $h($base . '/reports/payments/export.xlsx' . $exportQuery) ?>">Excel</a>
        <span aria-hidden="true">·</span>
        <a id="payments-report-exp-csv" href="<?= $h($base . '/reports/payments/export.csv' . $exportQuery) ?>">CSV</a>
        <span aria-hidden="true">·</span>
        <a id="payments-report-exp-pdf" href="<?= $h($base . '/reports/payments/export.pdf' . $exportQuery) ?>">PDF</a>
      </p>
    </div>
  </div>

  <div class="grid-page-body">
    <div class="data-table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <?php $sb = (string) $sort_by;
            $sd = (string) $sort_dir;
            $sp = $paymentsReportSortPost ?? [];
            echo ReportSortHeaderHtml::sortableTh('member_name', 'Member', $sb, $sd, $sp, $postAction);
            echo ReportSortHeaderHtml::sortableTh('call_sign', 'Call sign', $sb, $sd, $sp, $postAction);
            echo ReportSortHeaderHtml::sortableTh('payment_date', 'Payment date', $sb, $sd, $sp, $postAction);
            echo ReportSortHeaderHtml::sortableTh('paid_through', 'Paid through', $sb, $sd, $sp, $postAction);
            echo ReportSortHeaderHtml::sortableTh('membership_type', 'Membership type', $sb, $sd, $sp, $postAction);
            echo ReportSortHeaderHtml::sortableTh('form_number', 'Form number', $sb, $sd, $sp, $postAction);
?>
            <th scope="col">Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $pay): ?>
          <?php $mid = (int) ($pay['member_id'] ?? 0); ?>
          <tr>
            <td><?= $h((string) ($pay['member_name'] ?? '')) ?></td>
            <td><?php $cs = (string) ($pay['call_sign'] ?? ''); ?>
              <?php if ($mid > 0 && $cs !== ''): ?>
              <a href="<?= $h($base . '/members/' . $mid . '/payments') ?>"><?= $h($cs) ?></a>
              <?php elseif ($mid > 0): ?>
              <a href="<?= $h($base . '/members/' . $mid . '/payments') ?>"><?= $h('—') ?></a>
              <?php else: ?>
              <?= $h('—') ?>
              <?php endif; ?>
            </td>
            <td><?= $h((string) ($pay['payment_date'] ?? '')) ?></td>
            <td><?= $h((string) ($pay['paid_through'] ?? '')) ?></td>
            <td><?= $h((string) ($pay['membership_type'] ?? '')) ?></td>
            <td><?= $h((string) ($pay['form_number'] ?? '')) ?></td>
            <td><?= $h((string) ($pay['notes'] ?? '')) ?></td>
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
  var form = document.getElementById("payments-report-filter-form");
  if (!form) return;
  function syncExports() {
    ["xlsx", "csv", "pdf"].forEach(function (ext) {
      var a = document.getElementById("payments-report-exp-" + ext);
      if (a) a.href = base + "/reports/payments/export." + ext;
    });
  }
  form.addEventListener("input", syncExports);
  form.addEventListener("change", syncExports);
  syncExports();
})();
</script>
