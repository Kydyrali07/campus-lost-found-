<?php
require __DIR__ . '/includes/app.php';
if (user()) redirect('index.php');
$errors = []; $email = ''; $name = '';
if (is_post()) {
    csrf_check();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $name  = trim((string) ($_POST['full_name'] ?? ''));
    $pass  = (string) ($_POST['password'] ?? '');
    if (rate_limited()) $errors[] = 'Too many attempts. Try again in 15 minutes.';
    if (!preg_match('/^[a-z0-9._-]+@astanait\.edu\.kz$/', $email)) $errors[] = 'Use your university e-mail (@astanait.edu.kz).';
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Enter your full name.';
    if (strlen($pass) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pass !== (string) ($_POST['password2'] ?? '')) $errors[] = 'Passwords do not match.';
    if (!$errors) {
        try {
            $pdo = db();
            $pdo->prepare('INSERT INTO users (email, password_hash, full_name) VALUES (?, ?, ?)')->execute([$email, password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]), $name]);
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int) $pdo->lastInsertId(), 'name' => $name, 'role' => 'student', 'email' => $email];
            flash('Welcome, ' . $name . '! Your account is ready.');
            redirect('index.php');
        } catch (PDOException $e) { $errors[] = $e->getCode() === '23000' ? 'This e-mail is already registered.' : 'Could not create the account.'; }
    }
}
page_header('Register');
?>
<div style="max-width: 480px; margin: 40px auto 0;">
  <div class="form-card">
    <div style="text-align: center; margin-bottom: 24px;">
      <h1 style="font-size: 24px; font-weight: 800; color: var(--slate-900); margin: 0 0 6px 0;">Create an account</h1>
      <p style="font-size: 14px; color: var(--slate-500); margin: 0;">Only members of Astana IT University can register</p>
    </div>

    <?php foreach ($errors as $er): ?>
      <div class="flash-alert flash-err"><?= e($er) ?></div>
    <?php endforeach; ?>

    <form method="post">
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label">Full name</label>
        <input name="full_name" class="form-input" value="<?= e($name) ?>" placeholder="e.g. Aigerim Akhmetova" required autofocus>
      </div>

      <div class="form-group">
        <label class="form-label">University e-mail</label>
        <input type="email" name="email" class="form-input" value="<?= e($email) ?>" placeholder="name@astanait.edu.kz" required>
      </div>

      <div class="form-group">
        <label class="form-label">Password (min. 8 characters)</label>
        <input type="password" name="password" class="form-input" minlength="8" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required>
      </div>

      <div class="form-group">
        <label class="form-label">Confirm password</label>
        <input type="password" name="password2" class="form-input" minlength="8" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required>
      </div>

      <button class="btn-primary" style="width: 100%; padding: 12px; margin-top: 8px;">Create account</button>

      <div style="text-align: center; margin-top: 20px; font-size: 14px; color: var(--slate-500);">
        Already have an account? <a href="login.php" style="font-weight: 600;">Log in</a>
      </div>
    </form>
  </div>
</div>
<?php page_footer();
