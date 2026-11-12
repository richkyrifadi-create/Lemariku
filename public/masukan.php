<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

$pdo = db();

$form = ['category' => '', 'name' => '', 'email' => '', 'subject' => '', 'message' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Honeypot: field ini disembunyikan dari pengguna, jadi hanya bot yang mengisinya.
    // Dijawab sukses supaya bot tidak tahu kirimannya dibuang.
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        flash_set('Terima kasih, masukanmu sudah dikirim.');
        redirect('masukan.php');
    }

    foreach (array_keys($form) as $field) {
        $form[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    if (!in_array($form['category'], FEEDBACK_CATEGORIES, true)) {
        $errors[] = 'Pilih kategori yang tersedia.';
    }
    if ($form['name'] !== '' && mb_strlen($form['name']) > 100) {
        $errors[] = 'Nama maksimal 100 karakter.';
    }
    if ($form['email'] !== '') {
        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 150) {
            $errors[] = 'Format email tidak valid.';
        }
    }

    $subjectLen = mb_strlen($form['subject']);
    if ($subjectLen === 0) {
        $errors[] = 'Judul wajib diisi.';
    } elseif ($subjectLen > FEEDBACK_SUBJECT_MAX) {
        $errors[] = 'Judul maksimal ' . FEEDBACK_SUBJECT_MAX . ' karakter.';
    }

    $messageLen = mb_strlen($form['message']);
    if ($messageLen < FEEDBACK_MESSAGE_MIN) {
        $errors[] = 'Deskripsi minimal ' . FEEDBACK_MESSAGE_MIN . ' karakter.';
    } elseif ($messageLen > FEEDBACK_MESSAGE_MAX) {
        $errors[] = 'Deskripsi maksimal ' . FEEDBACK_MESSAGE_MAX . ' karakter.';
    }

    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        [$image, $uploadError] = save_upload(
            $_FILES['image'],
            FEEDBACK_IMAGE_MAX_BYTES,
            ['image/jpeg' => 'jpg', 'image/png' => 'png']
        );
        if ($uploadError !== null) {
            $errors[] = $uploadError;
        }
    }

    $ip = client_ip();
    if (!$errors && feedback_rate_exceeded($pdo, $ip)) {
        $errors[] = 'Kamu sudah mengirim ' . FEEDBACK_RATE_LIMIT . ' masukan dalam '
            . FEEDBACK_RATE_WINDOW_MINUTES . ' menit terakhir. Coba lagi nanti.';
        // Foto yang telanjur tersimpan dibuang supaya tidak jadi sampah.
        delete_image($image);
        $image = null;
    }

    if (!$errors) {
        $pdo->prepare(
            'INSERT INTO feedback (category, name, email, subject, message, image, ip, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $form['category'],
            $form['name'] !== '' ? $form['name'] : null,
            $form['email'] !== '' ? $form['email'] : null,
            $form['subject'],
            $form['message'],
            $image,
            $ip !== '' ? $ip : null,
            date('Y-m-d H:i:s'),
        ]);
        // Redirect setelah sukses supaya form kosong lagi dan tidak terkirim dua kali saat refresh.
        flash_set('Terima kasih, masukanmu sudah dikirim.');
        redirect('masukan.php');
    }
}

render_header('Masukan');
?>
<form class="panel feedback rv" method="post" enctype="multipart/form-data">
  <h1>Masukan &amp; Pengaduan</h1>
  <p class="muted center">Ada saran, menemukan error, atau ada yang tidak berfungsi? Tulis di sini.</p>

  <?php if ($errors): ?>
    <div role="alert">
      <?php foreach ($errors as $err): ?><p class="error"><?= e($err) ?></p><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

  <!-- Honeypot: disembunyikan lewat CSS dan dari pembaca layar. Biarkan kosong. -->
  <div class="hp" aria-hidden="true">
    <label for="website">Jangan diisi</label>
    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
  </div>

  <label for="category">Kategori</label>
  <select id="category" name="category" required>
    <option value="">Pilih...</option>
    <?php foreach (FEEDBACK_CATEGORIES as $c): ?>
      <option value="<?= e($c) ?>" <?= $form['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
    <?php endforeach; ?>
  </select>

  <div class="row">
    <div>
      <label for="name">Nama <span class="muted">(opsional)</span></label>
      <input type="text" id="name" name="name" value="<?= e($form['name']) ?>" maxlength="100" autocomplete="name">
    </div>
    <div>
      <label for="email">Email <span class="muted">(opsional)</span></label>
      <input type="email" id="email" name="email" value="<?= e($form['email']) ?>" maxlength="150" autocomplete="email">
    </div>
  </div>

  <label for="subject">Judul singkat</label>
  <input type="text" id="subject" name="subject" value="<?= e($form['subject']) ?>"
         required maxlength="<?= FEEDBACK_SUBJECT_MAX ?>">

  <label for="message">Deskripsi</label>
  <textarea id="message" name="message" rows="6" required
            minlength="<?= FEEDBACK_MESSAGE_MIN ?>" maxlength="<?= FEEDBACK_MESSAGE_MAX ?>"
            aria-describedby="message-count"><?= e($form['message']) ?></textarea>
  <p class="counter" id="message-count" aria-live="polite">
    <span id="count-now">0</span> / <?= FEEDBACK_MESSAGE_MAX ?> karakter
    (minimal <?= FEEDBACK_MESSAGE_MIN ?>)
  </p>

  <label for="image">Lampiran foto <span class="muted">(opsional, JPG atau PNG, maks 2 MB)</span></label>
  <input type="file" id="image" name="image" accept="image/jpeg,image/png">

  <div class="form-actions">
    <button type="submit">Kirim masukan</button>
  </div>
</form>

<script>
(function () {
  var box = document.getElementById('message');
  var now = document.getElementById('count-now');
  var min = <?= FEEDBACK_MESSAGE_MIN ?>;
  if (!box || !now) return;

  function update() {
    var len = box.value.length;
    now.textContent = len;
    now.className = (len > 0 && len < min) ? 'count-short' : '';
  }
  box.addEventListener('input', update);
  update();
})();
</script>
<?php render_footer();
