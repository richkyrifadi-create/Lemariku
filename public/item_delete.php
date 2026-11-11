<?php
require __DIR__ . '/../src/bootstrap.php';

$userId = require_login();
csrf_check();

$id = (int) ($_POST['id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare('SELECT image FROM items WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);
$item = $stmt->fetch();

if ($item) {
    $pdo->prepare('DELETE FROM items WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
    delete_image($item['image']);
    flash_set('Pakaian dihapus.');
}

redirect('index.php');
