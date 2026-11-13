<?php
// One-click installer: creates the database 'lostfound' (schema.sql) and loads the test data.
// Open http://localhost/lostfound/install.php once. Delete this file after installing.
declare(strict_types=1);
session_start();
require __DIR__ . '/includes/seed.php';
header('X-Content-Type-Options: nosniff');

function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
$cfg = require __DIR__ . '/config.php';
$msg = ''; $counts = null; $error = '';

$_SESSION['csrf'] ??= bin2hex(random_bytes(16));
try {
    $server = new PDO("mysql:host={$cfg['host']};port={$cfg['port']};charset=utf8mb4", $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    $server = null; $error = 'Cannot connect to MySQL: ' . $e->getMessage() . ' (start MySQL in the XAMPP Control Panel and check config.php)';
}

$installed = false;
if ($server) {
    try { $installed = (int) $server->query('SELECT COUNT(*) FROM `' . $cfg['name'] . '`.users')->fetchColumn() > 0; } catch (PDOException $e) { $installed = false; }
}

if ($server && $_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
    if ($installed && ($_POST['confirm'] ?? '') !== 'yes') {
        $error = 'The database already exists. Tick the box to delete it and install again.';
    } else {
        try {
            $server->exec(file_get_contents(__DIR__ . '/schema.sql'));                    // CREATE DATABASE + 8 tables
            $pdo = new PDO("mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset=utf8mb4", $cfg['user'], $cfg['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $counts = seed_database($pdo);
            if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0775, true);
            $installed = true;
        } catch (Throwable $e) { $error = 'Install failed: ' . $e->getMessage(); }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install | Campus Lost &amp; Found</title><link rel="stylesheet" href="assets/style.css"></head><body><main class="wrap">
<h1>Install Campus Lost &amp; Found</h1>
<p class="sub">Creates the MySQL database <b><?= h($cfg['name']) ?></b> with 8 tables and fills it with test data.</p>
<?php if ($error): ?><div class="flash err"><?= h($error) ?></div><?php endif; ?>
<?php if ($counts): ?>
  <div class="flash ok">Installed. The website is now connected to the database.</div>
  <table><tr><th>Table</th><th>Rows</th></tr><?php foreach ($counts as $t => $n): ?><tr><td><?= h($t) ?></td><td><?= (int) $n ?></td></tr><?php endforeach; ?></table>
  <div class="panel"><h3>Demo accounts</h3>
    <p>Admin: <b>admin@astanait.edu.kz</b> / <b>Admin#2026</b><br>Student: <b>aigerim.akhmetov1@astanait.edu.kz</b> / <b>Student#2026</b></p>
    <p class="meta">For safety, delete <b>install.php</b> after installing.</p>
    <a class="btn" href="index.php">Open the website</a></div>
<?php elseif ($server): ?>
  <form class="form" method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
    <?php if ($installed): ?>
      <div class="flash ok">The database is already installed.</div>
      <label><span><input type="checkbox" name="confirm" value="yes"> Delete everything and install again</span></label>
      <div class="row"><button class="btn danger">Reinstall</button><a class="btn" href="index.php">Open the website</a></div>
    <?php else: ?>
      <button class="btn">Create database and load test data</button>
    <?php endif; ?>
  </form>
<?php endif; ?>
</main></body></html>
