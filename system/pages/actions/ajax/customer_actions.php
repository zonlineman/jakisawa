<?php
/**
 * Customer Actions AJAX Handler
 * Location: /ajax/customer_actions.php
 */

// Set JSON header FIRST
header('Content-Type: application/json; charset=utf-8');

// Disable display errors in production
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle CORS if needed
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get base path - adjust based on your actual structure
$base_path = dirname(dirname(dirname(__DIR__))); // Goes up 3 levels from /ajax/

// Include required files
$database_file = $base_path . '/includes/database.php';
$customer_actions_file = $base_path . '/pages/actions/CustomerActions.php';

// Check if files exist
if (!file_exists($database_file)) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database configuration not found',
        'debug' => ['path' => $database_file]
    ]);
    exit;
}

if (!file_exists($customer_actions_file)) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'CustomerActions class not found',
        'debug' => ['path' => $customer_actions_file]
    ]);
    exit;
}

// Include files
try {
    require_once $database_file;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error loading database configuration: ' . $e->getMessage()]);
    exit;
}

try {
    require_once $customer_actions_file;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error loading CustomerActions: ' . $e->getMessage()]);
    exit;
}

// Check database connection
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    exit;
}

// Admin-only authentication guard
$adminRole = strtolower((string) ($_SESSION['admin_role'] ?? $_SESSION['role'] ?? ''));
$isAdmin = in_array($adminRole, ['admin', 'super_admin'], true);
$hasAdminSession = !empty($_SESSION['admin_id']) || (!empty($_SESSION['user_id']) && $isAdmin);

