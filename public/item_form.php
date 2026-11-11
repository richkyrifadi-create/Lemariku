<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

$userId = require_login();
$pdo = db();

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$categoryIds = array_map(fn($c) => (int) $c['id'], $categories);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$item = [
    'id' => 0, 'name' => '', 'brand' => '', 'color' => '', 'season' => SEASONS[0],
    'price' => '', 'purchased_at' => '', 'category_id' => 0, 'image' => null,
];

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM items WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    $found = $stmt->fetch();
    if (!$found) {
        http_response_code(404);
        exit('Pakaian tidak ditemukan.');
    }
    $item = $found;
    $item['price'] = $item['price'] !== null ? (string) (float) $item['price'] : '';
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $item['name'] = trim((string) ($_POST['name'] ?? ''));
    $item['brand'] = trim((string) ($_POST['brand'] ?? ''));
    $item['color'] = trim((string) ($_POST['color'] ?? ''));
    $item['season'] = (string) ($_POST['season'] ?? '');
    $item['price'] = trim((string) ($_POST['price'] ?? ''));
    $item['purchased_at'] = trim((string) ($_POST['purchased_at'] ?? ''));
    $item['category_id'] = (int) ($_POST['category_id'] ?? 0);

    $name = $item['name'];
    if ($name === '' || mb_strlen($name) > 120) {
        $errors[] = 'Nama pakaian wajib diisi (maks 120 karakter).';
    }
    if (!in_array($item['category_id'], $categoryIds, true)) {
        $errors[] = 'Pilih kategori yang valid.';
    }
    if (!in_array($item['season'], SEASONS, true)) {
        $errors[] = 'Pilih musim yang valid.';
    }

    $price = null;
    if ($item['price'] !== '') {
        if (!is_numeric($item['price']) || (float) $item['price'] < 0) {
            $errors[] = 'Harga harus angka dan tidak negatif.';
        } else {
            $price = round((float) $item['price'], 2);
        }
    }

    $purchasedAt = null;
    if ($item['purchased_at'] !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $item['purchased_at']);
        if (!$date || $date->format('Y-m-d') !== $item['purchased_at']) {
            $errors[] = 'Format tanggal beli tidak valid.';
        } else {
            $purchasedAt = $item['purchased_at'];
        }
    }

    $newImage = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        [$newImage, $uploadError] = save_upload($_FILES['image']);
        if ($uploadError !== null) {
            $errors[] = $uploadError;
        }
    }

    if (!$errors) {
        if ($id > 0) {
            $image = $newImage ?? $item['image'];
            $pdo->prepare(
                'UPDATE items SET name = ?, brand = ?, color = ?, season = ?, price = ?,
                                  purchased_at = ?, category_id = ?, image = ?
                 WHERE id = ? AND user_id = ?'
            )->execute([
                $name, $item['brand'] ?: null, $item['color'] ?: null, $item['season'], $price,
                $purchasedAt, $item['category_id'], $image, $id, $userId,
            ]);
            if ($newImage !== null && $item['image'] !== null) {
                delete_image($item['image']);
            }
            flash_set('Pakaian diperbarui.');
        } else {
            $pdo->prepare(
                'INSERT INTO items (user_id, name, brand, color, season, price, purchased_at, category_id, image)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $userId, $name, $item['brand'] ?: null, $item['color'] ?: null, $item['season'],
                $price, $purchasedAt, $item['category_id'], $newImage,
            ]);
            flash_set('Pakaian ditambahkan.');
        }
        redirect('index.php');
    }
}

render_header($id > 0 ? 'Edit pakaian' : 'Tambah pakaian');
?>
<form class="panel" method="post" enctype="multipart/form-data">
  <h1><?= $id > 0 ? 'Edit pakaian' : 'Tambah pakaian' ?></h1>
  <?php foreach ($errors as $err): ?><p class="error"><?= e($err) ?></p><?php endforeach; ?>

  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= $id ?>">

  <label>Nama pakaian
    <input type="text" name="name" value="<?= e($item['name']) ?>" required maxlength="120">
  </label>

  <div class="row">
    <label>Kategori
      <select name="category_id" required>
        <option value="">Pilih...</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) $item['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Musim
      <select name="season">
        <?php foreach (SEASONS as $s): ?>
          <option <?= $item['season'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>

  <div class="row">
    <label>Merek <input type="text" name="brand" value="<?= e($item['brand']) ?>" maxlength="80"></label>
    <label>Warna <input type="text" name="color" value="<?= e($item['color']) ?>" maxlength="40"></label>
  </div>

  <div class="row">
    <label>Harga (Rp) <input type="number" name="price" min="0" step="any" inputmode="decimal" value="<?= e($item['price']) ?>"></label>
    <label>Tanggal beli <input type="date" name="purchased_at" value="<?= e($item['purchased_at']) ?>"></label>
  </div>

  <label>Foto (JPG, PNG, atau WEBP, maks 3 MB)
    <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
  </label>
  <?php if ($item['image']): ?>
    <p class="muted">Foto saat ini:</p>
    <img class="preview" src="uploads/<?= e($item['image']) ?>" alt="">
  <?php endif; ?>

  <button type="submit">Simpan</button>
  <a class="ghost-link" href="index.php">Batal</a>
</form>
<?php render_footer();
