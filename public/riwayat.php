<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

$userId = require_login();
$pdo = $pdo ?? db();

$stmt = $pdo->prepare(
    'SELECT w.worn_on, i.id AS item_id, i.name, i.image, c.name AS category_name
     FROM wear_logs w
     JOIN items i ON i.id = w.item_id
     JOIN categories c ON c.id = i.category_id
     WHERE i.user_id = ?
     ORDER BY w.worn_on DESC, i.name'
);
$stmt->execute([$userId]);
$logs = $stmt->fetchAll();

// Kelompokkan per tanggal. Query sudah urut, jadi urutan blok ikut terjaga.
$days = [];
foreach ($logs as $log) {
    $days[$log['worn_on']][] = $log;
}

$itemsWorn = count(array_unique(array_column($logs, 'item_id')));

render_header('Riwayat');
?>
<p class="eyebrow rv">Riwayat</p>
<h1 class="page-title rv">Yang pernah dipakai.</h1>

<?php if ($logs): ?>
  <section class="stats rv">
    <div><strong><?= count($days) ?></strong><span>hari tercatat</span></div>
    <div><strong><?= count($logs) ?></strong><span>catatan pemakaian</span></div>
    <div><strong><?= $itemsWorn ?></strong><span>pakaian terpakai</span></div>
  </section>
<?php else: ?>
  <p class="muted center">
    Belum ada catatan. Tandai pakaian dengan <strong>Pakai hari ini</strong>
    di <a href="index.php">halaman Lemari</a>, lalu riwayatnya muncul di sini.
  </p>
<?php endif; ?>

<?php foreach ($days as $date => $entries): ?>
  <section class="wardrobe-section">
    <div class="section-head rv">
      <h2><?= e(format_date_id((string) $date)) ?></h2>
      <span class="count"><?= count($entries) ?> item</span>
    </div>
    <div class="grid">
      <?php foreach ($entries as $entry): ?>
        <article class="card card-mini rv">
          <div class="card-media">
            <?php if ($entry['image']): ?>
              <img src="uploads/<?= e($entry['image']) ?>" alt="" loading="lazy">
            <?php else: ?>
              <div class="noimg">Tanpa foto</div>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <h3><?= e($entry['name']) ?></h3>
            <p><?= e($entry['category_name']) ?></p>
            <div class="actions">
              <form method="post" action="wear.php" onsubmit="return confirm('Hapus catatan pemakaian ini?')">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="item_id" value="<?= (int) $entry['item_id'] ?>">
                <input type="hidden" name="worn_on" value="<?= e($entry['worn_on']) ?>">
                <input type="hidden" name="action" value="unwear">
                <input type="hidden" name="back" value="riwayat">
                <button type="submit" class="link danger">Hapus catatan</button>
              </form>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
<?php render_footer();
