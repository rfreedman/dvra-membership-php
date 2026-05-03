<?php
$h = static fn (?string $s): string => \DvraMembership\Support\View::e($s);
/** @var int $total */
/** @var string $search */
/** @var string $sort_by */
/** @var string $sort_dir */
/** @var string $arrl */
/** @var string $has_key */
/** @var string $current_only */
/** @var int|null $membership_type_id */
/** @var list<array{id: int, name: ?string, label: ?string}> $membership_types */
/** @var string $clearFiltersHref */
/** @var string $exportQuery */
$base = $base ?? '';

$membershipSel = static function (int $mid, ?int $sel): string {
    return ($sel !== null && $sel === $mid) ? ' selected' : '';
};

$membershipBlankSel = static function (?int $sel): string {
    return $sel === null ? ' selected' : '';
};

?>
<div class="grid-page">
  <div class="grid-page-top">
    <div class="member-page-header">
      <h2>Members</h2>
      <div class="member-page-actions">
        <a class="btn-primary" href="<?= $h($base . '/members/new') ?>">New member</a>
      </div>
    </div>
    <form method="get" action="<?= $h($base . '/') ?>" class="filter-form" id="members-filter-form">
      <input type="hidden" name="sort_by" value="<?= $h((string) $sort_by) ?>">
      <input type="hidden" name="sort_dir" value="<?= $h((string) $sort_dir) ?>">
      <div class="members-scope-panel">
        <div class="members-scope-switch" id="members-scope-switch">
          <input type="hidden" name="current_only" id="members-current-only-field" value="<?= $h((string) $current_only) ?>">
          <div class="members-scope-switch-row">
            <span class="members-scope-caption members-scope-caption--current">Current members</span>
            <label class="switch-widget">
              <input
                type="checkbox"
                class="switch-widget-input"
                id="members-current-only-cb"
                <?= $current_only === 'yes' ? 'checked' : '' ?>
                aria-label="Member list: current members only, or all members"
              >
              <span class="switch-widget-track" aria-hidden="true">
                <span class="switch-widget-thumb"></span>
              </span>
            </label>
            <span class="members-scope-caption members-scope-caption--all">All members</span>
          </div>
        </div>
        <p class="muted filter-hint">Current means paid through today or later.</p>
      </div>
      <div class="filter-grid">
        <label>Search <input type="text" name="search" value="<?= $h((string) $search) ?>" placeholder="Name, call sign, email"></label>
        <label>Membership type
          <select name="membership_type_id">
            <option value=""<?= $membershipBlankSel($membership_type_id) ?>>Any</option>
            <?php foreach ($membership_types as $item): ?>
            <option value="<?= $h((string) $item['id']) ?>"<?= $membershipSel($item['id'], $membership_type_id) ?>><?php
                $lab = trim((string) ($item['label'] ?? ''));
                echo $h($lab !== '' ? $lab : (string) ($item['name'] ?? ''));
            ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>ARRL member
          <select name="arrl">
            <option value=""<?= $arrl === '' ? ' selected' : '' ?>>Any</option>
            <option value="yes"<?= $arrl === 'yes' ? ' selected' : '' ?>>Yes</option>
            <option value="no"<?= $arrl === 'no' ? ' selected' : '' ?>>No</option>
          </select>
        </label>
        <label>Key holder
          <select name="has_key">
            <option value=""<?= $has_key === '' ? ' selected' : '' ?>>Any</option>
            <option value="yes"<?= $has_key === 'yes' ? ' selected' : '' ?>>Has key #</option>
            <option value="no"<?= $has_key === 'no' ? ' selected' : '' ?>>No key #</option>
          </select>
        </label>
      </div>
      <div class="filter-form-actions">
        <button type="submit">Apply filters</button>
        <a class="filter-clear" href="<?= $h($clearFiltersHref) ?>">Clear filters</a>
      </div>
    </form>
    <div class="results-and-export-row">
      <p class="results-meta"><?= $h((string) $total) ?> member<?= (int) $total !== 1 ? 's' : '' ?></p>
      <p class="muted export-links">Export matching members:
        <a id="members-export-link-xlsx" class="members-export-link" href="<?= $h($base . '/members/export.xlsx' . $exportQuery) ?>">Excel</a>
        <span aria-hidden="true">·</span>
        <a id="members-export-link-csv" class="members-export-link" href="<?= $h($base . '/members/export.csv' . $exportQuery) ?>">CSV</a>
        <span aria-hidden="true">·</span>
        <a id="members-export-link-pdf" class="members-export-link" href="<?= $h($base . '/members/export.pdf' . $exportQuery) ?>">PDF</a>
      </p>
    </div>
  </div>
  <div class="grid-page-body">
    <div id="members-grid" class="members-tabulator-wrap" role="grid" aria-label="Members"></div>
  </div>
</div>
