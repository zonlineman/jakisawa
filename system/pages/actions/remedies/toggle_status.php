<?php
// toggle_status.php - Handle status toggles (featured/active)
session_start();
require_once __DIR__ . '/../../../includes/database.php';

$systemBase = (defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system');
$redirectRemedies = $systemBase . '/admin_dashboard.php?page=remedies';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectRemedies);
    exit();
}

$remedy_id = intval($_POST['remedy_id'] ?? 0);
$action = $_POST['action_type'] ?? '';
$new_value = intval($_POST['new_value'] ?? 0);
$role = strtolower((string)($_SESSION['admin_role'] ?? $_SESSION['role'] ?? ''));
$isAdmin = in_array($role, ['admin', 'super_admin'], true);

if ($remedy_id <= 0 || !in_array($action, ['featured', 'active'])) {
    $_SESSION['errors'][] = 'Invalid request';
    header('Location: ' . $redirectRemedies);
    exit();
}

if ($action === 'active' && !$isAdmin) {
    $_SESSION['errors'][] = 'Only admin can activate/deactivate remedies';
    header('Location: ' . $redirectRemedies);
    exit();
}

$conn = getDBConnection();

$column = $action === 'featured' ? 'is_featured' : 'is_active';
$query = "UPDATE remedies SET $column = ?, updated_at = NOW() WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $new_value, $remedy_id);

if ($stmt->execute()) {
    $_SESSION['success'] = 'Status updated successfully';
} else {
    $_SESSION['errors'][] = 'Failed to update status';
}

$stmt->close();
header('Location: ' . $redirectRemedies);
exit();
?>
