<?php
require __DIR__ . '/includes/app.php';
$me = require_admin();
$pdo = db();
$tables = ['users', 'categories', 'locations', 'items', 'messages', 'notifications', 'reports', 'admin_logs'];

// ---------- backup: downloads a full SQL dump of the database (evidence for the backup task)
function sql_dump(PDO $pdo, array $tables): string
{
    $out = "-- Campus Lost & Found backup, " . date('Y-m-d H:i:s') . "\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach ($tables as $t) {
        $out .= "DROP TABLE IF EXISTS `$t`;\n" . $pdo->query("SHOW CREATE TABLE `$t`")->fetch()['Create Table'] . ";\n\n";
        foreach ($pdo->query("SELECT * FROM `$t`") as $row) {
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row));
            $out .= "INSERT INTO `$t` VALUES (" . implode(',', $vals) . ");\n";
        }
        $out .= "\n";
    }
    return $out . "SET FOREIGN_KEY_CHECKS=1;\n";
}

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'backup') {
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="lostfound_' . date('Y-m-d_His') . '.sql"');
        echo sql_dump($pdo, $tables); exit;
    }
    if ($action === 'report_reviewed') {
        $pdo->prepare("UPDATE reports SET status = 'reviewed' WHERE report_id = ?")->execute([$id]); flash('Report marked as reviewed.');
    } elseif ($action === 'delete_item') {
        $st = $pdo->prepare('SELECT title FROM items WHERE item_id = ?'); $st->execute([$id]); $it = $st->fetch();
        if ($it) {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO admin_logs (admin_id, item_id, action, details) VALUES (?, ?, 'delete_post', ?)")->execute([$me['id'], $id, 'Deleted listing: ' . mb_substr($it['title'], 0, 100)]);
            $pdo->prepare('DELETE FROM items WHERE item_id = ?')->execute([$id]);
            $pdo->commit(); flash('Listing deleted and logged.');
        } else flash('Listing not found.', 'err');
    } elseif ($action === 'block' || $action === 'unblock') {
        $st = $pdo->prepare('SELECT email, role FROM users WHERE user_id = ?'); $st->execute([$id]); $t = $st->fetch();
        if (!$t || $t['role'] === 'admin') flash('This account cannot be changed.', 'err');
        else {
            $pdo->prepare('UPDATE users SET is_blocked = ? WHERE user_id = ?')->execute([$action === 'block' ? 1 : 0, $id]);
            $pdo->prepare('INSERT INTO admin_logs (admin_id, item_id, action, details) VALUES (?, NULL, ?, ?)')->execute([$me['id'], $action . '_user', ucfirst($action) . 'ed user ' . $t['email']]);
            flash('User ' . $action . 'ed.');
        }
    }
    redirect('admin.php?tab=' . urlencode((string) ($_POST['tab'] ?? 'dashboard')));
}

