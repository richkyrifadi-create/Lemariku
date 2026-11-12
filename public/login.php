<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

if (current_user_id() !== null) {
    redirect('index.php');
}

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT id, name, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];
        redirect('index.php');
    }
    $error = 'Email atau kata sandi salah.';
}

render_header('Masuk');
?>
<form class="panel narrow rv" method="post">
  <h1>Masuk</h1>
  <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <label>Email <input type="email" name="email" value="<?= e($email) ?>" required></label>
  <label>Kata sandi <input type="password" name="password" required></label>
  <button type="submit">Masuk</button>
  <p class="muted">Belum punya akun? <a href="register.php">Daftar</a></p>
</form>
<?php render_footer();
