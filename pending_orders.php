<?php
// pending_orders.php
// 1. ALWAYS START SESSION FIRST.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Include database config. Ensure db_config.php has no output (no closintag or stray spaces/newlines).
require_once 'db_config.php';

// Check if $conn is a valid MySQLi object immediately after inclusion
if (!$conn instanceof mysqli) {
    error_log("Pending Orders Page: Database connection (\$conn) is not a MySQLi object. Check db_config.php.");
    $_SESSION['message'] = "Critical database error: Connection not established correctly. Please try again later.";
    $_SESSION['message_type'] = "danger";
    header('Location: login.php'); // Redirect to login or an error page
    exit;
}

// 3. User Login Check (MUST happen BEFORE any HTML output or other includes that output HTML)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pending_orders = [];
$message = '';
$message_type = '';

// Handle order status updates (Complete/Cancel)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['order_id'], $_POST['action'])) {
    $order_id = filter_var($_POST['order_id'], FILTER_VALIDATE_INT);
    $action = $_POST['action']; // 'complete' or 'cancel'

    if ($order_id === false || $order_id <= 0) {
        $_SESSION['message'] = "Invalid Order ID.";
        $_SESSION['message_type'] = "danger";
    } else {
        $new_status = '';
        if ($action === 'complete') {
            $new_status = 'Completed';
        } elseif ($action === 'cancel') {
            $new_status = 'Cancelled';
        }

        if (!empty($new_status)) {
            // Start transaction
            $conn->begin_transaction();
            try {
                // Verify ownership of the order before updating
                // This query checks if *any* product in the order is sold by the current user.
                // For a more robust check, you might want to ensure *all* products in the order
                // are from the current seller, or handle partial orders differently.
                $stmt_check_owner = $conn->prepare("SELECT COUNT(DISTINCT oi.product_id) FROM orders o JOIN order_items oi ON o.order_id = oi.order_id JOIN products p ON oi.product_id = p.product_id WHERE o.order_id = ? AND p.seller_id = ?");
                if ($stmt_check_owner === false) {
                    error_log("Pending Orders (POST): MySQLi prepare failed for owner check: " . $conn->error);
                    throw new Exception("Failed to prepare owner check query.");
                }
                $stmt_check_owner->bind_param("ii", $order_id, $user_id);
                $stmt_check_owner->execute();
                $result_check_owner = $stmt_check_owner->get_result();
                $is_owner = $result_check_owner->fetch_row()[0] > 0;
                $stmt_check_owner->close();

                if (!$is_owner) {
                    throw new Exception("You do not have permission to update this order.");
                }

                // Update order status
                $stmt_update_order = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
                if ($stmt_update_order === false) {
                    error_log("Pending Orders (POST): MySQLi prepare failed for order status update: " . $conn->error);
                    throw new Exception("Failed to prepare order status update.");
                }
                $stmt_update_order->bind_param("si", $new_status, $order_id);
                if (!$stmt_update_order->execute()) {
                    error_log("Pending Orders (POST): Error updating order status (MySQLi): " . $stmt_update_order->error);
                    throw new Exception("Error updating order status.");
                }
                $stmt_update_order->close();

                // If cancelled, return stock to products
                if ($new_status === 'Cancelled') {
                    $stmt_get_order_items = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                    if ($stmt_get_order_items === false) {
                        error_log("Pending Orders (POST): MySQLi prepare failed for getting order items: " . $conn->error);
                        throw new Exception("Failed to prepare order items retrieval.");
                    }
                    $stmt_get_order_items->bind_param("i", $order_id);
                    $stmt_get_order_items->execute();
                    $result_order_items = $stmt_get_order_items->get_result();

                    while ($item = $result_order_items->fetch_assoc()) {
                        $stmt_update_stock = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?");
                        if ($stmt_update_stock === false) {
                            error_log("Pending Orders (POST): MySQLi prepare failed for stock update: " . $conn->error);
                            throw new Exception("Failed to prepare stock update.");
                        }
                        $stmt_update_stock->bind_param("ii", $item['quantity'], $item['product_id']);
                        if (!$stmt_update_stock->execute()) {
                            error_log("Pending Orders (POST): Error updating stock for product ID " . htmlspecialchars($item['product_id']) . " (MySQLi): " . $stmt_update_stock->error);
                            throw new Exception("Error returning stock for product ID " . htmlspecialchars($item['product_id']) . ".");
                        }
                        $stmt_update_stock->close();
                    }
                    $stmt_get_order_items->close();
                }

                $conn->commit();
                $_SESSION['message'] = "Order " . htmlspecialchars($order_id) . " " . htmlspecialchars($new_status) . " successfully!";
                $_SESSION['message_type'] = "success";

            } catch (Exception $e) {
                $conn->rollback();
                error_log("Pending Orders (POST): Transaction failed: " . $e->getMessage());
                $_SESSION['message'] = "Order update failed: " . $e->getMessage();
                $_SESSION['message_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "Invalid action provided.";
            $_SESSION['message_type'] = "danger";
        }
    }
    header('Location: pending_orders.php');
    exit;
}

// Fetch pending orders for products sold by the current user
$sql_pending_orders = "SELECT
                            o.order_id,
                            o.order_date,
                            o.order_status AS status,
                            u.full_name AS buyer_name,
                            SUM(oi.quantity * p.price) AS order_total,
                            GROUP_CONCAT(p.name SEPARATOR ', ') AS products_in_order
                        FROM orders o
                        JOIN order_items oi ON o.order_id = oi.order_id
                        JOIN products p ON oi.product_id = p.product_id
                        JOIN users u ON o.user_id = u.user_id
                        WHERE p.seller_id = ? AND o.order_status = 'Pending'
                        GROUP BY o.order_id, o.order_date, o.order_status, u.full_name
                        ORDER BY o.order_date DESC";

try {
    $stmt_pending_orders = $conn->prepare($sql_pending_orders);
    if ($stmt_pending_orders === false) {
        error_log("Pending Orders (Display): MySQLi prepare failed for fetching pending orders: " . $conn->error);
        throw new Exception("Failed to prepare pending orders query.");
    }
    $stmt_pending_orders->bind_param("i", $user_id);
    $stmt_pending_orders->execute();
    $result_pending_orders = $stmt_pending_orders->get_result();

    if ($result_pending_orders->num_rows > 0) {
        while ($row = $result_pending_orders->fetch_assoc()) {
            $pending_orders[] = $row;
        }
    }
    $stmt_pending_orders->close();

} catch (Exception $e) {
    error_log("Pending Orders (Display): Database error fetching pending orders (MySQLi): " . $e->getMessage());
    $message = "Error loading pending orders. Please try again later.";
    $message_type = "danger";
}

// Check for messages stored in session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

include 'includes/header.php';
?>

<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        <!-- Menu -->
        <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
            <div class="app-brand demo">
                <a href="index.php" class="app-brand-link">
                    <span class="app-brand-text demo menu-text fw-bolder ms-2">Singko eCommerce</span>
                </a>
                <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-xl-none">
                    <i class="bx bx-chevron-left bx-sm align-middle"></i>
                </a>
            </div>

            <div class="menu-inner-shadow"></div>

            <ul class="menu-inner py-1">
                <!-- Dashboard -->
                <li class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                    <a href="dashboard.php" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-home-circle"></i>
                        <div data-i18n="Dashboard">Dashboard</div>
                    </a>
                </li>

                <li class="menu-header small text-uppercase">
                    <span class="menu-header-text">Marketplace</span>
                </li>
                <li class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'my_products.php') ? 'active' : ''; ?>">
                    <a href="my_products.php" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-collection"></i>
                        <div data-i18n="My Products">My Products</div>
                    </a>
                </li>
                <li class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'my_cart.php') ? 'active' : ''; ?>">
                    <a href="my_cart.php" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-cart"></i>
                        <div data-i18n="My Cart">My Cart</div>
                    </a>
                </li>
                <li class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'my_orders.php') ? 'active' : ''; ?>">
                    <a href="my_orders.php" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-list-ul"></i>
                        <div data-i18n="My Orders">My Orders</div>
                    </a>
                </li>
                <li class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'pending_orders.php') ? 'active' : ''; ?>">
                    <a href="pending_orders.php" class="menu-link">
                        <i class='menu-icon tf-icons bx bx-time'></i>
                        <div data-i18n="Pending Orders">Pending Orders</div>
                    </a>
                </li>
                <li class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'add_product.php') ? 'active' : ''; ?>">
                    <a href="add_product.php" class="menu-link">
                        <i class='menu-icon tf-icons bx bx-plus-circle'></i>
                        <div data-i18n="Add new Product">Add new Product</div>
                    </a>
                </li>
                <li class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'sales_report.php') ? 'active' : ''; ?>">
                    <a href="sales_report.php" class="menu-link">
                        <i class='menu-icon tf-icons bx bx-bar-chart-alt-2'></i>
                        <div data-i18n="Sales Report">Sales Report</div>
                    </a>
                </li>

                <li class="menu-header small text-uppercase">
                    <span class="menu-header-text">Account Settings</span>
                </li>
                <li class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>">
                    <a href="profile.php" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-user"></i>
                        <div data-i18n="Profile">Profile</div>
                    </a>
                </li>
                <li class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'active' : ''; ?>">
                    <a href="settings.php" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-cog"></i>
                        <div data-i18n="Settings">Settings</div>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="logout.php" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-power-off"></i>
                        <div data-i18n="Logout">Logout</div>
                    </a>
                </li>
            </ul>
        </aside>
        <!-- / Menu -->

        <!-- Layout container -->
        <div class="layout-page">
            <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                    <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                        <i class="bx bx-menu bx-sm"></i>
                    </a>
                </div>
                <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                    <ul class="navbar-nav flex-row align-items-center ms-auto">
                        <?php if (isset($_SESSION['username'])): ?>
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="https://placehold.co/40x40/cccccc/000000?text=JD" alt class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="https://placehold.co/40x40/cccccc/000000?text=JD" alt class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                                                    <small class="text-muted">Seller</small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="profile.php">
                                            <i class="bx bx-user me-2"></i>
                                            <span class="align-middle">My Profile</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="settings.php">
                                            <i class="bx bx-cog me-2"></i>
                                            <span class="align-middle">Settings</span>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="logout.php">
                                            <i class="bx bx-power-off me-2"></i>
                                            <span class="align-middle">Log Out</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="login.php">Login</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="register.php">Register</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>

            <!-- Content wrapper -->
            <div class="content-wrapper">
                <!-- Content -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Marketplace /</span> Pending Orders</h4>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <h5 class="card-header">Pending Orders For Your Products</h5>
                        <div class="card-body">
                            <div class="table-responsive text-nowrap">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Date</th>
                                            <th>Buyer</th>
                                            <th>Products</th>
                                            <th>Total Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-border-bottom-0">
                                        <?php if (!empty($pending_orders)): ?>
                                            <?php foreach ($pending_orders as $order): ?>
                                                <tr>
                                                    <td><strong>#<?php echo htmlspecialchars($order['order_id']); ?></strong></td>
                                                    <td><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($order['buyer_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($order['products_in_order']); ?></td>
                                                    <td>PHP <?php echo number_format($order['order_total'], 2); ?></td>
                                                    <td>
                                                        <?php
                                                            $status_class = '';
                                                            switch (strtolower($order['status'])) {
                                                                case 'pending': $status_class = 'bg-label-warning'; break;
                                                                case 'completed': $status_class = 'bg-label-success'; break;
                                                                case 'shipped': $status_class = 'bg-label-info'; break;
                                                                case 'cancelled': $status_class = 'bg-label-danger'; break;
                                                                default: $status_class = 'bg-label-secondary'; break;
                                                            }
                                                        ?>
                                                        <span class="badge <?php echo $status_class; ?> me-1"><?php echo htmlspecialchars(ucfirst($order['status'])); ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="d-inline-flex">
                                                            <?php if (strtolower($order['status']) == 'pending'): // Changed to lowercase comparison ?>
                                                                <form action="pending_orders.php" method="POST" class="me-2">
                                                                    <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id']); ?>">
                                                                    <input type="hidden" name="action" value="complete">
                                                                    <button type="submit" class="btn btn-success btn-sm">Complete</button>
                                                                </form>
                                                                <form action="pending_orders.php" method="POST" class="me-2">
                                                                    <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id']); ?>">
                                                                    <input type="hidden" name="action" value="cancel">
                                                                    <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                                                                </form>
                                                            <?php endif; ?>
                                                            <?php /* Removed the "Details" button as per user request */ ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center">No pending orders for your products.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- / Content -->

                <footer class="content-footer footer bg-footer-theme">
                    <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                        <div class="mb-2 mb-md-0">
                            © <?php echo date('Y'); ?> Singko eCommerce. All rights reserved.
                        </div>
                    </div>
                </footer>
                <div class="content-backdrop fade"></div>
            </div>
            <!-- Content wrapper -->
        </div>
        <!-- / Layout page -->
    </div>

    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>
</div>
<!-- / Layout wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="js/helpers.js"></script>
<script src="js/config.js"></script>
<script src="js/menu.js"></script>
<script src="js/main.js"></script>
<script async defer src="https://buttons.github.io/buttons.js"></script>

</body>
</html>
