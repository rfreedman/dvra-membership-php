<?php
$h = static fn (?string $s): string => \DvraMembership\Support\View::e($s);
/** @var string|null $error */
/** @var list<array{id: int, name: ?string}> $license_classes */
/** @var list<array{id: int, name: ?string}> $membership_types */
$base = $base ?? '';
$error = $error ?? null;

$lab = static fn (array $item): string => \DvraMembership\Support\MemberInputNormalizer::referenceLabel(
    isset($item['name']) ? (string) $item['name'] : null
);
?>
<div class="standard-page-scroll member-new-page">
  <h2>Create member</h2>
  <?php if ($error): ?>
  <p class="error"><?= $h($error) ?></p>
  <?php endif; ?>
  <form method="post" action="<?= $h($base . '/members/new') ?>" class="member-edit-form" id="member-create-form">
    <div class="member-form-field">
      <label for="new-last-name">Last name</label>
      <input id="new-last-name" class="member-form-control" name="last_name" required autocomplete="family-name">
    </div>
    <div class="member-form-field">
      <label for="new-first-name">First name</label>
      <input id="new-first-name" class="member-form-control" name="first_name" required autocomplete="given-name">
    </div>
    <div class="member-form-field">
      <label for="new-call">Call sign</label>
      <input id="new-call" class="member-form-control" name="call_sign" placeholder="optional" autocomplete="off">
    </div>
    <div class="member-form-field">
      <label for="new-email">Email</label>
      <input id="new-email" class="member-form-control" name="email" type="email" autocomplete="email">
    </div>
    <div class="member-form-field">
      <label for="new-phone">Phone</label>
      <input id="new-phone" class="member-form-control" name="phone" autocomplete="tel">
    </div>

    <fieldset class="member-address-fields member-form-fieldset">
      <legend>Mailing address</legend>
      <div class="member-form-field">
        <label for="new-addr-street">Street</label>
        <input id="new-addr-street" class="member-form-control" name="address_street" autocomplete="street-address" placeholder="optional">
      </div>
      <div class="member-form-field">
        <label for="new-addr-city">City</label>
        <input id="new-addr-city" class="member-form-control" name="address_city" autocomplete="address-level2" placeholder="optional">
      </div>
      <div class="member-form-field">
        <label for="new-addr-state">State</label>
        <input id="new-addr-state" class="member-form-control" name="address_state" autocomplete="address-level1" maxlength="16" placeholder="e.g. NJ">
      </div>
      <div class="member-form-field">
        <label for="new-addr-zip">ZIP</label>
        <input id="new-addr-zip" class="member-form-control" name="address_zip" autocomplete="postal-code" maxlength="16" placeholder="optional">
      </div>
    </fieldset>

    <div class="member-form-field">
      <label for="new-license">License class</label>
      <select id="new-license" class="member-form-control" name="license_class">
        <option value="">—</option>
        <?php foreach ($license_classes as $item): ?>
        <option value="<?= $h((string) $item['id']) ?>"><?= $h($lab($item)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="member-form-field">
      <label for="new-membership">Membership type</label>
      <select id="new-membership" class="member-form-control" name="membership_type">
        <option value="">—</option>
        <?php foreach ($membership_types as $item): ?>
        <option value="<?= $h((string) $item['id']) ?>"><?= $h($lab($item)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="member-form-field">
      <label for="new-arrl">ARRL member</label>
      <select id="new-arrl" class="member-form-control" name="arrl_member">
        <option value="no" selected>No</option>
        <option value="yes">Yes</option>
      </select>
    </div>
    <div class="member-form-field">
      <label for="new-key">Key number</label>
      <input id="new-key" class="member-form-control no-spinner" name="key_number" type="number" min="0" step="1" placeholder="optional">
    </div>
    <div class="member-form-field">
      <label for="new-paid-through">Paid through</label>
      <input id="new-paid-through" class="member-form-control" name="paid_through" type="date">
    </div>

    <div class="member-form-actions-row member-form-actions-row--create">
      <button type="submit" class="member-form-submit">Create member</button>
    </div>
  </form>
</div>
