<?php
// my_orders.php
// 1. ALWAYS START SESSION FIRST.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Include database config. Ensure db_config.php has no output (no closing  tag or stray spaces/newlines).
require_once 'db_config.php'; // This should now provide $conn as a MySQLi object

// Check if $conn is a valid MySQLi object immediately after inclusion
if (!$conn instanceof mysqli) {
    error_log("My Orders Page: Database connection (\$conn) is not a MySQLi object. Check db_config.php.");
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
$my_orders = [];
$message = ''; // Initialize message variable for display messages
$message_type = ''; // Initialize message type

// Fetch orders made by the current user
// Note: Changed o.status to o.order_status based on your 'orders' table schema
$sql_orders = "SELECT
                    o.order_id,
                    o.order_date,
                    o.order_status AS status, -- Use order_status, alias as 'status' for existing display logic
                    SUM(oi.quantity * p.price) AS order_total,
                    GROUP_CONCAT(p.name SEPARATOR ', ') AS products_in_order
                FROM orders o
                JOIN order_items oi ON o.order_id = oi.order_id
                JOIN products p ON oi.product_id = p.product_id
                WHERE o.user_id = ?
                GROUP BY o.order_id, o.order_date, o.order_status
                ORDER BY o.order_date DESC";

try {
    $stmt_orders = $conn->prepare($sql_orders);
    if ($stmt_orders === false) {
        // MySQLi error handling
        error_log("My Orders Page: MySQLi prepare failed for orders query: " . $conn->error);
        throw new Exception("Failed to prepare orders query."); // Throw generic Exception for consistent error handling
    }
    // MySQLi: Bind parameters
    $stmt_orders->bind_param("i", $user_id);
    $stmt_orders->execute();
    
    // MySQLi: Get result and fetch all rows
    $result_orders = $stmt_orders->get_result();
    if ($result_orders->num_rows > 0) {
        while ($row = $result_orders->fetch_assoc()) {
            $my_orders[] = $row;
        }
    }
    $stmt_orders->close(); // Close the statement
    // Do NOT close $conn here, as it's needed by other includes (e.g., header.php or main script end)

} catch (Exception $e) { // Catch generic Exception
    error_log("My Orders Page: Database error fetching orders (MySQLi): " . $e->getMessage());
    $message = "Error loading your orders. Please try again later.";
    $message_type = "danger";
}

// Check for messages stored in session (e.g., from successful checkout)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']); // Clear the message after displaying
    unset($_SESSION['message_type']);
}

// 4. NOW, include the header which contains the initial HTML structure.
// This MUST be AFTER ALL PHP logic that might redirect or set messages.
include 'includes/header.php';
?>

<div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
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
            <div class="layout-page">
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>
                    
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">My Account /</span> My Orders</h4>

                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="card">
                            <h5 class="card-header">My Purchase History</h5>
                            <div class="card-body">
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Order ID</th>
                                                <th>Order Date</th>
                                                <th>Products</th>
                                                <th>Total Amount</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-border-bottom-0">
                                            <?php if (!empty($my_orders)): ?>
                                                <?php foreach ($my_orders as $order): ?>
                                                    <tr>
                                                        <td><strong>#<?php echo htmlspecialchars($order['order_id']); ?></strong></td>
                                                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
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
                                                            <div class="dropdown">
                                                                <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                                    <i class="bx bx-dots-vertical-rounded"></i>
                                                                </button>
                                                                <div class="dropdown-menu">
                                                                    <a class="dropdown-item" href="order_details.php?id=<?php echo htmlspecialchars($order['order_id']); ?>"><i class="bx bx-file me-1"></i> View Details</a>
                                                                    </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">You have not placed any orders yet. <a href="index.php">Start shopping!</a></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="content-backdrop fade"></div>
                </div>
                </div>
            </div>

        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    </main> <footer class="bg-dark text-white py-4 mt-5">
    <div class="container text-center">
        <p>&copy; <?php echo date('Y'); ?> Singko eCommerce. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="js/helpers.js"></script>
<script src="js/config.js"></script>
<script src="js/menu.js"></script>
<script src="js/main.js"></script>
<script async defer src="https://buttons.github.io/buttons.js"></script>

</body>
</html>
