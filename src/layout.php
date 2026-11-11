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
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php">Lemariku</a>
  <nav>
    <?php if ($loggedIn): ?>
      <a<?= nav_attrs('index.php') ?> href="index.php">Lemari</a>
      <a<?= nav_attrs('item_form.php') ?> href="item_form.php">+ Pakaian</a>
      <a<?= nav_attrs('outfits.php') ?> href="outfits.php">Outfit</a>
      <a href="logout.php">Keluar</a>
    <?php else: ?>
      <a<?= nav_attrs('login.php') ?> href="login.php">Masuk</a>
      <a<?= nav_attrs('register.php') ?> href="register.php">Daftar</a>
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
    echo "</main>\n</body>\n</html>\n";
}

/**
 * Tampilkan satu kartu pakaian.
 *
 * @param array<string, mixed> $it
 */
function render_item_card(array $it): void
{
    ?>
    <article class="card">
      <?php if ($it['image']): ?>
        <img src="uploads/<?= e($it['image']) ?>" alt="">
      <?php else: ?>
        <div class="noimg">Tanpa foto</div>
      <?php endif; ?>

      <h3><?= e($it['name']) ?></h3>
      <p class="muted"><?= $it['brand'] ? e($it['brand']) : '' ?><?= $it['brand'] && $it['color'] ? ' · ' : '' ?><?= $it['color'] ? e($it['color']) : '' ?></p>
      <p>Dipakai <strong><?= (int) $it['wear_count'] ?>x</strong><?= $it['last_worn'] ? ' · terakhir ' . e($it['last_worn']) : '' ?></p>
      <?php if ($it['price_f'] !== null): ?>
        <p class="muted">Harga <?= e(rupiah($it['price_f'])) ?> · per pakai <?= e(rupiah($it['cpw'])) ?></p>
      <?php endif; ?>

      <?php if ($it['unused']): ?><span class="badge">Jarang dipakai</span><?php endif; ?>
      <?php if ($it['is_laundry']): ?><span class="badge badge-wash">Perlu dicuci</span><?php endif; ?>

      <div class="actions">
        <form method="post" action="wear.php">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
          <input type="hidden" name="action" value="wear">
          <button type="submit">Pakai hari ini</button>
        </form>
        <form method="post" action="wear.php">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
          <input type="hidden" name="action" value="laundry">
          <button type="submit" class="ghost"><?= $it['is_laundry'] ? 'Sudah dicuci' : 'Perlu dicuci' ?></button>
        </form>
        <a href="item_form.php?id=<?= (int) $it['id'] ?>">Edit</a>
        <form method="post" action="item_delete.php" onsubmit="return confirm('Hapus pakaian ini?')">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
          <button type="submit" class="danger">Hapus</button>
        </form>
      </div>
    </article>
    <?php
}
