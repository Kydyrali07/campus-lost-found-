<?php
require __DIR__ . '/includes/app.php';
$pdo = db();

$type = in_array($_GET['type'] ?? '', ['lost', 'found'], true) ? $_GET['type'] : '';

$where = ["i.status = 'open'"];
$params = [];
if ($type !== '') {
    $where[] = "i.item_type = ?";
    $params[] = $type;
}

$sql = "SELECT l.location_id, l.building, l.place, l.latitude, l.longitude,
               COUNT(i.item_id) as total_items,
               SUM(CASE WHEN i.item_type = 'lost' THEN 1 ELSE 0 END) as lost_count,
               SUM(CASE WHEN i.item_type = 'found' THEN 1 ELSE 0 END) as found_count
        FROM locations l
        LEFT JOIN items i ON i.location_id = l.location_id AND " . implode(' AND ', $where) . "
        GROUP BY l.location_id, l.building, l.place, l.latitude, l.longitude
        ORDER BY total_items DESC, l.building, l.place";
$st = $pdo->prepare($sql);
$st->execute($params);
$locations = $st->fetchAll();

// Also fetch top recent items for each location to show in popup
$itemsSql = "SELECT i.item_id, i.title, i.item_type, i.location_id, i.event_date 
             FROM items i 
             WHERE i.status = 'open' " . ($type ? "AND i.item_type = ?" : "") . " 
             ORDER BY i.event_date DESC LIMIT 100";
$st = $pdo->prepare($itemsSql);
$st->execute($type ? [$type] : []);
$allItems = $st->fetchAll();

$itemsByLocation = [];
foreach ($allItems as $it) {
    $itemsByLocation[$it['location_id']][] = [
        'id' => (int) $it['item_id'],
        'title' => $it['title'],
        'type' => $it['item_type'],
        'date' => relative_date($it['event_date']),
    ];
}

page_header('Campus Map', 'map');
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
  #campusMap {
    height: 520px;
    width: 100%;
    border-radius: var(--radius-lg);
    border: 1px solid var(--slate-200);
    z-index: 1;
    box-shadow: var(--shadow-sm);
  }
  .map-card-container {
    background: var(--bg-card);
    border: 1px solid var(--slate-200);
    border-radius: var(--radius-xl);
    padding: 24px;
    box-shadow: var(--shadow-sm);
    margin-bottom: 24px;
  }
  .map-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 18px;
    flex-wrap: wrap;
  }
  .map-filters {
    display: flex;
    gap: 8px;
  }
  .map-filter-btn {
    padding: 8px 16px;
    border-radius: var(--radius-pill);
    border: 1px solid var(--slate-200);
    background: var(--bg-card);
    font-size: 13.5px;
    font-weight: 600;
    color: var(--slate-700);
    text-decoration: none;
    transition: all 0.15s ease;
  }
  .map-filter-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
  }
  .map-filter-btn.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
  }
  .leaflet-popup-content {
    font-family: var(--font-family);
    margin: 12px 14px;
    line-height: 1.4;
  }
  .popup-loc-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--slate-900);
    margin-bottom: 8px;
    border-bottom: 1px solid var(--slate-200);
    padding-bottom: 4px;
  }
  .popup-item-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 4px 0;
    font-size: 13px;
    color: var(--slate-800);
    text-decoration: none;
  }
  .popup-item-link:hover {
    color: var(--primary);
  }
</style>

<div class="map-card-container">
  <div class="map-header-row">
    <div>
      <h1 style="font-size: 26px; font-weight: 800; color: var(--slate-900); margin: 0 0 4px 0;">Interactive Campus Map</h1>
      <p style="font-size: 14px; color: var(--slate-500); margin: 0;">Explore lost &amp; found items across Astana IT University campus buildings.</p>
    </div>
    <div class="map-filters">
      <a href="map.php" class="map-filter-btn<?= $type === '' ? ' active' : '' ?>">All items</a>
      <a href="map.php?type=lost" class="map-filter-btn<?= $type === 'lost' ? ' active' : '' ?>">Lost items</a>
      <a href="map.php?type=found" class="map-filter-btn<?= $type === 'found' ? ' active' : '' ?>">Found items</a>
    </div>
  </div>

  <div id="campusMap"></div>
</div>

<div class="panel-card">
  <h2 class="panel-title">Campus Locations Directory</h2>
  <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
    <?php foreach ($locations as $loc): ?>
      <div style="border: 1px solid var(--slate-200); border-radius: var(--radius-md); padding: 14px 16px; background: var(--slate-50); display: flex; justify-content: space-between; align-items: center;">
        <div>
          <b style="font-size: 14.5px; color: var(--slate-900); display: block;"><?= e(location_label($loc['building'], $loc['place'])) ?></b>
          <span style="font-size: 13px; color: var(--slate-500);"><?= e($loc['building']) ?>: <?= e($loc['place']) ?></span>
        </div>
        <div style="text-align: right;">
          <span class="badge-count" style="background: <?= (int)$loc['total_items'] > 0 ? 'var(--primary)' : 'var(--slate-400)' ?>; font-size: 12px; padding: 3px 8px;">
            <?= (int)$loc['total_items'] ?> items
          </span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var mapElement = document.getElementById('campusMap');
  if (!mapElement || typeof L === 'undefined') return;

  // Center on Astana IT University (EXPO Business Center, Block C1)
  var map = L.map('campusMap').setView([51.0897, 71.4172], 17);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors &middot; Astana IT University (EXPO C1)'
  }).addTo(map);

  var locationsData = <?= json_encode($locations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var itemsData = <?= json_encode($itemsByLocation, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

  locationsData.forEach(function (loc) {
    if (!loc.latitude || !loc.longitude) return;

    var count = parseInt(loc.total_items, 10);
    var color = count > 0 ? '#2563eb' : '#94a3b8';

    var circle = L.circleMarker([parseFloat(loc.latitude), parseFloat(loc.longitude)], {
      radius: Math.max(9, Math.min(20, 8 + count * 2)),
      fillColor: color,
      color: '#ffffff',
      weight: 2,
      opacity: 1,
      fillOpacity: 0.85
    }).addTo(map);

    var popupHtml = '<div class="popup-loc-title">' + (loc.building + ': ' + loc.place) + '</div>';
    popupHtml += '<div style="font-size:12px; color:#64748b; margin-bottom:6px;">' + count + ' listing(s) here</div>';

    var locItems = itemsData[loc.location_id] || [];
    if (locItems.length > 0) {
      popupHtml += '<div style="max-height:160px; overflow-y:auto;">';
      locItems.slice(0, 5).forEach(function (it) {
        var tagStyle = it.type === 'lost' ? 'background:#0f172a; color:#fff;' : 'background:#fef3c7; color:#92400e;';
        popupHtml += '<a class="popup-item-link" href="item.php?id=' + it.id + '">';
        popupHtml += '<span>' + it.title + '</span>';
        popupHtml += '<span style="font-size:10px; font-weight:700; padding:1px 6px; border-radius:99px; ' + tagStyle + '">' + it.type + '</span>';
        popupHtml += '</a>';
      });
      if (locItems.length > 5) {
        popupHtml += '<div style="font-size:11px; color:#64748b; margin-top:4px;">+' + (locItems.length - 5) + ' more items</div>';
      }
      popupHtml += '</div>';
    } else {
      popupHtml += '<div style="font-size:13px; color:#94a3b8;">No active items right now.</div>';
    }

    circle.bindPopup(popupHtml);
  });
});
</script>

<?php 
page_footer();
