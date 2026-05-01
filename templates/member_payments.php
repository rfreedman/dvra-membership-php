<?php
$h = static fn (?string $s): string => \DvraMembership\Support\View::e($s);
/** @var array<string, mixed> $member */
/** @var list<array{id: int, payment_date: string, paid_through: string, membership_type_id: ?int, form_number: ?string, notes: ?string}> $payments */
/** @var list<array{id: int, name: ?string, label: ?string}> $membership_types */
/** @var string|null $flash_error */
$base = $base ?? '';
$flash_error = $flash_error ?? null;
$mid = (int) $member['id'];

$lab = static fn (array $item): string => \DvraMembership\Support\MemberInputNormalizer::referenceLabel(
    isset($item['name']) ? (string) $item['name'] : '',
    isset($item['label']) ? (string) $item['label'] : ''
);

$nameSuffix = '';
if (!empty($member['call_sign'])) {
    $nameSuffix = ' (' . $h((string) $member['call_sign']) . ')';
}
?>
<div class="standard-page-scroll">
  <p><a href="<?= $h($base . '/members/' . $mid . '/view') ?>">← Back to member</a></p>
  <?php if ($flash_error !== null && $flash_error !== ''): ?>
  <p class="error"><?= $h((string) $flash_error) ?></p>
  <?php endif; ?>
  <div class="member-page-header">
    <h2>Payments — <?= $h((string) $member['last_name']) ?>, <?= $h((string) $member['first_name']) ?><?= $nameSuffix ?></h2>
    <div class="member-page-actions">
      <button id="open-add-payment" type="button" class="btn-primary">Add Payment</button>
    </div>
  </div>

  <dialog id="add-payment-dialog">
    <form method="dialog" class="dialog-close-corner-form">
      <button type="submit" class="dialog-close-corner-btn" aria-label="Cancel and close">×</button>
    </form>
    <h3>Add payment</h3>
    <form method="post" action="<?= $h($base . '/members/' . $mid . '/payments/new') ?>" class="stacked-form">
      <label>Payment date <input name="payment_date" type="date" required></label>
      <label>Paid through <input name="paid_through" type="date" required></label>
      <label>Membership type
        <select name="membership_type">
          <option value="">—</option>
          <?php foreach ($membership_types as $item): ?>
          <option value="<?= $h((string) $item['id']) ?>"><?= $h($lab($item)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Form number <input name="form_number" type="text" maxlength="64" autocomplete="off" placeholder="Optional"></label>
      <label class="full-width">Notes <textarea name="notes" rows="4"></textarea></label>
      <div class="form-actions-row">
        <button type="submit">Add payment</button>
        <button type="button" id="cancel-add-payment" class="btn-danger">Cancel</button>
      </div>
    </form>
  </dialog>

  <?php if (count($payments) > 0): ?>
  <div class="data-table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Payment Date</th>
          <th>Paid Through</th>
          <th>Membership Type</th>
          <th>Form #</th>
          <th>Notes</th>
          <th>Delete</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $payment): ?>
        <?php $pid = (int) $payment['id']; ?>
        <tr>
          <td>
            <input class="payment-row-input" form="payment-row-<?= $pid ?>" name="payment_date" type="date" value="<?= $h((string) $payment['payment_date']) ?>" required>
          </td>
          <td>
            <input class="payment-row-input" form="payment-row-<?= $pid ?>" name="paid_through" type="date" value="<?= $h((string) $payment['paid_through']) ?>" required>
          </td>
          <td>
            <select class="payment-row-input" form="payment-row-<?= $pid ?>" name="membership_type">
              <option value="">—</option>
              <?php $selMt = isset($payment['membership_type_id']) ? (int) $payment['membership_type_id'] : null; ?>
              <?php foreach ($membership_types as $item): ?>
              <option value="<?= $h((string) $item['id']) ?>"<?= $selMt !== null && $selMt === (int) $item['id'] ? ' selected' : '' ?>><?= $h($lab($item)) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <input class="payment-row-input" form="payment-row-<?= $pid ?>" name="form_number" type="text" maxlength="64" value="<?= $h(isset($payment['form_number']) && $payment['form_number'] !== null ? (string) $payment['form_number'] : '') ?>" autocomplete="off">
          </td>
          <td>
            <input class="payment-row-input" form="payment-row-<?= $pid ?>" name="notes" type="text" value="<?= $h(isset($payment['notes']) && $payment['notes'] !== null ? (string) $payment['notes'] : '') ?>">
            <form id="payment-row-<?= $pid ?>" method="post" action="<?= $h($base . '/payments/' . $pid . '/edit') ?>"></form>
          </td>
          <td>
            <form method="post" action="<?= $h($base . '/payments/' . $pid . '/delete') ?>">
              <button type="submit" class="btn-danger">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <p class="muted">No payments recorded yet.</p>
  <?php endif; ?>
</div>
