<?php
$h = static fn (?string $s): string => \DvraMembership\Support\View::e($s);
/** @var array<string, mixed> $member */
/** @var string|null $error */
/** @var list<array{id: int, name: ?string, label: ?string}> $license_classes */
/** @var list<array{id: int, name: ?string, label: ?string}> $membership_types */
$base = $base ?? '';
$error = $error ?? null;

$lab = static fn (array $item): string => \DvraMembership\Support\MemberInputNormalizer::referenceLabel(
    isset($item['name']) ? (string) $item['name'] : '',
    isset($item['label']) ? (string) $item['label'] : ''
);
$cid = static fn (?string $s): string => $h($s !== null ? $s : '');
$mid = (int) $member['id'];
$keyVal = isset($member['key_number']) && $member['key_number'] !== null ? (string) (int) $member['key_number'] : '';
?>
<div class="standard-page-scroll member-detail-page">
  <?php if ($error !== null && $error !== ''): ?>
  <p class="error"><?= $h((string) $error) ?></p>
  <?php endif; ?>
  <div class="member-page-header">
    <h2><?php if (!empty($member['call_sign'])): ?><?= $h((string) $member['call_sign']) ?> — <?php endif; ?><?= $h((string) $member['last_name']) ?>, <?= $h((string) $member['first_name']) ?></h2>
    <div class="member-page-actions">
      <a class="btn-primary member-edit-leave-risk" href="<?= $h($base . '/members/' . $mid . '/payments') ?>">Payments</a>
    </div>
  </div>

  <form method="post" action="<?= $h($base . '/members/' . $mid . '/edit') ?>" class="member-edit-form" id="member-edit-form">
    <div class="member-form-field">
      <label for="edit-last-name">Last name</label>
      <input id="edit-last-name" class="member-form-control" name="last_name" value="<?= $cid(isset($member['last_name']) ? (string) $member['last_name'] : '') ?>" required autocomplete="family-name">
    </div>
    <div class="member-form-field">
      <label for="edit-first-name">First name</label>
      <input id="edit-first-name" class="member-form-control" name="first_name" value="<?= $cid(isset($member['first_name']) ? (string) $member['first_name'] : '') ?>" required autocomplete="given-name">
    </div>
    <div class="member-form-field">
      <label for="edit-call">Call sign</label>
      <input id="edit-call" class="member-form-control" name="call_sign" value="<?= $cid(isset($member['call_sign']) ? (string) $member['call_sign'] : null) ?>" placeholder="optional" autocomplete="off">
    </div>
    <div class="member-form-field">
      <label for="edit-email">Email</label>
      <input id="edit-email" class="member-form-control" name="email" type="email" value="<?= $cid(isset($member['email']) ? (string) $member['email'] : null) ?>" autocomplete="email">
    </div>
    <div class="member-form-field">
      <label for="edit-phone">Phone</label>
      <input id="edit-phone" class="member-form-control" name="phone" value="<?= $cid(isset($member['phone']) ? (string) $member['phone'] : null) ?>" autocomplete="tel">
    </div>

    <fieldset class="member-address-fields member-form-fieldset">
      <legend>Mailing address</legend>
      <div class="member-form-field">
        <label for="edit-addr-street">Street</label>
        <input id="edit-addr-street" class="member-form-control" name="address_street" value="<?= $cid(isset($member['address_street']) ? (string) $member['address_street'] : null) ?>" autocomplete="street-address">
      </div>
      <div class="member-form-field">
        <label for="edit-addr-city">City</label>
        <input id="edit-addr-city" class="member-form-control" name="address_city" value="<?= $cid(isset($member['address_city']) ? (string) $member['address_city'] : null) ?>" autocomplete="address-level2">
      </div>
      <div class="member-form-field">
        <label for="edit-addr-state">State</label>
        <input id="edit-addr-state" class="member-form-control" name="address_state" value="<?= $cid(isset($member['address_state']) ? (string) $member['address_state'] : null) ?>" maxlength="16" autocomplete="address-level1">
      </div>
      <div class="member-form-field">
        <label for="edit-addr-zip">ZIP</label>
        <input id="edit-addr-zip" class="member-form-control" name="address_zip" value="<?= $cid(isset($member['address_zip']) ? (string) $member['address_zip'] : null) ?>" maxlength="16" autocomplete="postal-code">
      </div>
    </fieldset>

    <div class="member-form-field">
      <label for="edit-license">License class</label>
      <select id="edit-license" class="member-form-control" name="license_class">
        <option value="">—</option>
        <?php $selLic = isset($member['license_class_id']) ? (int) $member['license_class_id'] : null; ?>
        <?php foreach ($license_classes as $item): ?>
        <option value="<?= $h((string) $item['id']) ?>"<?= $selLic !== null && $selLic === (int) $item['id'] ? ' selected' : '' ?>><?= $h($lab($item)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="member-form-field">
      <label for="edit-membership">Membership type</label>
      <select id="edit-membership" class="member-form-control" name="membership_type">
        <option value="">—</option>
        <?php $selMt = isset($member['membership_type_id']) ? (int) $member['membership_type_id'] : null; ?>
        <?php foreach ($membership_types as $item): ?>
        <option value="<?= $h((string) $item['id']) ?>"<?= $selMt !== null && $selMt === (int) $item['id'] ? ' selected' : '' ?>><?= $h($lab($item)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="member-form-field">
      <label for="edit-arrl">ARRL member</label>
      <select id="edit-arrl" class="member-form-control" name="arrl_member">
        <option value="no"<?= empty($member['arrl_member']) ? ' selected' : '' ?>>No</option>
        <option value="yes"<?= !empty($member['arrl_member']) ? ' selected' : '' ?>>Yes</option>
      </select>
    </div>
    <div class="member-form-field">
      <label for="edit-key">Key number</label>
      <input id="edit-key" class="member-form-control no-spinner" name="key_number" type="number" min="0" step="1" value="<?= $h($keyVal) ?>">
    </div>

    <div class="member-form-field member-form-field--readonly" aria-live="polite">
      <span class="member-form-field-label">Paid through</span>
      <p class="member-form-readonly-text" id="member-paid-through"><?= $h(isset($member['paid_through']) && $member['paid_through'] !== null && $member['paid_through'] !== '' ? (string) $member['paid_through'] : '—') ?></p>
      <p class="member-form-help"><a class="member-edit-leave-risk" href="<?= $h($base . '/members/' . $mid . '/payments') ?>">Edit payments</a> to change paid-through date.</p>
    </div>

  </form>

  <div class="member-form-actions-row">
    <button type="submit" form="member-edit-form" id="member-edit-save" class="member-form-submit">Save member</button>
    <form method="post" action="<?= $h($base . '/members/' . $mid . '/delete') ?>" class="member-delete-form-inline" id="member-delete-form" data-confirm-delete="Delete this member and all payments?">
      <button type="submit" class="member-form-delete-submit">Delete member</button>
    </form>
  </div>
</div>
