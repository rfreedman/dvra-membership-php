<?php
$h = static fn (?string $s): string => \DvraMembership\Support\View::e($s);
/** @var array<string, mixed> $member */
/** @var list<array{id: int, payment_date: string, paid_through: string, membership_type_display: string, form_number: ?string, notes: ?string}> $payments */
$base = $base ?? '';
$mid = (int) $member['id'];
$name = ($member['call_sign'] ?? null) !== null && ($member['call_sign'] ?? '') !== ''
    ? ' (' . $h((string) $member['call_sign']) . ')'
    : '';
?>
<div class="standard-page-scroll">
  <p><a href="<?= $h($base . '/members/' . $mid . '/view') ?>">← Back to member</a></p>
  <div class="member-page-header">
    <h2>Payments — <?= $h((string) $member['last_name']) ?>, <?= $h((string) $member['first_name']) ?><?= $name ?></h2>
  </div>

  <?php if (count($payments) > 0): ?>
  <div class="data-table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Payment date</th>
          <th>Paid through</th>
          <th>Membership type</th>
          <th>Form #</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td><?= $h((string) $p['payment_date']) ?></td>
          <td><?= $h((string) $p['paid_through']) ?></td>
          <td><?= $h((string) $p['membership_type_display']) ?></td>
          <td><?= $h(isset($p['form_number']) && $p['form_number'] !== null ? (string) $p['form_number'] : '') ?></td>
          <td><?= $h(isset($p['notes']) && $p['notes'] !== null ? (string) $p['notes'] : '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <p class="muted">No payments recorded yet.</p>
  <?php endif; ?>

  <p class="muted" style="margin-top: 1rem;">Payment add/edit/delete in the PHP UI is not implemented yet — use the Python app or SQLite tools for changes.</p>
</div>
