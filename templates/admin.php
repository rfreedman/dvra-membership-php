<?php

$h = static fn (?string $s): string => \DvraMembership\Support\View::e($s);
/** @var string $base */
/** @var string|null $error */
/** @var list<array{id: int, name: string|null}> $license_classes */
/** @var list<array{id: int, name: string|null}> $membership_types */
/** @var list<array{id: int, username: string}> $admin_users */
/** @var list<array{id: int, username: string, display_name: string|null}> $managers */
$base = $base ?? '';
$error = $error ?? null;

$multipleAdmins = count($admin_users) > 1;
?>
<div class="standard-page-scroll admin-reference-page">
  <div class="member-page-header">
    <h2>Reference data and accounts</h2>
  </div>

  <?php if ($error !== null && $error !== ''): ?>
  <p class="error"><?= $h($error) ?></p>
  <?php endif; ?>

  <section class="reference-block admin-ref-section">
    <h3>License classes</h3>
    <div class="data-table-scroll admin-ref-table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th scope="col">Name</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($license_classes as $lc): ?>
          <?php $lid = (int) $lc['id']; ?>
          <tr>
            <td>
              <div class="admin-ref-item-row">
                <form class="admin-ref-row-form" method="post" action="<?= $h($base . '/admin/license/' . $lid . '/update') ?>">
                  <input type="text" name="name" value="<?= $h(isset($lc['name']) ? (string) $lc['name'] : '') ?>" maxlength="64" required class="admin-ref-name-input" aria-label="License class name">
                  <button type="submit">Save</button>
                </form>
                <form class="admin-ref-row-form admin-ref-delete-form" method="post" action="<?= $h($base . '/admin/license/' . $lid . '/delete') ?>">
                  <button type="submit" class="btn-danger">Delete</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <tr class="admin-ref-add-row">
            <td>
              <form class="admin-ref-row-form admin-ref-add-form" method="post" action="<?= $h($base . '/admin/license/create') ?>">
                <label class="visually-hidden" for="admin-new-license-class-name">New name</label>
                <input id="admin-new-license-class-name" type="text" name="name" maxlength="64" required class="admin-ref-name-input" placeholder="New name">
                <button type="submit">Add</button>
              </form>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="reference-block admin-ref-section">
    <h3>Membership types</h3>
    <div class="data-table-scroll admin-ref-table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th scope="col">Name</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($membership_types as $mt): ?>
          <?php $tid = (int) $mt['id']; ?>
          <tr>
            <td>
              <div class="admin-ref-item-row">
                <form class="admin-ref-row-form" method="post" action="<?= $h($base . '/admin/membership-type/' . $tid . '/update') ?>">
                  <input type="text" name="name" value="<?= $h(isset($mt['name']) ? (string) $mt['name'] : '') ?>" maxlength="64" required class="admin-ref-name-input" aria-label="Membership type name">
                  <button type="submit">Save</button>
                </form>
                <form class="admin-ref-row-form admin-ref-delete-form" method="post" action="<?= $h($base . '/admin/membership-type/' . $tid . '/delete') ?>">
                  <button type="submit" class="btn-danger">Delete</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <tr class="admin-ref-add-row">
            <td>
              <form class="admin-ref-row-form admin-ref-add-form" method="post" action="<?= $h($base . '/admin/membership-type/create') ?>">
                <label class="visually-hidden" for="admin-new-membership-type-name">New name</label>
                <input id="admin-new-membership-type-name" type="text" name="name" maxlength="64" required class="admin-ref-name-input" placeholder="New name">
                <button type="submit">Add</button>
              </form>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="reference-block">
    <h3>Application admins</h3>
    <?php foreach ($admin_users as $au): ?>
    <div class="reference-row">
      <strong><?= $h((string) $au['username']) ?></strong>
      <form class="inline-form" method="post" action="<?= $h($base . '/admins/' . $au['id'] . '/password') ?>">
        <label>New password
          <input name="password" type="password" autocomplete="new-password" required>
        </label>
        <div class="inline-form-actions">
          <button type="submit">Set password</button>
        </div>
      </form>
      <?php if ($multipleAdmins): ?>
      <form class="inline-form inline-delete-form" method="post" action="<?= $h($base . '/admins/' . $au['id'] . '/delete') ?>">
        <div class="inline-form-actions">
          <button type="submit" class="btn-danger">Delete</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <form class="inline-form" method="post" action="<?= $h($base . '/admins/create') ?>">
      <label>New admin username
        <input name="username" maxlength="128" autocomplete="username" required>
      </label>
      <label>Initial password
        <input name="password" type="password" autocomplete="new-password" required>
      </label>
      <div class="inline-form-actions">
        <button type="submit">Create admin</button>
      </div>
    </form>
  </section>

  <section class="reference-block">
    <h3>Managers</h3>
    <?php foreach ($managers as $mg): ?>
    <div class="reference-row">
      <strong><?= $h((string) $mg['username']) ?></strong>
      <form class="inline-form" method="post" action="<?= $h($base . '/managers/' . $mg['id'] . '/profile') ?>">
        <label>Display name
          <input name="display_name" value="<?= $h(isset($mg['display_name']) && $mg['display_name'] !== null ? (string) $mg['display_name'] : '') ?>" maxlength="256">
        </label>
        <div class="inline-form-actions">
          <button type="submit">Save profile</button>
        </div>
      </form>
      <form class="inline-form" method="post" action="<?= $h($base . '/managers/' . $mg['id'] . '/password') ?>">
        <label>New password
          <input name="password" type="password" autocomplete="new-password" required>
        </label>
        <div class="inline-form-actions">
          <button type="submit">Set password</button>
        </div>
      </form>
      <form class="inline-form inline-delete-form" method="post" action="<?= $h($base . '/managers/' . $mg['id'] . '/delete') ?>">
        <div class="inline-form-actions">
          <button type="submit" class="btn-danger">Delete</button>
        </div>
      </form>
    </div>
    <?php endforeach; ?>

    <form class="inline-form" method="post" action="<?= $h($base . '/managers/create') ?>">
      <label>New manager username
        <input name="username" maxlength="128" autocomplete="username" required>
      </label>
      <label>Initial password
        <input name="password" type="password" autocomplete="new-password" required>
      </label>
      <label>Display name (optional)
        <input name="display_name" maxlength="256">
      </label>
      <div class="inline-form-actions">
        <button type="submit">Create manager</button>
      </div>
    </form>
  </section>
</div>
