<?php
require __DIR__ . '/includes/app.php';
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT i.*, c.name AS category, l.building, l.place, u.full_name AS owner, u.user_id AS owner_id
                     FROM items i JOIN categories c ON c.category_id = i.category_id JOIN locations l ON l.location_id = i.location_id JOIN users u ON u.user_id = i.user_id
                     WHERE i.item_id = ?');
$st->execute([$id]);
$item = $st->fetch();
if (!$item) { http_response_code(404); page_header('Not found'); echo '<h1>Listing not found</h1><p><a href="index.php">Back to the feed</a></p>'; page_footer(); exit; }

$me = user();
$isOwner = $me && (int) $me['id'] === (int) $item['owner_id'];
$isAdmin = $me && $me['role'] === 'admin';

if (is_post()) {
    csrf_check();
    $me = require_login();
    $action = $_POST['action'] ?? '';
    if ($action === 'resolve' && ($isOwner || $isAdmin)) {
        $pdo->prepare("UPDATE items SET status = 'resolved' WHERE item_id = ?")->execute([$id]);
        flash('Marked as resolved. Great news!');
    } elseif ($action === 'delete' && ($isOwner || $isAdmin)) {
        $pdo->beginTransaction();
        if ($isAdmin && !$isOwner) $pdo->prepare("INSERT INTO admin_logs (admin_id, item_id, action, details) VALUES (?, ?, 'delete_post', ?)")->execute([$me['id'], $id, 'Deleted listing: ' . mb_substr($item['title'], 0, 100)]);
        $pdo->prepare('DELETE FROM items WHERE item_id = ?')->execute([$id]);
        $pdo->commit();
        flash('Listing deleted.'); redirect('index.php');
    } elseif ($action === 'report' && !$isOwner) {
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if (mb_strlen($reason) < 3) flash('Please describe the problem.', 'err');
        else { $pdo->prepare('INSERT INTO reports (item_id, reporter_id, reason) VALUES (?, ?, ?)')->execute([$id, $me['id'], mb_substr($reason, 0, 255)]); flash('Thank you. The report was sent to the moderators.'); }
    } elseif ($action === 'message') {
        $body = trim((string) ($_POST['body'] ?? ''));
        $receiver = $isOwner ? (int) ($_POST['receiver_id'] ?? 0) : (int) $item['owner_id'];
        $ok = $body !== '' && $receiver > 0 && $receiver !== (int) $me['id'];
        if ($ok && $isOwner) {                                         // owner may only answer people who wrote to this listing
            $chk = $pdo->prepare('SELECT 1 FROM messages WHERE item_id = ? AND sender_id = ? AND receiver_id = ? LIMIT 1');
            $chk->execute([$id, $receiver, $me['id']]); $ok = (bool) $chk->fetch();
        }
        if (!$ok) flash('Message could not be sent.', 'err');
        else {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO messages (item_id, sender_id, receiver_id, body) VALUES (?, ?, ?, ?)')->execute([$id, $me['id'], $receiver, mb_substr($body, 0, 1000)]);
            $pdo->prepare("INSERT INTO notifications (user_id, message_id, type) VALUES (?, ?, 'reply')")->execute([$receiver, $pdo->lastInsertId()]);
            $pdo->commit();
            flash('Message sent.');
        }
    }
    redirect('item.php?id=' . $id);
}

