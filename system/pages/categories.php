<?php
// pages/categories.php - Categories Management

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and has admin/staff access
session_start();
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    header('Location: ' . ((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/login.php'));
    exit();
}

$user_role = $_SESSION['role'] ?? 'admin';
$user_id = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0;

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

// Helper function for slug creation if not in functions.php
if (!function_exists('createSlug')) {
    function createSlug($string) {
        $string = strtolower($string);
        $string = preg_replace('/[^a-z0-9-]/', '-', $string);
        $string = preg_replace('/-+/', '-', $string);
        return trim($string, '-');
    }
}

// Helper function to fetch one row
if (!function_exists('fetchOne')) {
    function fetchOne($conn, $query, $params = [], $types = "") {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            return null;
        }
        
        if (!empty($params) && !empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row;
    }
}

// Helper function to fetch all rows
if (!function_exists('fetchAll')) {
    function fetchAll($conn, $query, $params = [], $types = "") {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            return [];
        }
        
        if (!empty($params) && !empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $rows;
    }
}

// Helper function for INSERT/UPDATE/DELETE
if (!function_exists('executeStatement')) {
    function executeStatement($conn, $query, $params = [], $types = "") {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        if (!empty($params) && !empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $result = $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $insert_id = $stmt->insert_id;
        $stmt->close();
        
        return $insert_id ?: $affected_rows;
    }
}

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_category') {
        $name = trim($_POST['name']);
        $slug = createSlug($name);
        $description = trim($_POST['description'] ?? '');
        $color = $_POST['color'] ?? '#2e7d32';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Check if category already exists
        $checkQuery = "SELECT id FROM categories WHERE name = ? OR slug = ?";
        $existing = fetchOne($conn, $checkQuery, [$name, $slug], "ss");
        
        if ($existing) {
            $_SESSION['error'] = "Category with this name already exists!";
        } else {
            $insertQuery = "INSERT INTO categories (name, slug, description, color, is_active, created_at) 
                           VALUES (?, ?, ?, ?, ?, NOW())";
            $result = executeStatement($conn, $insertQuery, [$name, $slug, $description, $color, $is_active], "ssssi");
            
            if ($result) {
                $_SESSION['success'] = "Category added successfully!";
            } else {
                $_SESSION['error'] = "Failed to add category. Please try again.";
            }
        }
        header('Location: categories.php');
        exit();
    }
    
    // Handle Edit Category
    if ($_POST['action'] === 'edit_category') {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $slug = createSlug($name);
        $description = trim($_POST['description'] ?? '');
        $color = $_POST['color'] ?? '#2e7d32';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $updateQuery = "UPDATE categories SET name = ?, slug = ?, description = ?, color = ?, is_active = ? WHERE id = ?";
        $result = executeStatement($conn, $updateQuery, [$name, $slug, $description, $color, $is_active, $id], "ssssii");
        
        if ($result) {
            $_SESSION['success'] = "Category updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update category.";
        }
        header('Location: categories.php');
        exit();
    }
    
    // Handle Delete Category
    if ($_POST['action'] === 'delete_category' && $user_role === 'admin') {
        $id = intval($_POST['id']);
        
        // Check if category has products
        $checkQuery = "SELECT COUNT(*) as count FROM remedies WHERE category_id = ?";
        $result = fetchOne($conn, $checkQuery, [$id], "i");
        
        if ($result && $result['count'] > 0) {
            $_SESSION['error'] = "Cannot delete category with existing remedies. Move or delete remedies first.";
        } else {
            $deleteQuery = "DELETE FROM categories WHERE id = ?";
            $result = executeStatement($conn, $deleteQuery, [$id], "i");
            
            if ($result) {
                $_SESSION['success'] = "Category deleted successfully!";
            } else {
                $_SESSION['error'] = "Failed to delete category.";
            }
        }
        header('Location: categories.php');
        exit();
    }
}

// Get all categories
$categoriesQuery = "SELECT c.*, 
                    (SELECT COUNT(*) FROM remedies WHERE category_id = c.id) as product_count 
                    FROM categories c 
                    ORDER BY c.name ASC";
$categories = fetchAll($conn, $categoriesQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .top-bar {
            background: #fff;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .page-title {
            margin: 0;
            font-size: 1.5rem;
            color: #212529;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.2;
        }
        .category-color-preview {
            width: 30px;
            height: 30px;
            border-radius: 5px;
            display: inline-block;
            margin-right: 10px;
            border: 1px solid #ddd;
            vertical-align: middle;
        }
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .table th {
            background-color: #f8f9fa;
        }
        .badge {
            padding: 5px 10px;
        }
        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                padding: 12px 14px;
            }
            .page-title {
                font-size: 1.2rem;
            }
        }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="top-bar">
            <h1 class="page-title">
                <i class="fas fa-tags"></i>
                Categories Management
            </h1>
            <div class="btn-toolbar d-flex flex-wrap gap-2">
                <a href="../admin_dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                </a>
                <a href="../admin_dashboard.php?page=remedies" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Remedies
                </a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus"></i> Add New Category
                </button>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Categories Stats -->
        <?php
        $total_categories = count($categories);
        $active_categories = count(array_filter($categories, fn($c) => $c['is_active'] == 1));
        $with_products = count(array_filter($categories, fn($c) => $c['product_count'] > 0));
        $empty_categories = count(array_filter($categories, fn($c) => $c['product_count'] == 0));
        ?>
        
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-2">Total Categories</h6>
                                <h2 class="mb-0"><?php echo $total_categories; ?></h2>
                            </div>
                            <i class="fas fa-tags fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-2">Active Categories</h6>
                                <h2 class="mb-0"><?php echo $active_categories; ?></h2>
                            </div>
                            <i class="fas fa-check-circle fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-info text-white stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-2">With Products</h6>
                                <h2 class="mb-0"><?php echo $with_products; ?></h2>
                            </div>
                            <i class="fas fa-box fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-warning text-white stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-2">Empty Categories</h6>
                                <h2 class="mb-0"><?php echo $empty_categories; ?></h2>
                            </div>
                            <i class="fas fa-folder-open fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Categories Table -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Categories</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Color</th>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Description</th>
                                <th>Products</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($categories)): ?>
                                <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo $category['id']; ?></td>
                                    <td>
                                        <span class="category-color-preview" style="background-color: <?php echo htmlspecialchars($category['color'] ?? '#2e7d32'); ?>"></span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($category['slug']); ?></code></td>
                                    <td>
                                        <?php 
                                        $desc = $category['description'] ?? '';
                                        echo htmlspecialchars(substr($desc, 0, 30)) . (strlen($desc) > 30 ? '...' : ''); 
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $category['product_count'] > 0 ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $category['product_count']; ?> products
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($category['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($category['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick='editCategory(<?php echo json_encode($category); ?>)' title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user_role === 'admin' && $category['product_count'] == 0): ?>
                                        <button class="btn btn-sm btn-danger" onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars(addslashes($category['name'])); ?>')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                                        <h5>No categories found</h5>
                                        <p class="text-muted">Click "Add New Category" to create your first category.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add_category">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-plus-circle me-2"></i>Add New Category
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Category Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required 
                                   placeholder="e.g., Herbal Teas">
                            <small class="text-muted">This will be displayed to customers</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="color" class="form-label">Category Color</label>
                            <input type="color" class="form-control form-control-color" id="color" 
                                   name="color" value="#2e7d32" style="width: 100%; height: 50px;">
                            <small class="text-muted">Choose a color for visual identification</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" 
                                      rows="3" placeholder="Brief description of this category"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" 
                                       name="is_active" checked>
                                <label class="form-check-label" for="is_active">
                                    Active (visible to customers)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Add Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="edit_category">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit me-2"></i>Edit Category
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Category Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_color" class="form-label">Category Color</label>
                            <input type="color" class="form-control form-control-color" id="edit_color" 
                                   name="color" style="width: 100%; height: 50px;">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" 
                                      rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="edit_is_active" 
                                       name="is_active">
                                <label class="form-check-label" for="edit_is_active">
                                    Active (visible to customers)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Update Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Category Form -->
    <form method="POST" id="deleteCategoryForm" style="display: none;">
        <input type="hidden" name="action" value="delete_category">
        <input type="hidden" name="id" id="delete_id">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCategory(category) {
            document.getElementById('edit_id').value = category.id;
            document.getElementById('edit_name').value = category.name;
            document.getElementById('edit_color').value = category.color || '#2e7d32';
            document.getElementById('edit_description').value = category.description || '';
            document.getElementById('edit_is_active').checked = category.is_active == 1;
            
            new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
        }

        function deleteCategory(id, name) {
            if (confirm(`Are you sure you want to delete category "${name}"?`)) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteCategoryForm').submit();
            }
        }
    </script>
</body>
</html>
