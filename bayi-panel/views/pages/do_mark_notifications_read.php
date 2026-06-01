<?php // views/pages/do_mark_notifications_read.php
use Auth\Auth;
use Helpers\Database;

Auth::require();
verify_csrf();
header('Content-Type: application/json; charset=utf-8');

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
$id   = (int)($data['id'] ?? 0);

if ($id) {
    Database::query(
        "UPDATE notifications SET is_read = 1 WHERE id = ? AND dealer_id = ?",
        [$id, Auth::id()]
    );
}

echo json_encode(['success' => true]);