$messages = []; $partners = [];
if ($me) {
    $st = $pdo->prepare('SELECT m.*, s.full_name AS sender FROM messages m JOIN users s ON s.user_id = m.sender_id
                         WHERE m.item_id = ? AND (m.sender_id = ? OR m.receiver_id = ?) ORDER BY m.sent_at, m.message_id');
    $st->execute([$id, $me['id'], $me['id']]);
    $messages = $st->fetchAll();
    $pdo->prepare('UPDATE notifications n JOIN messages m ON m.message_id = n.message_id SET n.is_read = 1 WHERE n.user_id = ? AND m.item_id = ?')->execute([$me['id'], $id]);
    if ($isOwner) { $st = $pdo->prepare('SELECT DISTINCT s.user_id, s.full_name FROM messages m JOIN users s ON s.user_id = m.sender_id WHERE m.item_id = ? AND m.receiver_id = ?'); $st->execute([$id, $me['id']]); $partners = $st->fetchAll(); }
}
page_header($item['title']);
?>
<a href="index.php" class="back-link">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
  Back to feed
</a>

<div class="item-detail-layout">
  <div class="item-detail-media">
    <?= photo_html($item) ?>
  </div>
  <div class="item-detail-body">
    <div class="detail-badge-row">
      <?= tag_html($item['item_type']) ?>
      <?php if ($item['status'] === 'resolved'): ?>
        <span class="tag tag-resolved">Resolved</span>
      <?php endif; ?>
    </div>
    
    <h1 class="item-detail-title"><?= e($item['title']) ?></h1>
    
    <div class="detail-meta-box">
      <div class="detail-meta-item"><b>Category:</b> <?= e($item['category']) ?></div>
      <div class="detail-meta-item"><b>Location:</b> <?= e($item['building']) ?> &middot; <?= e($item['place']) ?></div>
      <div class="detail-meta-item"><b>Date:</b> <?= relative_date($item['event_date']) ?> (<?= e($item['event_date']) ?>)</div>
      <div class="detail-meta-item"><b>Posted by:</b> <?= e($item['owner']) ?></div>
    </div>
    
    <div class="item-detail-desc">
      <?= nl2br(e($item['description'] ?: 'No additional description provided.')) ?>
    </div>
    
    <?php if ($isOwner || $isAdmin): ?>
    <div class="detail-actions-row">
      <?php if ($item['status'] === 'open'): ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="resolve">
          <button class="btn-primary">Mark as resolved</button>
        </form>
      <?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <button class="btn-danger" data-confirm="Are you sure you want to permanently delete this listing?">Delete listing</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="panel-card">
  <h2 class="panel-title">Messages &amp; Inquiries</h2>
  <?php if (!$me): ?>
    <p style="color:var(--slate-600); font-size:14.5px;">
      <a href="login.php?next=<?= urlencode('item.php?id=' . $id) ?>" style="font-weight:600;">Log in</a> with your university account to contact the poster.
    </p>
  <?php else: ?>
    <div class="chat-thread">
      <?php foreach ($messages as $m): ?>
        <div class="chat-bubble<?= (int) $m['sender_id'] === (int) $me['id'] ? ' me' : '' ?>">
          <div class="chat-bubble-header">
            <b><?= e($m['sender']) ?></b>
            <span><?= e($m['sent_at']) ?></span>
          </div>
          <div class="chat-bubble-content">
            <?= nl2br(e($m['body'])) ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$messages): ?>
        <p style="color:var(--slate-500); font-size:14px; margin:0;">No messages yet. Send a message below to coordinate recovery.</p>
      <?php endif; ?>
    </div>

    <?php if (($isOwner && $partners) || (!$isOwner && $item['status'] === 'open')): ?>
    <form class="form-container" method="post" style="margin-top:20px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="message">
      <?php if ($isOwner): ?>
        <div class="form-group">
          <label class="form-label">Reply to</label>
          <select name="receiver_id" class="form-select">
            <?php foreach ($partners as $p): ?>
              <option value="<?= (int) $p['user_id'] ?>"><?= e($p['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div class="form-group">
        <label class="form-label"><?= $isOwner ? 'Your reply' : 'Message to poster' ?></label>
        <textarea name="body" class="form-textarea" required maxlength="1000" placeholder="Ask where to meet, details about the item, etc..."></textarea>
      </div>
      <button class="btn-primary">Send message</button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($me && !$isOwner): ?>
<div class="panel-card" style="border-color:#fecaca;">
  <h3 style="font-size:16px; margin:0 0 10px 0; color:#991b1b;">Report an issue</h3>
  <form method="post" style="display:flex; gap:10px; flex-wrap:wrap;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="report">
    <input name="reason" class="form-input" style="flex:1; min-width:260px;" placeholder="Spam, false listing, inappropriate content..." maxlength="255" required>
    <button class="btn-danger">Report</button>
  </form>
</div>
<?php endif; 

page_footer();

