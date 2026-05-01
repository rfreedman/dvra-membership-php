<?php
$h = static fn (?string $s): string => \DvraMembership\Support\View::e($s);
/** @var string|null $error */
/** @var list<array{id: int, name: ?string, label: ?string}> $license_classes */
/** @var list<array{id: int, name: ?string, label: ?string}> $membership_types */
$base = $base ?? '';
$error = $error ?? null;

$lab = static fn (array $item): string => \DvraMembership\Support\MemberInputNormalizer::referenceLabel(
    isset($item['name']) ? (string) $item['name'] : '',
    isset($item['label']) ? (string) $item['label'] : ''
);
?>
<div class="standard-page-scroll member-new-page">
  <h2>Create member</h2>
  <?php if ($error): ?>
  <p class="error"><?= $h($error) ?></p>
  <?php endif; ?>
  <form method="post" action="<?= $h($base . '/members/new') ?>" class="stacked-form">
    <label>Last name <input name="last_name" required autocomplete="family-name"></label>
    <label>First name <input name="first_name" required autocomplete="given-name"></label>
    <label>Call sign <input name="call_sign" placeholder="optional" autocomplete="off"></label>
    <label>Email <input name="email" type="email" autocomplete="email"></label>
    <label>Phone <input name="phone" autocomplete="tel"></label>
    <fieldset class="member-address-fields">
      <legend>Mailing address</legend>
      <label>Street <input name="address_street" autocomplete="street-address" placeholder="optional"></label>
      <label>City <input name="address_city" autocomplete="address-level2" placeholder="optional"></label>
      <label>State <input name="address_state" autocomplete="address-level1" maxlength="16" placeholder="e.g. NJ"></label>
      <label>ZIP <input name="address_zip" autocomplete="postal-code" maxlength="16" placeholder="optional"></label>
    </fieldset>
    <label>License class
      <select name="license_class">
        <option value="">—</option>
        <?php foreach ($license_classes as $item): ?>
        <option value="<?= $h((string) $item['id']) ?>"><?= $h($lab($item)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Membership type
      <select name="membership_type">
        <option value="">—</option>
        <?php foreach ($membership_types as $item): ?>
        <option value="<?= $h((string) $item['id']) ?>"><?= $h($lab($item)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>ARRL member
      <select name="arrl_member">
        <option value="no" selected>No</option>
        <option value="yes">Yes</option>
      </select>
    </label>
    <label>Key number <input name="key_number" type="number" min="0" step="1" placeholder="optional"></label>
    <label>Paid through <input name="paid_through" type="date"></label>
    <button type="submit">Create</button>
  </form>
</div>
