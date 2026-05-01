<?php
/** @var list<array<string,mixed>> $rows */
/** @var int $total */
$h = static fn (?string $s): string => \DvraMembership\Support\View::e($s);
$base = $base ?? '';
?>
<div class="grid-page">
  <div class="grid-page-top">
    <div class="member-page-header">
      <h2>Members <span class="muted">(preview)</span></h2>
    </div>
    <p class="muted"><?= $h((string) $total) ?> member(s) in database. Tabulator filters, edits, imports, exports, and reports are not implemented in PHP yet.</p>
  </div>
  <div class="grid-page-body">
    <div class="data-table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Last name</th>
            <th>First name</th>
            <th>Call</th>
            <th>Paid through</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= $h(isset($r['id']) ? (string) $r['id'] : '') ?></td>
            <td><?= $h(isset($r['last_name']) ? (string) $r['last_name'] : '') ?></td>
            <td><?= $h(isset($r['first_name']) ? (string) $r['first_name'] : '') ?></td>
            <td><?= $h(isset($r['call_sign']) ? (string) $r['call_sign'] : '') ?></td>
            <td><?= $h(isset($r['paid_through']) ? (string) $r['paid_through'] : '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
