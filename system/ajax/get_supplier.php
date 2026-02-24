
<?php
session_start();
require_once '../includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = intval($_GET['id']);
$conn = getDBConnection();

$query = "SELECT * FROM suppliers WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$supplier = $result->fetch_assoc();

if (!$supplier) {
    echo json_encode(['error' => 'Supplier not found']);
    exit;
}

echo json_encode($supplier);
$stmt->close();
$conn->close();
?>
