<?php
declare(strict_types=1);

/**
 * Tandai menu yang sedang dibuka. Halaman edit pakaian dihitung
 * sebagai bagian dari "Lemari", bukan "+ Pakaian".
 */
function nav_attrs(string $page): string
{
    $current = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $editing = $current === 'item_form.php' && (int) ($_GET['id'] ?? $_POST['id'] ?? 0) > 0;

    $active = match ($page) {
        'index.php' => $current === 'index.php' || $editing,
        'item_form.php' => $current === 'item_form.php' && !$editing,
        default => $current === $page,
    };

    return $active ? ' class="active" aria-current="page"' : '';
}

function render_header(string $title): void
{
    $loggedIn = current_user_id() !== null;
    $flash = flash_get();
    ?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · Lemariku</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <script>document.documentElement.className += ' js';</script>
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php">Lemariku</a>
  <nav aria-label="Menu utama">
    <?php if ($loggedIn): ?>
      <a<?= nav_attrs('index.php') ?> href="index.php">Lemari</a>
      <a<?= nav_attrs('item_form.php') ?> href="item_form.php">Tambah</a>
      <a<?= nav_attrs('riwayat.php') ?> href="riwayat.php">Riwayat</a>
      <a<?= nav_attrs('masukan.php') ?> href="masukan.php">Masukan</a>
      <a href="logout.php">Keluar</a>
    <?php else: ?>
      <a<?= nav_attrs('login.php') ?> href="login.php">Masuk</a>
      <a<?= nav_attrs('register.php') ?> href="register.php">Daftar</a>
      <a<?= nav_attrs('masukan.php') ?> href="masukan.php">Masukan</a>
    <?php endif; ?>
  </nav>
</header>
<main class="container">
  <?php if ($flash !== null): ?>
    <div class="flash"><?= e($flash) ?></div>
  <?php endif; ?>
    <?php
}

function render_footer(): void
{
    ?>
</main>
<footer class="sitefoot">
  <p>Dibuat oleh <a href="<?= e(AUTHOR_URL) ?>" target="_blank" rel="noopener noreferrer"><?= e(AUTHOR_NAME) ?></a></p>
</footer>
<script src="assets/app.js"></script>
</body>
</html>
    <?php
}

/**
 * Tampilkan satu kartu pakaian.
 *
 * @param array<string, mixed> $it
 */
function render_item_card(array $it): void
{
    $detail = array_filter([$it['brand'] ?? null, $it['color'] ?? null]);
    ?>
    <article class="card rv">
      <div class="card-media">
        <?php if ($it['image']): ?>
          <img src="uploads/<?= e($it['image']) ?>" alt="" loading="lazy">
        <?php else: ?>
          <div class="noimg">Tanpa foto</div>
        <?php endif; ?>

        <div class="card-wear">
          <form method="post" action="wear.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
            <input type="hidden" name="action" value="wear">
            <button type="submit">Pakai hari ini</button>
          </form>
        </div>
      </div>

      <div class="card-body">
        <h3><?= e($it['name']) ?></h3>
        <?php if ($detail): ?>
          <p><?= e(implode(' · ', $detail)) ?></p>
        <?php endif; ?>
        <p>Dipakai <span class="count"><?= (int) $it['wear_count'] ?>x</span><?= $it['last_worn'] ? ' · terakhir ' . e($it['last_worn']) : '' ?></p>
        <?php if ($it['price_f'] !== null): ?>
          <p><?= e(rupiah($it['price_f'])) ?> · per pakai <?= e(rupiah($it['cpw'])) ?></p>
        <?php endif; ?>

        <?php if ($it['unused'] || $it['is_laundry']): ?>
          <div class="badges">
            <?php if ($it['unused']): ?><span class="badge">Jarang dipakai</span><?php endif; ?>
            <?php if ($it['is_laundry']): ?><span class="badge badge-wash">Perlu dicuci</span><?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="actions">
          <form method="post" action="wear.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
            <input type="hidden" name="action" value="laundry">
            <button type="submit" class="link"><?= $it['is_laundry'] ? 'Sudah dicuci' : 'Perlu dicuci' ?></button>
          </form>
          <a href="item_form.php?id=<?= (int) $it['id'] ?>">Edit</a>
          <form method="post" action="item_delete.php" onsubmit="return confirm('Hapus pakaian ini?')">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
            <button type="submit" class="link danger">Hapus</button>
          </form>
        </div>
      </div>
    </article>
    <?php
}
