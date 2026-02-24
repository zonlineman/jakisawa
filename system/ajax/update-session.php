<?php
// admin/ajax/update-session.php
session_start();

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $_SESSION['last_activity'] = time();
    echo json_encode(['status' => 'success', 'time' => date('H:i:s')]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
}
?>