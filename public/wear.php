<?php
require __DIR__ . '/../src/bootstrap.php';

$userId = require_login();
csrf_check();

$pdo = db();
$itemId = (int) ($_POST['item_id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');

$stmt = $pdo->prepare('SELECT id, is_laundry FROM items WHERE id = ? AND user_id = ?');
$stmt->execute([$itemId, $userId]);
$item = $stmt->fetch();

if ($item) {
    if ($action === 'wear') {
        $today = date('Y-m-d');
        $check = $pdo->prepare('SELECT 1 FROM wear_logs WHERE item_id = ? AND worn_on = ?');
        $check->execute([$itemId, $today]);
        if (!$check->fetch()) {
            $pdo->prepare('INSERT INTO wear_logs (item_id, worn_on) VALUES (?, ?)')->execute([$itemId, $today]);
            // Pakaian yang dipakai otomatis dianggap perlu dicuci.
            $pdo->prepare('UPDATE items SET is_laundry = 1 WHERE id = ?')->execute([$itemId]);
            flash_set('Dicatat dipakai hari ini.');
        } else {
            flash_set('Pakaian ini sudah dicatat dipakai hari ini.');
        }
    } elseif ($action === 'laundry') {
        $pdo->prepare('UPDATE items SET is_laundry = ? WHERE id = ?')
            ->execute([(int) !$item['is_laundry'], $itemId]);
    }
}

redirect('index.php');
