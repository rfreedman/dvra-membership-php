<?php
/** @var string|null $error */
$h = static fn (?string $s): string => \DvraMembership\Support\View::e($s);
$error = $error ?? null;
$base = $base ?? '';
?>
<div class="grid-page">
<div class="grid-page-top"><h2>Login</h2></div>
<div class="grid-page-body standard-page-scroll" style="height:auto;">
<?php if ($error): ?>
<p class="error"><?= $h($error) ?></p>
<?php endif; ?>
<form method="post" action="<?= $h($base . '/login') ?>" class="stacked-form" style="flex-direction:column;align-items:stretch;">
  <label>Username <input name="username" required autocomplete="username"></label>
  <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
  <button type="submit">Sign in</button>
</form>
<p class="muted">PHP port — members list mirrors the Python app’s grid UI; deeper features are still migrating.</p>
</div>
</div>
