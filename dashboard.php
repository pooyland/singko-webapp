<?php
// dashboard.php
// 1. ALWAYS START SESSION FIRST.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Include database config. Ensure db_config.php has no output (no closing tag or stray spaces/newlines).
require_once 'db_config.php'; // This should now provide $conn as a MySQLi object

// Check if $conn is a valid MySQLi object immediately after inclusion
if (!$conn instanceof mysqli) {
    error_log("Dashboard Page: Database connection (\$conn) is not a MySQLi object. Check db_config.php.");
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
$username = $_SESSION['username'] ?? 'User'; // Default to 'User' if not set
$current_page = basename($_SERVER['PHP_SELF']);

// Initialize dashboard data
$total_orders = 0;
$pending_orders_count = 0; // Renamed to avoid conflict with $pending_orders array
$total_earnings = 0; // New variable for total earnings
$total_spent = 0;
$recent_orders = [];
$message = '';
$message_type = '';

// --- Fetch Dashboard Data (MySQLi) ---
try {
    // Total Orders (as a buyer AND seller)
    // Summing orders where user is buyer or user is seller of products in the order
    $stmt_total_orders = $conn->prepare("
        SELECT COUNT(DISTINCT o.order_id) AS total_orders_count
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE o.user_id = ? OR p.seller_id = ?
    ");
    if ($stmt_total_orders === false) {
        error_log("Dashboard: MySQLi prepare failed for total orders: " . $conn->error);
        throw new Exception("Failed to prepare total orders query.");
    }
    $stmt_total_orders->bind_param("ii", $user_id, $user_id);
    $stmt_total_orders->execute();
    $result_total_orders = $stmt_total_orders->get_result();
    $total_orders = $result_total_orders->fetch_row()[0];
    $stmt_total_orders->close();
    error_log("Dashboard Debug: Total Orders fetched: " . $total_orders . " for user_id: " . $user_id);


    // Pending Orders (as a buyer AND seller)
    // Summing pending orders where user is buyer or user is seller of products in the order
    $stmt_pending_orders_count = $conn->prepare("
        SELECT COUNT(DISTINCT o.order_id) AS pending_orders_count
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE LOWER(o.order_status) = 'pending' AND (o.user_id = ? OR p.seller_id = ?)
    ");
    if ($stmt_pending_orders_count === false) {
        error_log("Dashboard: MySQLi prepare failed for pending orders count: " . $conn->error);
        throw new Exception("Failed to prepare pending orders count query.");
    }
    $stmt_pending_orders_count->bind_param("ii", $user_id, $user_id);
    $stmt_pending_orders_count->execute();
    $result_pending_orders_count = $stmt_pending_orders_count->get_result();
    $pending_orders_count = $result_pending_orders_count->fetch_row()[0];
    $stmt_pending_orders_count->close();
    error_log("Dashboard Debug: Pending Orders fetched: " . $pending_orders_count . " for user_id: " . $user_id);


    // Total Earnings (as a seller)
    // Sum of all order_items price * quantity where the product's seller_id matches the current user
    // This query now matches the sales_report.php logic for total_revenue (no status filter)
    $stmt_total_earnings = $conn->prepare("
        SELECT SUM(oi.quantity * p.price) AS total_earnings
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE p.seller_id = ?
    ");
    if ($stmt_total_earnings === false) {
        error_log("Dashboard: MySQLi prepare failed for total earnings: " . $conn->error);
        throw new Exception("Failed to prepare total earnings query.");
    }
    $stmt_total_earnings->bind_param("i", $user_id);
    $stmt_total_earnings->execute();
    $result_total_earnings = $stmt_total_earnings->get_result();
    $total_earnings_row = $result_total_earnings->fetch_assoc();
    $total_earnings = $total_earnings_row['total_earnings'] ?? 0;
    $stmt_total_earnings->close();
    error_log("Dashboard Debug: Total Earnings fetched: " . $total_earnings . " for user_id: " . $user_id);


    // Total Spent (as a buyer)
    // Sum of all order_items price * quantity for orders placed by the current user
    $stmt_total_spent = $conn->prepare("
        SELECT SUM(oi.quantity * p.price) AS total_spent
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN products p ON oi.product_id = p.product_id
        WHERE o.user_id = ? AND LOWER(o.order_status) = 'completed'
    ");
    if ($stmt_total_spent === false) {
        error_log("Dashboard: MySQLi prepare failed for total spent: " . $conn->error);
        throw new Exception("Failed to prepare total spent query.");
    }
    $stmt_total_spent->bind_param("i", $user_id);
    $stmt_total_spent->execute();
    $result_total_spent = $stmt_total_spent->get_result();
    $total_spent_row = $result_total_spent->fetch_assoc();
    $total_spent = $total_spent_row['total_spent'] ?? 0;
    $stmt_total_spent->close();
    error_log("Dashboard Debug: Total Spent fetched: " . $total_spent . " for user_id: " . $user_id);


    // Recent Orders (as a buyer, limit 3, including relevant order details and status)
    $sql_recent_orders = "SELECT
                            o.order_id,
                            o.order_date,
                            o.order_status AS status,
                            SUM(oi.quantity * oi.price_at_purchase) AS total_amount_at_purchase, -- Use price_at_purchase
                            GROUP_CONCAT(p.name SEPARATOR ', ') AS product_names
                        FROM orders o
                        JOIN order_items oi ON o.order_id = oi.order_id
                        JOIN products p ON oi.product_id = p.product_id
                        WHERE o.user_id = ?
                        GROUP BY o.order_id, o.order_date, o.order_status
                        ORDER BY o.order_date DESC
                        LIMIT 3";
    $stmt_recent_orders = $conn->prepare($sql_recent_orders);
    if ($stmt_recent_orders === false) {
        error_log("Dashboard: MySQLi prepare failed for recent orders: " . $conn->error);
        throw new Exception("Failed to prepare recent orders query.");
    }
    $stmt_recent_orders->bind_param("i", $user_id);
    $stmt_recent_orders->execute();
    $result_recent_orders = $stmt_recent_orders->get_result();

    if ($result_recent_orders->num_rows > 0) {
        while ($row = $result_recent_orders->fetch_assoc()) {
            $recent_orders[] = $row;
        }
    }
    $stmt_recent_orders->close();
    error_log("Dashboard Debug: Recent Orders fetched: " . count($recent_orders) . " for user_id: " . $user_id);

} catch (Exception $e) {
    error_log("Dashboard: Database error during data fetching: " . $e->getMessage());
    $message = "Error loading dashboard data: " . $e->getMessage();
    $message_type = "danger";
}


// Include the header which contains <!DOCTYPE html>, <head>, and the top navigation bar
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
                <li class="menu-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <a href="dashboard.php" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-home-circle"></i>
                        <div data-i18n="Dashboard">Dashboard</div>
                    </a>
                </li>

                <li class="menu-header small text-uppercase">
                    <span class="menu-header-text">Marketplace</span>
                </li>
                <li class="menu-item <?php echo ($current_page == 'my_products.php') ? 'active' : ''; ?>">
                    <a href="my_products.php" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-collection"></i> <div data-i18n="My Products">My Products</div> </a>
                </li>
                <li class="menu-item <?php echo ($current_page == 'my_cart.php') ? 'active' : ''; ?>">
                    <a href="my_cart.php" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-cart"></i>
                        <div data-i18n="My Cart">My Cart</div>
                    </a>
                </li>
                <li class="menu-item <?php echo ($current_page == 'my_orders.php') ? 'active' : ''; ?>">
                    <a href="my_orders.php" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-list-ul"></i>
                        <div data-i18n="My Orders">My Orders</div>
                    </a>
                </li>
                <li class="menu-item <?php echo ($current_page == 'pending_orders.php') ? 'active' : ''; ?>">
                    <a href="pending_orders.php" class="menu-link">
                        <i class='menu-icon tf-icons bx bx-time'></i>
                        <div data-i18n="Pending Orders">Pending Orders</div>
                    </a>
                </li>
                <li class="menu-item <?php echo ($current_page == 'add_product.php') ? 'active' : ''; ?>">
                    <a href="add_product.php" class="menu-link">
                        <i class='menu-icon tf-icons bx bx-plus-circle'></i>
                        <div data-i18n="Add new Product">Add new Product</div>
                    </a>
                </li>
                <li class="menu-item <?php echo ($current_page == 'sales_report.php') ? 'active' : ''; ?>">
                    <a href="sales_report.php" class="menu-link">
                        <i class='menu-icon tf-icons bx bx-bar-chart-alt-2'></i> <div data-i18n="Sales Report">Sales Report</div>
                    </a>
                </li>

                <li class="menu-header small text-uppercase">
                    <span class="menu-header-text">Account Settings</span>
                </li>
                <li class="menu-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                    <a href="profile.php" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-user"></i>
                        <div data-i18n="Profile">Profile</div>
                    </a>
                </li>
                <li class="menu-item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
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
                    <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Dashboard /</span> Analytics</h4>

                    <div class="row">
                        <div class="col-lg-8 mb-4 order-0">
                            <div class="card">
                                <div class="d-flex align-items-end row">
                                    <div class="col-sm-7">
                                        <div class="card-body">
                                            <h5 class="card-title text-primary">Welcome back, <?php echo htmlspecialchars($username); ?>! 👋</h5>
                                            <p class="mb-4">
                                                Here's a quick overview of your activities.
                                            </p>

                                            <a href="my_orders.php" class="btn btn-sm btn-outline-primary">View my orders</a>
                                        </div>
                                    </div>
                                    <div class="col-sm-5 text-center text-sm-left">
                                        <div class="card-body pb-0 px-0 px-md-4">
                                            <img src="https://placehold.co/140x140/cccccc/000000?text=Welcome" alt="Welcome Image" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 order-1">
                            <div class="row">
                                <div class="col-lg-6 col-md-12 col-6 mb-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="card-title d-flex align-items-start justify-content-between">
                                                <div class="avatar flex-shrink-0">
                                                    <i class="bx bx-cart bx-md text-info"></i>
                                                </div>
                                            </div>
                                            <span class="fw-semibold d-block mb-1">Total Orders</span>
                                            <h3 class="card-title mb-2"><?php echo htmlspecialchars($total_orders); ?></h3>
                                            <small class="text-success fw-semibold"><i class="bx bx-up-arrow-alt"></i> +XX%</small> <?php /* Placeholder for trend */ ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6 col-md-12 col-6 mb-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="card-title d-flex align-items-start justify-content-between">
                                                <div class="avatar flex-shrink-0">
                                                    <i class="bx bx-time bx-md text-warning"></i>
                                                </div>
                                            </div>
                                            <span class="fw-semibold d-block mb-1">Pending Orders</span>
                                            <h3 class="card-title text-nowrap mb-2"><?php echo htmlspecialchars($pending_orders_count); ?></h3>
                                            <small class="text-danger fw-semibold"><i class="bx bx-down-arrow-alt"></i> -YY%</small> <?php /* Placeholder for trend */ ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-8 col-lg-4 order-2 order-md-2 mb-4">
                            <div class="card h-100">
                                <div class="card-header d-flex align-items-center justify-content-between">
                                    <h5 class="card-title m-0 me-2">Total Earnings</h5> <?php // Changed from Wishlist Items ?>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="d-flex flex-column align-items-center gap-1">
                                            <h2 class="mb-2">PHP <?php echo number_format($total_earnings, 2); ?></h2> <?php // Display total earnings ?>
                                            <span>Total Earnings</span>
                                        </div>
                                        <div id="earningsChart"></div> <?php // Changed ID for clarity ?>
                                    </div>
                                    <ul class="p-0 m-0">
                                        <?php /* You can add dynamic recent earnings here if needed */ ?>
                                        <li class="d-flex mb-4 pb-1">
                                            <div class="avatar flex-shrink-0 me-3">
                                                <i class="bx bx-dollar-circle bx-md text-success"></i> <?php // Icon for earnings ?>
                                            </div>
                                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                <div class="me-2">
                                                    <small class="text-muted d-block mb-1">This Year</small>
                                                    <h6 class="mb-0">PHP <?php echo number_format($total_earnings, 2); ?></h6>
                                                </div>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-8 col-lg-4 order-3 order-md-3 mb-4">
                            <div class="card h-100">
                                <div class="card-header d-flex align-items-center justify-content-between">
                                    <h5 class="card-title m-0 me-2">Total Spent</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="d-flex flex-column align-items-center gap-1">
                                            <h2 class="mb-2">PHP <?php echo number_format($total_spent, 2); ?></h2>
                                            <span>Total Spent</span>
                                        </div>
                                        <div id="totalSpentChart"></div>
                                    </div>
                                    <ul class="p-0 m-0">
                                        <?php /* You can add dynamic monthly/yearly spent data here if needed */ ?>
                                        <li class="d-flex mb-4 pb-1">
                                            <div class="avatar flex-shrink-0 me-3">
                                                <i class="bx bx-wallet bx-md text-primary"></i> <?php // Icon for spent ?>
                                            </div>
                                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                <div class="me-2">
                                                    <small class="text-muted d-block mb-1">Lifetime</small>
                                                    <h6 class="mb-0">PHP <?php echo number_format($total_spent, 2); ?></h6>
                                                </div>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 order-4 order-md-4">
                            <div class="card">
                                <h5 class="card-header">Recent Orders</h5>
                                <div class="card-body">
                                    <div class="table-responsive text-nowrap">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Order ID</th>
                                                    <th>Date</th>
                                                    <th>Status</th>
                                                    <th>Products</th> <!-- Added Products column -->
                                                    <th>Total</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="table-border-bottom-0">
                                                <?php if (!empty($recent_orders)): ?>
                                                    <?php foreach ($recent_orders as $order): ?>
                                                        <tr>
                                                            <td><i class="bx bx-hash bx-sm text-primary me-2"></i> <strong><?php echo htmlspecialchars($order['order_id']); ?></strong></td>
                                                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
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
                                                            <td><?php echo htmlspecialchars($order['product_names']); ?></td> <!-- Display product names -->
                                                            <td>PHP <?php echo number_format($order['total_amount_at_purchase'], 2); ?></td> <!-- Use total_amount_at_purchase -->
                                                            <td>
                                                                <div class="dropdown">
                                                                    <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                                        <i class="bx bx-dots-vertical-rounded"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu">
                                                                        <a class="dropdown-item" href="my_orders.php?order_id=<?php echo htmlspecialchars($order['order_id']); ?>"><i class="bx bx-edit-alt me-1"></i> View Details</a>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center">No recent orders found.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
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
<script async defer src="https://buttons.github.io/buttons.js"></script>

</body>
</html>
