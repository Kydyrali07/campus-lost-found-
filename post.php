<?php
require __DIR__ . '/includes/app.php';
$me = require_login();
$pdo = db();
$cats = $pdo->query('SELECT category_id, name FROM categories ORDER BY name')->fetchAll();
$locs = $pdo->query('SELECT location_id, building, place FROM locations ORDER BY building, place')->fetchAll();
$errors = [];
$v = ['item_type' => 'lost', 'title' => '', 'category_id' => '', 'location_id' => '', 'event_date' => date('Y-m-d'), 'description' => ''];

if (is_post()) {
    csrf_check();
    foreach ($v as $k => $_) $v[$k] = trim((string) ($_POST[$k] ?? ''));
    if (!in_array($v['item_type'], ['lost', 'found'], true)) $errors[] = 'Choose lost or found.';
    if (mb_strlen($v['title']) < 3 || mb_strlen($v['title']) > 100) $errors[] = 'Title must be 3 to 100 characters.';
    if (!(int) $v['category_id']) $errors[] = 'Choose a category.';
    if (!(int) $v['location_id']) $errors[] = 'Choose a location.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['event_date']) || $v['event_date'] > date('Y-m-d')) $errors[] = 'Date cannot be in the future.';
    if (mb_strlen($v['description']) > 2000) $errors[] = 'Description is too long.';

    $photo = null;
    $f = $_FILES['photo'] ?? null;
    if (!$errors && $f && $f['error'] !== UPLOAD_ERR_NO_FILE) {
        $info = ($f['error'] === UPLOAD_ERR_OK && $f['size'] <= 2 * 1024 * 1024) ? @getimagesize($f['tmp_name']) : false;
        $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
        if (!$ext) $errors[] = 'Photo must be a JPG, PNG or WEBP image up to 2 MB.';
        else {
            $dir = __DIR__ . '/uploads';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $name = bin2hex(random_bytes(12)) . '.' . $ext;
            if (move_uploaded_file($f['tmp_name'], "$dir/$name")) $photo = 'uploads/' . $name; else $errors[] = 'Could not save the photo.';
        }
    }
    if (!$errors) {
        try {
            $pdo->prepare('INSERT INTO items (user_id, category_id, location_id, item_type, title, description, event_date, photo_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$me['id'], (int) $v['category_id'], (int) $v['location_id'], $v['item_type'], $v['title'], $v['description'], $v['event_date'], $photo]);
            flash('Your listing was published.');
            redirect('item.php?id=' . $pdo->lastInsertId());
        } catch (PDOException $e) { $errors[] = 'Invalid category or location.'; }
    }
}
page_header('Post an item', 'post');
?>
<div style="max-width: 680px; margin: 0 auto;">
  <a href="index.php" class="back-link">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
    Back to feed
  </a>

  <div class="form-card">
    <h1 style="font-size: 26px; font-weight: 800; color: var(--slate-900); margin: 0 0 6px 0; letter-spacing: -0.02em;">Post an item</h1>
    <p style="font-size: 14.5px; color: var(--slate-500); margin: 0 0 24px 0;">Add distinctive details and location so owners can quickly recover their belongings.</p>

    <?php foreach ($errors as $er): ?>
      <div class="flash-alert flash-err"><?= e($er) ?></div>
    <?php endforeach; ?>

    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label">Type of report</label>
        <div class="type-toggle-group">
          <label class="type-toggle-label <?= $v['item_type'] === 'lost' ? 'active-lost' : '' ?>">
            <input type="radio" name="item_type" value="lost" <?= $v['item_type'] === 'lost' ? 'checked' : '' ?> onchange="this.form.querySelectorAll('.type-toggle-label').forEach(el=>el.classList.remove('active-lost','active-found')); this.parentElement.classList.add('active-lost');">
            I lost an item
          </label>
          <label class="type-toggle-label <?= $v['item_type'] === 'found' ? 'active-found' : '' ?>">
            <input type="radio" name="item_type" value="found" <?= $v['item_type'] === 'found' ? 'checked' : '' ?> onchange="this.form.querySelectorAll('.type-toggle-label').forEach(el=>el.classList.remove('active-lost','active-found')); this.parentElement.classList.add('active-found');">
            I found an item
          </label>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Item title</label>
        <input name="title" class="form-input" value="<?= e($v['title']) ?>" required minlength="3" maxlength="100" placeholder="e.g. Blue backpack, AirPods Pro, Student ID">
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category_id" class="form-select" required>
            <option value="">Choose category...</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int) $c['category_id'] ?>" <?= (string) $c['category_id'] === $v['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Date</label>
          <input type="date" name="event_date" class="form-input" value="<?= e($v['event_date']) ?>" max="<?= date('Y-m-d') ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Campus location</label>
        <select name="location_id" class="form-select" required>
          <option value="">Choose campus location...</option>
          <?php foreach ($locs as $l): ?>
            <option value="<?= (int) $l['location_id'] ?>" <?= (string) $l['location_id'] === $v['location_id'] ? 'selected' : '' ?>><?= e($l['building'] . ': ' . $l['place']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Description &amp; distinctive features</label>
        <textarea name="description" class="form-textarea" maxlength="2000" placeholder="Color, stickers, scratches, exact spot where it was left or found..."><?= e($v['description']) ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Photo (optional, JPG/PNG/WEBP, up to 2 MB)</label>
        <input type="file" name="photo" class="form-input" accept="image/jpeg,image/png,image/webp" id="photoFileInput">
        <div id="photoPreviewBox" style="margin-top: 10px; display: none;">
          <img id="photoPreviewImg" src="" alt="Preview" style="max-height: 180px; border-radius: 8px; border: 1px solid var(--slate-200); object-fit: cover;">
        </div>
      </div>

      <div style="display: flex; gap: 12px; margin-top: 24px; align-items: center;">
        <button class="btn-primary" style="padding: 12px 28px; font-size: 15px;">Publish listing</button>
        <a class="btn-secondary" href="index.php">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php page_footer();
