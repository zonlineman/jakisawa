<?php
// includes/navbar.php
if (!isset($_SESSION)) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #2e7d32, #1b5e20);">
    <div class="container-fluid">
        <a class="navbar-brand" href="../index.php">
            <i class="fas fa-leaf me-2"></i>
            <strong>JAKISAWA Herbal</strong>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage == 'index.php' ? 'active' : ''; ?>" href="../index.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage == 'remedies.php' ? 'active' : ''; ?>" href="remedies.php">
                        <i class="fas fa-prescription-bottle-alt"></i> Remedies
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage == 'orders.php' ? 'active' : ''; ?>" href="orders.php">
                        <i class="fas fa-shopping-cart"></i> Orders
                    </a>
                </li>
                <?php if (isset($_SESSION['role']) && ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff')): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage == 'add_remedy.php' ? 'active' : ''; ?>" href="add_remedy.php">
                        <i class="fas fa-plus-circle"></i> Add Remedy
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            
            <div class="navbar-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i> 
                            <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>
                            <span class="badge bg-light text-dark ms-1"><?php echo $_SESSION['role']; ?></span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../auth/profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <a class="nav-link" href="../auth/login.php">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>