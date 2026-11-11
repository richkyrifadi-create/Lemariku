<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

$userId = require_login();
$pdo = db();

$q = trim((string) ($_GET['q'] ?? ''));
$catId = (int) ($_GET['category'] ?? 0);

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

$sql = 'SELECT i.id, i.name, i.brand, i.color, i.price, i.image, i.is_laundry,
               DATE(i.created_at) AS created_on, c.name AS category_name,
               COUNT(w.id) AS wear_count, MAX(w.worn_on) AS last_worn
        FROM items i
        JOIN categories c ON c.id = i.category_id
        LEFT JOIN wear_logs w ON w.item_id = i.id
        WHERE i.user_id = ?';
$params = [$userId];
if ($q !== '') {
    $sql .= ' AND (i.name LIKE ? OR i.brand LIKE ? OR i.color LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($catId > 0) {
    $sql .= ' AND i.category_id = ?';
    $params[] = $catId;
}
$sql .= ' GROUP BY i.id ORDER BY i.name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$cutoff = date('Y-m-d', strtotime('-' . UNUSED_DAYS . ' days'));
$totalValue = 0.0;
$unusedCount = 0;
$laundryCount = 0;
foreach ($items as &$it) {
    $price = $it['price'] !== null ? (float) $it['price'] : null;
    $count = (int) $it['wear_count'];
    // Cost per wear: harga dibagi jumlah pemakaian. Kalau belum pernah dipakai, tampilkan harga penuh.
    $it['cpw'] = ($price !== null && $count > 0) ? $price / $count : $price;
    $it['price_f'] = $price;
    $since = $it['last_worn'] ?? $it['created_on'];
    $it['unused'] = $since < $cutoff;
    if ($it['unused']) {
        $unusedCount++;
    }
    if ($it['is_laundry']) {
        $laundryCount++;
    }
    $totalValue += $price ?? 0.0;
}
unset($it);

$sections = group_by_category($items);

render_header('Lemari');
?>
<section class="stats">
  <div><strong><?= count($items) ?></strong><span>item</span></div>
  <div><strong><?= $laundryCount ?></strong><span>perlu dicuci</span></div>
  <div><strong><?= $unusedCount ?></strong><span>jarang dipakai (<?= UNUSED_DAYS ?> hari)</span></div>
  <div><strong><?= e(rupiah($totalValue)) ?></strong><span>total nilai</span></div>
</section>

<form class="filters" method="get">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama, merek, atau warna">
  <select name="category">
    <option value="0">Semua kategori</option>
    <?php foreach ($categories as $c): ?>
      <option value="<?= (int) $c['id'] ?>" <?= $catId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Filter</button>
  <?php if ($q !== '' || $catId > 0): ?>
    <a class="ghost-link" href="index.php">Reset</a>
  <?php endif; ?>
</form>

<?php if (!$items): ?>
  <p class="muted">
    <?php if ($q !== '' || $catId > 0): ?>
      Tidak ada pakaian yang cocok dengan filter ini. <a href="index.php">Tampilkan semua</a>.
    <?php else: ?>
      Belum ada pakaian. <a href="item_form.php">Tambah sekarang</a>.
    <?php endif; ?>
  </p>
<?php endif; ?>

<?php if (count($sections) > 1): ?>
  <nav class="section-nav">
    <?php foreach ($sections as $catName => $catItems): ?>
      <a href="#kategori-<?= e(slug($catName)) ?>"><?= e($catName) ?> <span><?= count($catItems) ?></span></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<?php foreach ($sections as $catName => $catItems): ?>
  <section class="wardrobe-section" id="kategori-<?= e(slug($catName)) ?>">
    <div class="section-head">
      <h2><?= e($catName) ?></h2>
      <span class="count"><?= count($catItems) ?> item</span>
    </div>
    <div class="grid">
      <?php foreach ($catItems as $it) {
          render_item_card($it);
      } ?>
    </div>
  </section>
<?php endforeach; ?>
<?php render_footer();
