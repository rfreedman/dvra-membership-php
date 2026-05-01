<?php
/** @var string $title */
/** @var string $contentHtml */
$h = static fn (?string $s): string => \DvraMembership\Support\View::e($s);
$t = $title ?? 'DVRA Membership Manager';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $h($t) ?></title>
  <link rel="stylesheet" href="<?= $h(($base ?? '') . '/static/style.css') ?>">
</head>
<body>
<?php if (!empty($simpleLayout)): ?>
<?= $contentHtml ?>
<?php else: ?>
<header class="site-header">
  <div class="header-inner">
    <div class="site-header-brand-row">
      <a href="<?= $h(($base ?? '') . '/') ?>" aria-label="DVRA Membership Manager home">
        <img class="site-header-logo" src="<?= $h(($base ?? '') . '/static/w2zq-site-icon-gold.png') ?>" alt="" width="48" height="48">
      </a>
      <h1><a href="<?= $h(($base ?? '') . '/') ?>">DVRA Membership Manager</a></h1>
    </div>
    <nav class="site-nav">
      <?php if (!empty($authenticated)): ?>
      <span class="muted">PHP preview</span>
      <form class="logout-form" method="post" action="<?= $h(($base ?? '') . '/logout') ?>">
        <button type="submit">Logout</button>
      </form>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="site-main">
  <?= $contentHtml ?>
</main>
<script>
(function () {
  function fitScrollablePanels() {
    var panels = document.querySelectorAll(".grid-page-body > .data-table-scroll, .standard-page-scroll");
    if (!panels.length) return;
    var vh = window.innerHeight || document.documentElement.clientHeight || 0;
    var siteMain = document.querySelector(".site-main");
    var pad = 0;
    if (siteMain) pad = parseFloat(window.getComputedStyle(siteMain).paddingBottom || "0") || 0;
    for (var i = 0; i < panels.length; i++) {
      var panel = panels[i];
      var rect = panel.getBoundingClientRect();
      var target = Math.max(180, Math.floor(vh - rect.top - pad - 12));
      panel.style.height = target + "px";
    }
  }
  window.addEventListener("resize", fitScrollablePanels);
  document.addEventListener("DOMContentLoaded", function () {
    fitScrollablePanels();
    window.requestAnimationFrame(fitScrollablePanels);
  });
})();
</script>
<?php endif; ?>
</body>
</html>
