<?php
// ajax_get_remedy.php - dedicated AJAX endpoint
session_start();
require_once '../includes/database.php';

if (!isset($_GET['get_remedy']) || !is_numeric($_GET['get_remedy'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$remedy_id = intval($_GET['get_remedy']);
$conn = getDBConnection();

if (!$conn) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$query = "SELECT 
    r.*, 
    c.name as category_name,
    s.name as supplier_name
    FROM remedies r
    LEFT JOIN categories c ON r.category_id = c.id
    LEFT JOIN suppliers s ON r.supplier_id = s.id
    WHERE r.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $remedy_id);
$stmt->execute();
$result = $stmt->get_result();

header('Content-Type: application/json');

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'remedy' => $row
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Remedy not found'
    ]);
}

$stmt->close();
$conn->close();
exit();
?>