$tab = in_array($_GET['tab'] ?? '', ['reports', 'users', 'logs', 'analytics'], true) ? $_GET['tab'] : 'dashboard';
page_header('Admin', 'admin');
?>
<div style="margin-bottom: 24px;">
  <h1 style="font-size: 28px; font-weight: 800; color: var(--slate-900); margin: 0 0 16px 0;">Admin Control Center</h1>
  <div style="display: flex; gap: 8px; border-bottom: 1px solid var(--slate-200); padding-bottom: 12px; margin-bottom: 24px; flex-wrap: wrap;">
    <?php foreach (['dashboard' => 'Dashboard', 'analytics' => 'Data Analytics (Stage 2)', 'reports' => 'Reports', 'users' => 'Users', 'logs' => 'Activity log'] as $k => $label): ?>
      <a href="admin.php?tab=<?= $k ?>" class="pager-item<?= $tab === $k ? ' active' : '' ?>" style="padding: 0 18px; border-radius: var(--radius-pill); height: 38px;">
        <?= $label ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($tab === 'dashboard'): ?>
  <div class="panel-card" style="margin-bottom: 24px;">
    <h2 class="panel-title">Database Status</h2>
    <p style="font-size: 14.5px; color: var(--slate-600); margin: 0 0 16px 0;">
      Connected to MySQL database <b><?= e((require __DIR__ . '/config.php')['name']) ?></b>, server version <?= e($pdo->query('SELECT VERSION()')->fetchColumn()) ?>.
    </p>
    <div class="table-container">
      <table class="data-table">
        <thead><tr><th>Table Name</th><th>Record Count</th></tr></thead>
        <tbody>
          <?php foreach ($tables as $t): ?>
            <tr>
              <td><code><?= e($t) ?></code></td>
              <td><b><?= (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn() ?></b> rows</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel-card">
    <h3 class="panel-title" style="font-size: 18px;">Database Backup</h3>
    <p style="font-size: 14px; color: var(--slate-500); margin: 0 0 16px 0;">Download a complete SQL dump of the database. Can be imported in phpMyAdmin.</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="backup">
      <button class="btn-primary">Download SQL backup (.sql)</button>
    </form>
  </div>

<?php elseif ($tab === 'analytics'): 
  // Part C: Data Analytics Queries for Campus Administration
  $catStats = $pdo->query("SELECT c.name AS category, COUNT(i.item_id) AS total,
                            SUM(CASE WHEN i.item_type = 'lost' THEN 1 ELSE 0 END) AS lost_cnt,
                            SUM(CASE WHEN i.item_type = 'found' THEN 1 ELSE 0 END) AS found_cnt,
                            ROUND(SUM(CASE WHEN i.status = 'resolved' THEN 1 ELSE 0 END) * 100.0 / COUNT(i.item_id), 1) AS recovery_pct
                            FROM categories c LEFT JOIN items i ON c.category_id = i.category_id
                            GROUP BY c.category_id, c.name ORDER BY total DESC")->fetchAll();

  $locStats = $pdo->query("SELECT l.building, COUNT(i.item_id) AS total,
                            SUM(CASE WHEN i.item_type = 'lost' THEN 1 ELSE 0 END) AS lost_cnt,
                            SUM(CASE WHEN i.item_type = 'found' THEN 1 ELSE 0 END) AS found_cnt
                            FROM locations l LEFT JOIN items i ON l.location_id = i.location_id
                            GROUP BY l.building ORDER BY total DESC")->fetchAll();

  $totItems = (int) $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
  $totResolved = (int) $pdo->query("SELECT COUNT(*) FROM items WHERE status = 'resolved'")->fetchColumn();
  $overallRate = $totItems > 0 ? round(($totResolved / $totItems) * 100, 1) : 0;
?>
  <!-- Analytics Metric Cards -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="panel-card" style="border-left: 4px solid var(--primary); padding: 18px;">
      <div style="font-size: 13px; color: var(--slate-500); text-transform: uppercase; font-weight: 700;">Total Tracked Items</div>
      <div style="font-size: 32px; font-weight: 800; color: var(--slate-900); margin-top: 4px;"><?= $totItems ?></div>
      <div style="font-size: 12.5px; color: var(--slate-600); margin-top: 4px;">Across 10 campus categories</div>
    </div>
    <div class="panel-card" style="border-left: 4px solid #10b981; padding: 18px;">
      <div style="font-size: 13px; color: var(--slate-500); text-transform: uppercase; font-weight: 700;">Items Recovered / Resolved</div>
      <div style="font-size: 32px; font-weight: 800; color: #10b981; margin-top: 4px;"><?= $totResolved ?></div>
      <div style="font-size: 12.5px; color: var(--slate-600); margin-top: 4px;">Returned successfully to owners</div>
    </div>
    <div class="panel-card" style="border-left: 4px solid #f59e0b; padding: 18px;">
      <div style="font-size: 13px; color: var(--slate-500); text-transform: uppercase; font-weight: 700;">Overall Campus Recovery Rate</div>
      <div style="font-size: 32px; font-weight: 800; color: #f59e0b; margin-top: 4px;"><?= $overallRate ?>%</div>
      <div style="font-size: 12.5px; color: var(--slate-600); margin-top: 4px;">Average across all locations</div>
    </div>
  </div>

  <!-- Table 1: Category Breakdown -->
  <div class="panel-card" style="margin-bottom: 24px;">
    <h2 class="panel-title">1. Category Loss &amp; Recovery Statistics (Part C1, C5)</h2>
    <p style="font-size: 14px; color: var(--slate-600); margin: 0 0 16px 0;">
      Real-time breakdown of lost vs found items and recovery percentage per category from the <code>items</code> table.
    </p>
    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Category</th>
            <th>Total Reports</th>
            <th>Lost</th>
            <th>Found</th>
            <th>Recovery Rate (%)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($catStats as $cs): ?>
            <tr>
              <td><b><?= e($cs['category']) ?></b></td>
              <td><?= (int) $cs['total'] ?></td>
              <td><span style="color: #ef4444; font-weight: 600;"><?= (int) $cs['lost_cnt'] ?></span></td>
              <td><span style="color: #2563eb; font-weight: 600;"><?= (int) $cs['found_cnt'] ?></span></td>
              <td>
                <div style="display: flex; align-items: center; gap: 8px;">
                  <div style="flex: 1; max-width: 100px; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                    <div style="width: <?= min(100, (float) $cs['recovery_pct']) ?>%; height: 100%; background: #10b981;"></div>
                  </div>
                  <b><?= $cs['recovery_pct'] ?>%</b>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Table 2: Campus Hotspots -->
  <div class="panel-card">
    <h2 class="panel-title">2. Campus Loss Hotspots by Building</h2>
    <p style="font-size: 14px; color: var(--slate-600); margin: 0 0 16px 0;">
      Locations with the highest number of reported lost and found incidents.
    </p>
    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Campus Building / Area</th>
            <th>Total Incidents</th>
            <th>Lost Items</th>
            <th>Found Items</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($locStats as $ls): ?>
            <tr>
              <td><b><?= e($ls['building']) ?></b></td>
              <td><b><?= (int) $ls['total'] ?></b></td>
              <td><span style="color: #ef4444;"><?= (int) $ls['lost_cnt'] ?></span></td>
              <td><span style="color: #2563eb;"><?= (int) $ls['found_cnt'] ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php elseif ($tab === 'reports'):
  $rows = $pdo->query('SELECT r.report_id, r.reason, r.status, r.created_at, r.item_id, i.title, u.full_name AS reporter
                       FROM reports r JOIN items i ON i.item_id = r.item_id JOIN users u ON u.user_id = r.reporter_id ORDER BY r.status = \'new\' DESC, r.created_at DESC LIMIT 60')->fetchAll(); ?>
  <div class="panel-card">
    <h2 class="panel-title">User Reports</h2>
    <?php if (!$rows): ?>
      <p style="color: var(--slate-500); font-size: 14px; margin: 0;">No reports filed yet.</p>
    <?php else: ?>
      <div class="table-container">
        <table class="data-table">
          <thead><tr><th>Listing</th><th>Reason</th><th>Reported by</th><th>Date</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><a href="item.php?id=<?= (int) $r['item_id'] ?>" style="font-weight: 600;"><?= e($r['title']) ?></a></td>
              <td><?= e($r['reason']) ?></td>
              <td><?= e($r['reporter']) ?></td>
              <td><?= e(substr($r['created_at'], 0, 10)) ?></td>
              <td>
                <span class="tag" style="background: <?= $r['status'] === 'new' ? '#fee2e2' : '#f1f5f9' ?>; color: <?= $r['status'] === 'new' ? '#991b1b' : '#475569' ?>;">
                  <?= e($r['status']) ?>
                </span>
              </td>
              <td>
                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                  <?php if ($r['status'] === 'new'): ?>
                    <form method="post" style="margin: 0;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="tab" value="reports">
                      <input type="hidden" name="action" value="report_reviewed">
                      <input type="hidden" name="id" value="<?= (int) $r['report_id'] ?>">
                      <button class="btn-secondary" style="padding: 6px 12px; font-size: 13px;">Review</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" style="margin: 0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="tab" value="reports">
                    <input type="hidden" name="action" value="delete_item">
                    <input type="hidden" name="id" value="<?= (int) $r['item_id'] ?>">
                    <button class="btn-danger" style="padding: 6px 12px; font-size: 13px;" data-confirm="Delete the reported listing?">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'users'):
  $rows = $pdo->query('SELECT u.user_id, u.email, u.full_name, u.role, u.is_blocked, (SELECT COUNT(*) FROM items i WHERE i.user_id = u.user_id) AS posts FROM users u ORDER BY u.role, u.full_name LIMIT 100')->fetchAll(); ?>
  <div class="panel-card">
    <h2 class="panel-title">User Accounts</h2>
    <div class="table-container">
      <table class="data-table">
        <thead><tr><th>Full name</th><th>University e-mail</th><th>Role</th><th>Listings</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><b><?= e($r['full_name']) ?></b></td>
            <td><?= e($r['email']) ?></td>
            <td><span class="tag" style="background:#e0f2fe; color:#0369a1; text-transform:uppercase;"><?= e($r['role']) ?></span></td>
            <td><?= (int) $r['posts'] ?></td>
            <td>
              <span class="tag" style="background: <?= $r['is_blocked'] ? '#fee2e2' : '#dcfce7' ?>; color: <?= $r['is_blocked'] ? '#991b1b' : '#166534' ?>;">
                <?= $r['is_blocked'] ? 'Blocked' : 'Active' ?>
              </span>
            </td>
            <td>
              <?php if ($r['role'] !== 'admin'): ?>
                <div style="display: flex; justify-content: flex-end;">
                  <form method="post" style="margin: 0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="tab" value="users">
                    <input type="hidden" name="action" value="<?= $r['is_blocked'] ? 'unblock' : 'block' ?>">
                    <input type="hidden" name="id" value="<?= (int) $r['user_id'] ?>">
                    <button class="<?= $r['is_blocked'] ? 'btn-secondary' : 'btn-danger' ?>" style="padding: 6px 12px; font-size: 13px;" <?= $r['is_blocked'] ? '' : 'data-confirm="Block this user?"' ?>>
                      <?= $r['is_blocked'] ? 'Unblock' : 'Block' ?>
                    </button>
                  </form>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php else:
  $rows = $pdo->query('SELECT g.created_at, g.action, g.details, a.full_name AS admin, i.title FROM admin_logs g JOIN users a ON a.user_id = g.admin_id LEFT JOIN items i ON i.item_id = g.item_id ORDER BY g.created_at DESC LIMIT 50')->fetchAll(); ?>
  <div class="panel-card">
    <h2 class="panel-title">Moderation Activity Log</h2>
    <div class="table-container">
      <table class="data-table">
        <thead><tr><th>Timestamp</th><th>Admin</th><th>Action</th><th>Details</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td style="color: var(--slate-500);"><?= e($r['created_at']) ?></td>
            <td><b><?= e($r['admin']) ?></b></td>
            <td><code><?= e($r['action']) ?></code></td>
            <td><?= e($r['details'] ?? '') ?><?= $r['title'] ? ' (<b>' . e($r['title']) . '</b>)' : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; 

page_footer();
