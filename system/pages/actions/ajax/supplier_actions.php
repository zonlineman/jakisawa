<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

ob_start();

$base = dirname(dirname(dirname(__DIR__)));
require_once $base . '/includes/database.php';
require_once $base . '/pages/actions/CustomerActions.php';

function responseJson(array $arr, int $status = 200): void
{
    $buffer = ob_get_clean();
    if ($buffer && trim($buffer) !== '') {
        error_log('supplier_actions buffered output: ' . trim($buffer));
    }
    http_response_code($status);
    echo json_encode($arr);
    exit;
}

try {
    $adminRole = strtolower((string) ($_SESSION['admin_role'] ?? $_SESSION['role'] ?? ''));
    $isAdmin = in_array($adminRole, ['admin', 'super_admin'], true);
    $hasSession = !empty($_SESSION['admin_id']) || !empty($_SESSION['user_id']);
    if (!$hasSession) {
        responseJson(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    if ($action === '') {
        responseJson(['success' => false, 'message' => 'No action specified'], 400);
    }

    $conn = getDBConnection();
    if (!$conn) {
        responseJson(['success' => false, 'message' => 'Database connection failed'], 500);
    }

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    $customerActions = new CustomerActions($pdo);
} catch (Throwable $e) {
    error_log('supplier_actions bootstrap error: ' . $e->getMessage());
    responseJson(['success' => false, 'message' => 'Server bootstrap failed'], 500);
}

try {
    switch ($action) {
        case 'activate': {
            $id = (int)($_POST['supplier_id'] ?? 0);
            if ($id <= 0) {
                responseJson(['success' => false, 'message' => 'Invalid supplier ID']);
            }
            $stmt = $conn->prepare('UPDATE suppliers SET is_active = 1 WHERE id = ?');
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();
            $stmt->close();
            responseJson(['success' => $ok, 'message' => $ok ? 'Supplier activated successfully' : 'Failed to activate supplier']);
        }

        case 'deactivate': {
            $id = (int)($_POST['supplier_id'] ?? 0);
            if ($id <= 0) {
                responseJson(['success' => false, 'message' => 'Invalid supplier ID']);
            }
            $stmt = $conn->prepare('UPDATE suppliers SET is_active = 0 WHERE id = ?');
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();
            $stmt->close();
            responseJson(['success' => $ok, 'message' => $ok ? 'Supplier deactivated successfully' : 'Failed to deactivate supplier']);
        }

        case 'delete': {
            if (!$isAdmin) {
                responseJson(['success' => false, 'message' => 'Only admins can delete suppliers']);
            }
            $id = (int)($_POST['supplier_id'] ?? 0);
            if ($id <= 0) {
                responseJson(['success' => false, 'message' => 'Invalid supplier ID']);
            }

            $check = $conn->prepare('SELECT COUNT(*) c FROM remedies WHERE supplier_id = ?');
            $check->bind_param('i', $id);
            $check->execute();
            $res = $check->get_result();
            $count = (int)(($res && ($r = $res->fetch_assoc())) ? $r['c'] : 0);
            $check->close();

            if ($count > 0) {
                responseJson(['success' => false, 'message' => 'Cannot delete supplier with assigned products']);
            }

            $stmt = $conn->prepare('DELETE FROM suppliers WHERE id = ?');
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();
            $stmt->close();

            responseJson(['success' => $ok, 'message' => $ok ? 'Supplier deleted successfully' : 'Failed to delete supplier']);
        }

        case 'bulk_action': {
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids)) {
                $ids = [$ids];
            }
            $ids = array_values(array_filter(array_map('intval', $ids), static fn($x) => $x > 0));
            $op = trim((string)($_POST['operation'] ?? ''));
            if (!$ids || $op === '') {
                responseJson(['success' => false, 'message' => 'Invalid bulk action input']);
            }

            $okCount = 0;
            foreach ($ids as $id) {
                if ($op === 'activate') {
                    $stmt = $conn->prepare('UPDATE suppliers SET is_active = 1 WHERE id = ?');
                    $stmt->bind_param('i', $id);
                    $ok = $stmt->execute();
                    $stmt->close();
                    if ($ok) {
                        $okCount++;
                    }
                } elseif ($op === 'deactivate') {
                    $stmt = $conn->prepare('UPDATE suppliers SET is_active = 0 WHERE id = ?');
                    $stmt->bind_param('i', $id);
                    $ok = $stmt->execute();
                    $stmt->close();
                    if ($ok) {
                        $okCount++;
                    }
                } elseif ($op === 'delete') {
                    if (!$isAdmin) {
                        continue;
                    }

                    $check = $conn->prepare('SELECT COUNT(*) c FROM remedies WHERE supplier_id = ?');
                    $check->bind_param('i', $id);
                    $check->execute();
                    $res = $check->get_result();
                    $count = (int)(($res && ($r = $res->fetch_assoc())) ? $r['c'] : 0);
                    $check->close();
                    if ($count > 0) {
                        continue;
                    }

                    $stmt = $conn->prepare('DELETE FROM suppliers WHERE id = ?');
                    $stmt->bind_param('i', $id);
                    $ok = $stmt->execute();
                    $stmt->close();
                    if ($ok) {
                        $okCount++;
                    }
                }
            }

            responseJson([
                'success' => $okCount > 0,
                'message' => "Bulk action completed. Success: {$okCount}/" . count($ids)
            ]);
        }

        case 'send_email': {
            $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $subject = trim((string)($_POST['subject'] ?? ''));
            $message = trim((string)($_POST['message'] ?? ''));
            if (!$email || !$subject || !$message) {
                responseJson(['success' => false, 'message' => 'Invalid email input']);
            }
            responseJson($customerActions->sendEmail($email, $subject, $message));
        }

        case 'send_bulk_email': {
            $emails = $_POST['emails'] ?? [];
            if (!is_array($emails)) {
                $emails = [$emails];
            }
            $emails = array_values(array_unique(array_filter(array_map(static function ($e) {
                $x = filter_var((string)$e, FILTER_SANITIZE_EMAIL);
                return filter_var($x, FILTER_VALIDATE_EMAIL) ? $x : null;
            }, $emails))));
            $subject = trim((string)($_POST['subject'] ?? ''));
            $message = trim((string)($_POST['message'] ?? ''));
            if (!$emails || !$subject || !$message) {
                responseJson(['success' => false, 'message' => 'Invalid bulk email input']);
            }

            $sent = 0;
            $failed = 0;
            foreach ($emails as $em) {
                $r = $customerActions->sendEmail($em, $subject, $message);
                if (!empty($r['success'])) {
                    $sent++;
                } else {
                    $failed++;
                }
            }
            responseJson(['success' => $sent > 0, 'message' => "Bulk email completed. Sent: {$sent}, Failed: {$failed}"]);
        }

        case 'send_sms': {
            $phone = trim((string)($_POST['phone'] ?? ''));
            $message = trim((string)($_POST['message'] ?? ''));
            if ($phone === '' || $message === '') {
                responseJson(['success' => false, 'message' => 'Phone and message are required']);
            }
            responseJson($customerActions->sendSMS($phone, $message));
        }

        case 'send_bulk_sms': {
            $phones = $_POST['phones'] ?? [];
            if (!is_array($phones)) {
                $phones = [$phones];
            }
            $message = trim((string)($_POST['message'] ?? ''));
            if (!$phones || $message === '') {
                responseJson(['success' => false, 'message' => 'Phones and message are required']);
            }

            $sent = 0;
            $failed = 0;
            foreach ($phones as $ph) {
                $r = $customerActions->sendSMS((string)$ph, $message);
                if (!empty($r['success'])) {
                    $sent++;
                } else {
                    $failed++;
                }
            }
            responseJson(['success' => $sent > 0, 'message' => "Bulk SMS completed. Sent: {$sent}, Failed: {$failed}"]);
        }

        case 'health_check': {
            responseJson(['success' => true, 'data' => $customerActions->getCommunicationHealth()]);
        }

        default:
            responseJson(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Throwable $e) {
    error_log('supplier_actions AJAX error: ' . $e->getMessage());
    responseJson(['success' => false, 'message' => 'Server error occurred']);
}
