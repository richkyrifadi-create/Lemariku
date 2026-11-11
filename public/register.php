<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

if (current_user_id() !== null) {
    redirect('index.php');
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Nama wajib diisi (maks 100 karakter).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $errors[] = 'Format email tidak valid.';
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'Kata sandi minimal 8 karakter.';
    }

    if (!$errors) {
        try {
            $stmt = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) db()->lastInsertId();
            $_SESSION['user_name'] = $name;
            flash_set('Akun berhasil dibuat. Selamat datang!');
            redirect('index.php');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'Email sudah terdaftar.';
            } else {
                throw $e;
            }
        }
    }
}

render_header('Daftar');
?>
<form class="panel narrow" method="post">
  <h1>Daftar</h1>
  <?php foreach ($errors as $err): ?><p class="error"><?= e($err) ?></p><?php endforeach; ?>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <label>Nama <input type="text" name="name" value="<?= e($name) ?>" required maxlength="100"></label>
  <label>Email <input type="email" name="email" value="<?= e($email) ?>" required></label>
  <label>Kata sandi (min. 8 karakter) <input type="password" name="password" required minlength="8"></label>
  <button type="submit">Buat akun</button>
  <p class="muted">Sudah punya akun? <a href="login.php">Masuk</a></p>
</form>
<?php render_footer();