if (!$hasAdminSession || !$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

// Initialize CustomerActions
try {
    $customerActions = new CustomerActions($pdo);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to initialize CustomerActions: ' . $e->getMessage()]);
    exit;
}

// Route actions
$response = null;

try {
    switch ($action) {
        case 'approve':
            $customerId = intval($_POST['customer_id'] ?? 0);
            if ($customerId > 0) {
                $response = $customerActions->approveCustomer($customerId);
            } else {
                $response = ['success' => false, 'message' => 'Invalid customer ID'];
            }
            break;
            
        case 'reject':
            $customerId = intval($_POST['customer_id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            if ($customerId > 0 && !empty($reason)) {
                $response = $customerActions->rejectCustomer($customerId, $reason);
            } else {
                $response = ['success' => false, 'message' => 'Invalid input'];
            }
            break;
            
        case 'activate':
            $customerId = intval($_POST['customer_id'] ?? 0);
            if ($customerId > 0) {
                $response = $customerActions->activateCustomer($customerId);
            } else {
                $response = ['success' => false, 'message' => 'Invalid customer ID'];
            }
            break;
            
        case 'deactivate':
            $customerId = intval($_POST['customer_id'] ?? 0);
            if ($customerId > 0) {
                $response = $customerActions->deactivateCustomer($customerId);
            } else {
                $response = ['success' => false, 'message' => 'Invalid customer ID'];
            }
            break;
            
        case 'delete':
            $customerId = intval($_POST['customer_id'] ?? 0);
            if ($customerId > 0) {
                $response = $customerActions->deleteCustomer($customerId);
            } else {
                $response = ['success' => false, 'message' => 'Invalid customer ID'];
            }
            break;
            
        case 'convert':
            $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($name)) {
                $response = $customerActions->convertToRegistered($email, $name, $phone);
            } else {
                $response = ['success' => false, 'message' => 'Invalid input. Please provide valid email and name.'];
            }
            break;
            
        case 'send_email':
            $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $subject = trim($_POST['subject'] ?? '');
            $message = trim($_POST['message'] ?? '');
            
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($subject) && !empty($message)) {
                $response = $customerActions->sendEmail($email, $subject, $message);
            } else {
                $response = ['success' => false, 'message' => 'Invalid input'];
            }
            break;

        case 'send_bulk_email':
            $emails = isset($_POST['emails']) ? (array)$_POST['emails'] : [];
            $subject = trim($_POST['subject'] ?? '');
            $message = trim($_POST['message'] ?? '');

            $emails = array_values(array_unique(array_filter(array_map(function ($e) {
                $sanitized = filter_var((string)$e, FILTER_SANITIZE_EMAIL);
                return filter_var($sanitized, FILTER_VALIDATE_EMAIL) ? $sanitized : null;
            }, $emails))));

            if (empty($emails) || $subject === '' || $message === '') {
                $response = ['success' => false, 'message' => 'Invalid bulk email input'];
                break;
            }

            $sent = 0;
            $failed = 0;
            foreach ($emails as $email) {
                $result = $customerActions->sendEmail($email, $subject, $message);
                if (!empty($result['success'])) {
                    $sent++;
                } else {
                    $failed++;
                }
            }

            $response = [
                'success' => $sent > 0,
                'message' => "Bulk email completed. Sent: {$sent}, Failed: {$failed}"
            ];
            break;

        case 'send_sms':
            $phone = trim((string)($_POST['phone'] ?? ''));
            $message = trim((string)($_POST['message'] ?? ''));

            if ($phone === '' || $message === '') {
                $response = ['success' => false, 'message' => 'Phone and message are required'];
            } else {
                $response = $customerActions->sendSMS($phone, $message);
            }
            break;

        case 'send_bulk_sms':
            $phones = isset($_POST['phones']) ? (array)$_POST['phones'] : [];
            $message = trim((string)($_POST['message'] ?? ''));

            if (empty($phones) || $message === '') {
                $response = ['success' => false, 'message' => 'Phones and message are required'];
                break;
            }

            $sent = 0;
            $failed = 0;
            foreach ($phones as $phone) {
                $result = $customerActions->sendSMS((string)$phone, $message);
                if (!empty($result['success'])) {
                    $sent++;
                } else {
                    $failed++;
                }
            }

            $response = [
                'success' => $sent > 0,
                'message' => "Bulk SMS completed. Sent: {$sent}, Failed: {$failed}"
            ];
            break;

        case 'health_check':
            $response = [
                'success' => true,
                'data' => $customerActions->getCommunicationHealth()
            ];
            break;
            
     case 'get_customer':
    $id = urldecode(trim($_GET['id'] ?? ''));
    $type = $_GET['type'] ?? 'registered';
    
    if (!empty($id)) {
        $customer = $customerActions->getCustomerDetails($id, $type);
        if ($customer) {
            echo json_encode([
                'success' => true, 
                'data' => $customer,
                'type' => $type
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Customer not found'
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    }
    break;
            
        case 'get_orders':
            $customerId = intval($_GET['customer_id'] ?? 0);
            if ($customerId > 0) {
                $orders = $customerActions->getCustomerOrders($customerId);
                $response = ['success' => true, 'data' => $orders];
            } else {
                $response = ['success' => false, 'message' => 'Invalid customer ID'];
            }
            break;
            
        case 'search':
            $query = trim($_GET['q'] ?? '');
            $type = $_GET['type'] ?? 'registered';
            $limit = intval($_GET['limit'] ?? 10);
            
            if (strlen($query) >= 2) {
                $results = $customerActions->search($query, $type, $limit);
                $response = ['success' => true, 'data' => $results];
            } else {
                $response = ['success' => false, 'message' => 'Query must be at least 2 characters'];
            }
            break;
            
        case 'bulk_action':
            $ids = isset($_POST['ids']) ? array_filter(array_map('intval', (array)$_POST['ids'])) : [];
            $operation = trim($_POST['operation'] ?? '');
            
            if (!empty($ids) && !empty($operation)) {
                $successCount = 0;
                $allowedOperations = ['approve', 'activate', 'deactivate', 'delete'];
                
                if (in_array($operation, $allowedOperations)) {
                    foreach ($ids as $id) {
                        if ($id > 0) {
                            switch ($operation) {
                                case 'approve':
                                    $result = $customerActions->approveCustomer($id);
                                    break;
                                case 'activate':
                                    $result = $customerActions->activateCustomer($id);
                                    break;
                                case 'deactivate':
                                    $result = $customerActions->deactivateCustomer($id);
                                    break;
                                case 'delete':
                                    $result = $customerActions->deleteCustomer($id);
                                    break;
                                default:
                                    $result = ['success' => false];
                            }
                            
                            if ($result['success']) {
                                $successCount++;
                            }
                        }
                    }
                    
                    $response = [
                        'success' => $successCount > 0,
                        'message' => "Successfully processed $successCount of " . count($ids) . " customers"
                    ];
                } else {
                    $response = ['success' => false, 'message' => 'Invalid operation'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Invalid input'];
            }
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Unknown action'];
            break;
    }
} catch (Exception $e) {
    error_log("CustomerActions AJAX Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => 'Server error occurred',
        'debug' => (ini_get('display_errors') ? [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ] : null)
    ];
}

// Send response
if ($response === null) {
    $response = ['success' => false, 'message' => 'No response generated'];
}

echo json_encode($response);
exit;
