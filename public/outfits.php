<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

$userId = require_login();
$pdo = db();

$stmt = $pdo->prepare('SELECT id, name FROM items WHERE user_id = ? ORDER BY name');
$stmt->execute([$userId]);
$myItems = $stmt->fetchAll();
$ownedIds = array_map(fn($i) => (int) $i['id'], $myItems);

$errors = [];
$name = '';
$notes = '';
$selected = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete') {
        $outfitId = (int) ($_POST['outfit_id'] ?? 0);
        $pdo->prepare('DELETE FROM outfits WHERE id = ? AND user_id = ?')->execute([$outfitId, $userId]);
        flash_set('Outfit dihapus.');
        redirect('outfits.php');
    }

    if ($action === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        // Hanya terima item yang memang milik user ini.
        $selected = array_values(array_intersect(
            array_map('intval', (array) ($_POST['items'] ?? [])),
            $ownedIds
        ));

        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Nama outfit wajib diisi (maks 100 karakter).';
        }
        if (!$selected) {
            $errors[] = 'Pilih minimal satu pakaian.';
        }

        if (!$errors) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('INSERT INTO outfits (user_id, name, notes) VALUES (?, ?, ?)')
                    ->execute([$userId, $name, $notes !== '' ? $notes : null]);
                $outfitId = (int) $pdo->lastInsertId();
                $link = $pdo->prepare('INSERT INTO outfit_items (outfit_id, item_id) VALUES (?, ?)');
                foreach ($selected as $itemId) {
                    $link->execute([$outfitId, $itemId]);
                }
                $pdo->commit();
            } catch (Throwable $t) {
                $pdo->rollBack();
                throw $t;
            }
            flash_set('Outfit disimpan.');
            redirect('outfits.php');
        }
    }
}

$stmt = $pdo->prepare(
    'SELECT o.id, o.name, o.notes, GROUP_CONCAT(i.name ORDER BY i.name SEPARATOR ", ") AS item_names
     FROM outfits o
     LEFT JOIN outfit_items oi ON oi.outfit_id = o.id
     LEFT JOIN items i ON i.id = oi.item_id
     WHERE o.user_id = ?
     GROUP BY o.id
     ORDER BY o.created_at DESC'
);
$stmt->execute([$userId]);
$outfits = $stmt->fetchAll();

render_header('Outfit');
?>
<h1 class="page-title">Outfit</h1>

<?php if (!$outfits): ?>
  <p class="muted">Belum ada outfit yang disimpan.</p>
<?php endif; ?>

<div class="grid">
  <?php foreach ($outfits as $o): ?>
    <article class="card">
      <h3><?= e($o['name']) ?></h3>
      <p class="muted"><?= e($o['item_names'] ?? '') ?></p>
      <?php if ($o['notes']): ?><p><?= nl2br(e($o['notes'])) ?></p><?php endif; ?>
      <form method="post" onsubmit="return confirm('Hapus outfit ini?')">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="outfit_id" value="<?= (int) $o['id'] ?>">
        <button type="submit" class="danger">Hapus</button>
      </form>
    </article>
  <?php endforeach; ?>
</div>

<?php if (!$myItems): ?>
  <p class="muted">Tambahkan pakaian dulu di <a href="item_form.php">halaman tambah pakaian</a>.</p>
<?php else: ?>
<form class="panel" method="post">
  <h2>Buat outfit baru</h2>
  <?php foreach ($errors as $err): ?><p class="error"><?= e($err) ?></p><?php endforeach; ?>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="create">

  <label>Nama outfit <input type="text" name="name" value="<?= e($name) ?>" required maxlength="100"></label>
  <fieldset class="checklist">
    <legend>Pilih pakaian</legend>
    <?php foreach ($myItems as $i): ?>
      <label class="check">
        <input type="checkbox" name="items[]" value="<?= (int) $i['id'] ?>" <?= in_array((int) $i['id'], $selected, true) ? 'checked' : '' ?>>
        <?= e($i['name']) ?>
      </label>
    <?php endforeach; ?>
  </fieldset>
  <label>Catatan (opsional) <textarea name="notes" rows="2" maxlength="500"><?= e($notes) ?></textarea></label>
  <div class="form-actions">
    <button type="submit">Simpan outfit</button>
  </div>
</form>
<?php endif; ?>
<?php render_footer();
