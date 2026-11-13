<?php
require __DIR__ . '/includes/app.php';
if (user())
  redirect('index.php');
$next = safe_next($_GET['next'] ?? $_POST['next'] ?? null);
$error = '';
$email = '';
if (is_post()) {
  csrf_check();
  $email = strtolower(trim((string) ($_POST['email'] ?? '')));
  if (rate_limited())
    $error = 'Too many attempts. Try again in 15 minutes.';
  else {
    $st = db()->prepare('SELECT user_id, password_hash, full_name, role, is_blocked FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();
    if (!$u || !password_verify((string) ($_POST['password'] ?? ''), $u['password_hash']))
      $error = 'Wrong e-mail or password.';   // same message for both cases
    elseif ((int) $u['is_blocked'] === 1)
      $error = 'This account is blocked. Contact campus security.';
    else {
      session_regenerate_id(true);
      $_SESSION['user'] = ['id' => (int) $u['user_id'], 'name' => $u['full_name'], 'role' => $u['role'], 'email' => $email];
      flash('Welcome back, ' . $u['full_name'] . '!');
      redirect($next);
    }
  }
}
page_header('Log in');
?>
<div style="max-width: 440px; margin: 40px auto 0;">
  <div class="form-card">
    <div style="text-align: center; margin-bottom: 24px;">
      <h1 style="font-size: 24px; font-weight: 800; color: var(--slate-900); margin: 0 0 6px 0;">Welcome back</h1>
      <p style="font-size: 14px; color: var(--slate-500); margin: 0;">Log in with your Astana IT University e-mail</p>
    </div>

    <?php if ($error): ?>
      <div class="flash-alert flash-err"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">

      <div class="form-group">
        <label class="form-label">University e-mail</label>
        <input type="email" name="email" class="form-input" value="<?= e($email) ?>" placeholder="name@astanait.edu.kz"
          required autofocus>
      </div>

      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-input"
          placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required>
      </div>

      <button class="btn-primary" style="width: 100%; padding: 12px; margin-top: 8px;">Log in</button>

      <div style="text-align: center; margin-top: 20px; font-size: 14px; color: var(--slate-500);">
        Don't have an account? <a href="register.php" style="font-weight: 600;">Register here</a>
      </div>
    </form>
  </div>
</div>
<?php page_footer();
