<?php
require __DIR__ . '/includes/app.php';
$pdo = db();

$q        = trim((string) ($_GET['q'] ?? ''));
$cat      = (int) ($_GET['category'] ?? 0);
$type     = in_array($_GET['type'] ?? '', ['lost', 'found'], true) ? $_GET['type'] : '';
$building = trim((string) ($_GET['building'] ?? ''));
$status   = ($_GET['status'] ?? 'open') === 'resolved' ? 'resolved' : 'open';
$page     = max(1, (int) ($_GET['page'] ?? 1));
$per      = 12;

$where = ['i.status = ?']; 
$params = [$status];

if ($q !== '') { 
    $like = '%' . addcslashes($q, '%_\\') . '%'; 
    $where[] = '(i.title LIKE ? OR i.description LIKE ? OR l.place LIKE ? OR l.building LIKE ?)'; 
    array_push($params, $like, $like, $like, $like); 
}
if ($cat > 0)  { 
    $where[] = 'i.category_id = ?'; 
    $params[] = $cat; 
}
if ($type !== '') { 
    $where[] = 'i.item_type = ?'; 
    $params[] = $type; 
}
if ($building !== '') { 
    $where[] = 'l.building = ?'; 
    $params[] = $building; 
}

$from = 'FROM items i JOIN categories c ON c.category_id = i.category_id JOIN locations l ON l.location_id = i.location_id JOIN users u ON u.user_id = i.user_id WHERE ' . implode(' AND ', $where);

$st = $pdo->prepare("SELECT COUNT(*) $from"); 
$st->execute($params);
$total = (int) $st->fetchColumn();
$pages = max(1, (int) ceil($total / $per));
$page  = min($page, $pages);
$off   = ($page - 1) * $per;

$st = $pdo->prepare("SELECT i.item_id, i.title, i.item_type, i.status, i.event_date, i.photo_url, c.name AS category, l.building, l.place, u.full_name AS owner
                     $from ORDER BY i.event_date DESC, i.item_id DESC LIMIT $per OFFSET $off");
$st->execute($params);
$items = $st->fetchAll();

$cats = $pdo->query('SELECT category_id, name FROM categories ORDER BY name')->fetchAll();
$buildings = $pdo->query('SELECT DISTINCT building FROM locations ORDER BY building')->fetchAll(PDO::FETCH_COLUMN);

page_header('Feed', 'feed');
?>

<div class="feed-wrapper">
  <h1 class="page-main-heading">Find what you lost on campus</h1>

  <form class="feed-form" method="get" id="filterForm">
    <!-- Search Bar -->
    <div class="search-bar-container">
      <div class="search-input-box">
        <svg class="search-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by keyword, e.g. AirPods" aria-label="Search by keyword">
        <?php if ($q !== ''): ?>
          <a href="index.php" class="clear-search-link" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- 2 Column Layout: Filters Sidebar + Grid -->
    <div class="content-columns">
      <!-- Sidebar -->
      <aside class="sidebar-col">
        <div class="filter-card">
          <!-- Category Section -->
          <div class="filter-section">
            <h2 class="filter-section-title">Category</h2>
            <div class="filter-options-list">
              <label class="filter-option-label">
                <input type="radio" name="category" value="0" <?= $cat === 0 ? 'checked' : '' ?> onchange="this.form.submit()">
                <span class="custom-checkbox-box">
                  <svg class="check-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </span>
                <span class="option-text">All items</span>
              </label>

              <?php foreach ($cats as $c): ?>
              <label class="filter-option-label">
                <input type="radio" name="category" value="<?= (int) $c['category_id'] ?>" <?= $cat === (int) $c['category_id'] ? 'checked' : '' ?> onchange="this.form.submit()">
                <span class="custom-checkbox-box">
                  <svg class="check-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </span>
                <span class="option-text"><?= e($c['name']) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Type Section -->
          <div class="filter-section">
            <h2 class="filter-section-title">Type</h2>
            <div class="filter-options-list">
              <label class="filter-option-label">
                <input type="radio" name="type" value="" <?= $type === '' ? 'checked' : '' ?> onchange="this.form.submit()">
                <span class="custom-checkbox-box">
                  <svg class="check-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </span>
                <span class="option-text">All types</span>
              </label>
              <label class="filter-option-label">
                <input type="radio" name="type" value="lost" <?= $type === 'lost' ? 'checked' : '' ?> onchange="this.form.submit()">
                <span class="custom-checkbox-box">
                  <svg class="check-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </span>
                <span class="option-text">Lost</span>
              </label>
              <label class="filter-option-label">
                <input type="radio" name="type" value="found" <?= $type === 'found' ? 'checked' : '' ?> onchange="this.form.submit()">
                <span class="custom-checkbox-box">
                  <svg class="check-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </span>
                <span class="option-text">Found</span>
              </label>
            </div>
          </div>

          <!-- Building Section -->
          <div class="filter-section">
            <h2 class="filter-section-title">Building</h2>
            <div class="select-dropdown-container">
              <select name="building" class="building-select" onchange="this.form.submit()">
                <option value="">All buildings</option>
                <?php foreach ($buildings as $b): ?>
                <option value="<?= e($b) ?>" <?= $building === $b ? 'selected' : '' ?>><?= e($b) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <?php if ($cat > 0 || $type !== '' || $building !== '' || $q !== ''): ?>
          <div class="filter-reset-container">
            <a href="index.php" class="btn-reset-filters">Reset filters</a>
          </div>
          <?php endif; ?>
        </div>
      </aside>

      <!-- Items Grid Column -->
      <section class="items-grid-col">
        <?php if (!$items): ?>
          <div class="empty-state-box">
            <div class="empty-state-icon">
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
            </div>
            <h3>Nothing found matching your criteria</h3>
            <p>Try searching with another keyword, or choose another category or building.</p>
            <a href="index.php" class="btn-clear-query">Clear all filters</a>
          </div>
        <?php else: ?>
          <div class="feed-grid">
            <?php foreach ($items as $i): ?>
            <div class="feed-item-card">
              <div class="card-media-box">
                <?= photo_html($i) ?>
              </div>
              <div class="card-info-box">
                <div class="card-top-row">
                  <h3 class="card-item-title"><?= e($i['title']) ?></h3>
                  <?= tag_html($i['item_type']) ?>
                </div>
                <div class="card-meta-line"><?= e(location_label($i['building'], $i['place'])) ?> &middot; <?= relative_date($i['event_date']) ?></div>
                <div class="card-cat-line"><?= e($i['category']) ?></div>
                <a class="btn-view-details" href="item.php?id=<?= (int) $i['item_id'] ?>">View details</a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <?php if ($pages > 1): ?>
          <div class="feed-pagination">
            <?php for ($p = 1; $p <= $pages; $p++): $qs = http_build_query(array_merge($_GET, ['page' => $p])); ?>
              <?= $p === $page ? '<span class="pager-item active">' . $p . '</span>' : '<a class="pager-item" href="?' . e($qs) . '">' . $p . '</a>' ?>
            <?php endfor; ?>
          </div>
          <?php endif; ?>
        <?php endif; ?>
      </section>
    </div>
  </form>
</div>

<?php 
page_footer();
