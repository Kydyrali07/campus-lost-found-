<?php
require __DIR__ . '/includes/app.php';
$me = require_login();
$pdo = db();

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'resolve') {
        $st = $pdo->prepare("UPDATE items SET status = 'resolved' WHERE item_id = ? AND user_id = ?");
        $st->execute([$id, $me['id']]); flash($st->rowCount() ? 'Marked as resolved.' : 'Listing not found.', $st->rowCount() ? 'ok' : 'err');
    } elseif ($action === 'delete') {
        $st = $pdo->prepare('DELETE FROM items WHERE item_id = ? AND user_id = ?');
        $st->execute([$id, $me['id']]); flash($st->rowCount() ? 'Listing deleted.' : 'Listing not found.', $st->rowCount() ? 'ok' : 'err');
    } elseif ($action === 'read_all') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$me['id']]); flash('All notifications marked as read.');
    }
    redirect('my.php');
}

$st = $pdo->prepare('SELECT i.item_id, i.title, i.item_type, i.status, i.event_date, l.building, l.place
                     FROM items i JOIN locations l ON l.location_id = i.location_id WHERE i.user_id = ? ORDER BY i.created_at DESC');
$st->execute([$me['id']]);
$mine = $st->fetchAll();

$st = $pdo->prepare('SELECT n.is_read, n.created_at, m.item_id, m.body, i.title, s.full_name AS from_name
                     FROM notifications n JOIN messages m ON m.message_id = n.message_id JOIN items i ON i.item_id = m.item_id JOIN users s ON s.user_id = m.sender_id
                     WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT 30');
$st->execute([$me['id']]);
$notes = $st->fetchAll();

page_header('My account', 'my');
?>
<div style="margin-bottom: 24px;">
  <div style="background: var(--bg-card); border: 1px solid var(--slate-200); border-radius: var(--radius-lg); padding: 24px 28px; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; box-shadow: var(--shadow-sm);">
    <div style="display: flex; align-items: center; gap: 16px;">
      <div style="width: 52px; height: 52px; border-radius: 50%; background: #dbeafe; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 800;">
        <?= strtoupper(substr($me['name'], 0, 1)) ?>
      </div>
      <div>
        <h1 style="font-size: 22px; font-weight: 800; color: var(--slate-900); margin: 0 0 4px 0;"><?= e($me['name']) ?></h1>
        <div style="font-size: 14px; color: var(--slate-500); display: flex; align-items: center; gap: 10px;">
          <span><?= e($me['email'] ?? '') ?></span>
          <span>&middot;</span>
          <span style="background: var(--slate-100); padding: 2px 8px; border-radius: 9999px; font-weight: 600; font-size: 12px; color: var(--slate-700); text-transform: uppercase;"><?= e($me['role']) ?></span>
        </div>
      </div>
    </div>
    <div>
      <a href="post.php" class="btn-primary" style="padding: 10px 22px; border-radius: var(--radius-pill);">+ Post new item</a>
    </div>
  </div>
</div>

<div class="panel-card">
  <h2 class="panel-title" style="margin-bottom: 18px;">My listings (<?= count($mine) ?>)</h2>
  <?php if (!$mine): ?>
    <div class="empty-state-box" style="padding: 32px 16px;">
      <p style="margin: 0 0 14px 0;">You have not published any lost or found items yet.</p>
      <a href="post.php" class="btn-primary">Post your first item</a>
    </div>
  <?php else: ?>
    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Item title</th>
            <th>Type</th>
            <th>Campus location</th>
            <th>Date</th>
            <th>Status</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($mine as $i): ?>
          <tr>
            <td>
              <a href="item.php?id=<?= (int) $i['item_id'] ?>" style="font-weight: 600; color: var(--slate-900);">
                <?= e($i['title']) ?>
              </a>
            </td>
            <td><?= tag_html($i['item_type']) ?></td>
            <td><?= e(location_label($i['building'], $i['place'])) ?></td>
            <td><?= relative_date($i['event_date']) ?></td>
            <td>
              <?php if ($i['status'] === 'resolved'): ?>
                <span class="tag tag-resolved">Resolved</span>
              <?php else: ?>
                <span class="tag" style="background:#e0f2fe; color:#0369a1;">Open</span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                <?php if ($i['status'] === 'open'): ?>
                  <form method="post" style="margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="resolve">
                    <input type="hidden" name="id" value="<?= (int) $i['item_id'] ?>">
                    <button class="btn-secondary" style="padding: 6px 12px; font-size: 13px;">Mark resolved</button>
                  </form>
                <?php endif; ?>
                <form method="post" style="margin:0;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $i['item_id'] ?>">
                  <button class="btn-danger" style="padding: 6px 12px; font-size: 13px;" data-confirm="Are you sure you want to delete this listing?">Delete</button>
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

<div class="panel-card">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; flex-wrap: wrap; gap: 10px;">
    <h2 class="panel-title" style="margin: 0;">Inquiries &amp; Notifications</h2>
    <?php if ($notes): ?>
      <form method="post" style="margin: 0;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="read_all">
        <button class="btn-secondary" style="font-size: 13px; padding: 6px 14px;">Mark all as read</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!$notes): ?>
    <p style="color: var(--slate-500); font-size: 14px; margin: 0;">No inquiries or messages yet.</p>
  <?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 10px;">
      <?php foreach ($notes as $n): ?>
        <div style="padding: 14px 18px; border-radius: var(--radius-md); border: 1px solid <?= $n['is_read'] ? 'var(--slate-200)' : '#93c5fd' ?>; background: <?= $n['is_read'] ? 'var(--bg-card)' : '#f0f7ff' ?>; display: flex; flex-direction: column; gap: 6px;">
          <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13.5px;">
            <span>
              <b><?= e($n['from_name']) ?></b> wrote regarding 
              <a href="item.php?id=<?= (int) $n['item_id'] ?>" style="font-weight: 600;">
                <?= e($n['title']) ?>
              </a>
            </span>
            <span style="color: var(--slate-400); font-size: 12px;"><?= e($n['created_at']) ?></span>
          </div>
          <div style="font-size: 14px; color: var(--slate-700); line-height: 1.4;">
            <?= nl2br(e($n['body'])) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php page_footer();
