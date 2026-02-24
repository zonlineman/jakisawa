<?php
/**
 * GET REMEDY API ENDPOINT
 * Returns single remedy details as JSON
 */

// Prevent any output before JSON
ob_start();

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

// Clear any previous output
ob_clean();

require_once __DIR__ . '/../../../includes/database.php';

try {
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        throw new Exception('Invalid remedy ID');
    }
    
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    $seoTableExists = false;
    $stockLedgerExists = false;
    $hasCustomSizes = false;
    $hasCustomSachets = false;
    $hasRemedyCustomSizes = false;
    $hasRemedyCustomSachets = false;

    $remedySizesCol = $conn->query("SHOW COLUMNS FROM remedies LIKE 'custom_sizes'");
    $remedySachetsCol = $conn->query("SHOW COLUMNS FROM remedies LIKE 'custom_sachets'");
    $hasRemedyCustomSizes = $remedySizesCol && $remedySizesCol->num_rows > 0;
    $hasRemedyCustomSachets = $remedySachetsCol && $remedySachetsCol->num_rows > 0;

    $tableCheck = $conn->query("SHOW TABLES LIKE 'remedy_seo_marketing'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $seoTableExists = true;
        $sizesCol = $conn->query("SHOW COLUMNS FROM remedy_seo_marketing LIKE 'custom_sizes'");
        $sachetsCol = $conn->query("SHOW COLUMNS FROM remedy_seo_marketing LIKE 'custom_sachets'");
        $hasCustomSizes = $sizesCol && $sizesCol->num_rows > 0;
        $hasCustomSachets = $sachetsCol && $sachetsCol->num_rows > 0;
    }
    $stockCheck = $conn->query("SHOW TABLES LIKE 'stock_ledger'");
    if ($stockCheck && $stockCheck->num_rows > 0) {
        $stockLedgerExists = true;
    }

    if ($seoTableExists) {
        if ($hasRemedyCustomSizes && $hasCustomSizes) {
            $customSizesSql = "COALESCE(NULLIF(r.custom_sizes, ''), m.custom_sizes) AS custom_sizes";
        } elseif ($hasRemedyCustomSizes) {
            $customSizesSql = "r.custom_sizes AS custom_sizes";
        } elseif ($hasCustomSizes) {
            $customSizesSql = "m.custom_sizes AS custom_sizes";
        } else {
            $customSizesSql = "NULL AS custom_sizes";
        }

        if ($hasRemedyCustomSachets && $hasCustomSachets) {
            $customSachetsSql = "COALESCE(NULLIF(r.custom_sachets, ''), m.custom_sachets) AS custom_sachets";
        } elseif ($hasRemedyCustomSachets) {
            $customSachetsSql = "r.custom_sachets AS custom_sachets";
        } elseif ($hasCustomSachets) {
            $customSachetsSql = "m.custom_sachets AS custom_sachets";
        } else {
            $customSachetsSql = "NULL AS custom_sachets";
        }

        $query = "
            SELECT 
                r.*,
                c.name as category_name,
                s.name as supplier_name,
                m.seo_title,
                m.seo_meta_description,
                m.seo_keywords,
                m.focus_keyword,
                m.og_title,
                m.og_description,
                m.canonical_url,
                m.target_audience,
                m.value_proposition,
                m.customer_pain_points,
                m.cta_text,
                m.cta_link,
                m.faq_q1,
                m.faq_a1,
                m.faq_q2,
                m.faq_a2,
                {$customSizesSql},
                {$customSachetsSql}
            FROM remedies r
            LEFT JOIN categories c ON r.category_id = c.id
            LEFT JOIN suppliers s ON r.supplier_id = s.id
            LEFT JOIN remedy_seo_marketing m ON m.remedy_id = r.id
            WHERE r.id = ?
        ";
    } else {
        $customSizesSql = $hasRemedyCustomSizes ? 'r.custom_sizes AS custom_sizes' : 'NULL AS custom_sizes';
        $customSachetsSql = $hasRemedyCustomSachets ? 'r.custom_sachets AS custom_sachets' : 'NULL AS custom_sachets';

        $query = "
            SELECT 
                r.*,
                c.name as category_name,
                s.name as supplier_name,
                {$customSizesSql},
                {$customSachetsSql}
            FROM remedies r
            LEFT JOIN categories c ON r.category_id = c.id
            LEFT JOIN suppliers s ON r.supplier_id = s.id
            WHERE r.id = ?
        ";
    }
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare remedy query');
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Remedy not found');
    }
    
    $remedy = $result->fetch_assoc();
    $stmt->close();

    $salesStats = [
        'orders_count' => 0,
        'units_sold' => 0,
        'total_sales' => 0
    ];
    $recentOrders = [];
    $stockHistory = [];

    $salesStmt = $conn->prepare("
        SELECT
            COUNT(DISTINCT order_id) AS orders_count,
            COALESCE(SUM(quantity), 0) AS units_sold,
            COALESCE(SUM(total_price), 0) AS total_sales
        FROM order_items
        WHERE product_id = ?
    ");
    if ($salesStmt) {
        $salesStmt->bind_param('i', $id);
        $salesStmt->execute();
        $salesRes = $salesStmt->get_result();
        if ($salesRes && $salesRes->num_rows > 0) {
            $salesStats = $salesRes->fetch_assoc();
        }
        $salesStmt->close();
    }

    $ordersStmt = $conn->prepare("
        SELECT
            oi.order_id,
            o.order_number,
            o.customer_name,
            o.payment_status,
            o.order_status,
            oi.quantity,
            oi.unit_price,
            oi.total_price,
            o.created_at
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        WHERE oi.product_id = ?
        ORDER BY o.created_at DESC
        LIMIT 15
    ");
    if ($ordersStmt) {
        $ordersStmt->bind_param('i', $id);
        $ordersStmt->execute();
        $ordersRes = $ordersStmt->get_result();
        if ($ordersRes) {
            $recentOrders = $ordersRes->fetch_all(MYSQLI_ASSOC);
        }
        $ordersStmt->close();
    }

    if ($stockLedgerExists) {
        $histStmt = $conn->prepare("
            SELECT movement_type, qty_change, balance_after, source_ref, movement_at, notes
            FROM stock_ledger
            WHERE remedy_id = ?
            ORDER BY movement_at DESC, id DESC
            LIMIT 20
        ");
        if ($histStmt) {
            $histStmt->bind_param('i', $id);
            $histStmt->execute();
            $histRes = $histStmt->get_result();
            if ($histRes) {
                $stockHistory = $histRes->fetch_all(MYSQLI_ASSOC);
            }
            $histStmt->close();
        }
    } else {
        $auditStmt = $conn->prepare("
            SELECT action AS movement_type, created_at AS movement_at, new_values AS notes
            FROM audit_log
            WHERE table_name IN ('remedies','products') AND record_id = ? AND action LIKE 'stock_update:%'
            ORDER BY created_at DESC
            LIMIT 20
        ");
        if ($auditStmt) {
            $auditStmt->bind_param('i', $id);
            $auditStmt->execute();
            $auditRes = $auditStmt->get_result();
            if ($auditRes) {
                $stockHistory = $auditRes->fetch_all(MYSQLI_ASSOC);
            }
            $auditStmt->close();
        }
    }

    $seoData = [];
    if ($seoTableExists) {
        $seoStmt = $conn->prepare("SELECT * FROM remedy_seo_marketing WHERE remedy_id = ? LIMIT 1");
        if ($seoStmt) {
            $seoStmt->bind_param('i', $id);
            $seoStmt->execute();
            $seoRes = $seoStmt->get_result();
            $seoData = $seoRes && $seoRes->num_rows > 0 ? $seoRes->fetch_assoc() : [];
            $seoStmt->close();
        }
    }

    $conn->close();
    
    echo json_encode([
        'success' => true,
        'data' => $remedy,
        'seo_data' => $seoData,
        'sales_stats' => $salesStats,
        'recent_orders' => $recentOrders,
        'stock_history' => $stockHistory
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit;
?>
