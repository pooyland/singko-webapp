<?php
// profile.php
include 'includes/header.php'; // Includes the top bar, navigation, and opens <main>

// Include your database configuration
require_once 'db_config.php';

// Start session if not already started (header.php might do this already)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$user_info = [];
$products_sold_by_user = [];
$products_bought_by_user = [];
$total_earnings_as_seller = 0;
$total_spending_as_buyer = 0;

// --- Fetch User Information ---
$sql_user_info = "SELECT username, email FROM users WHERE user_id = ?";
$stmt_user_info = $conn->prepare($sql_user_info);
if ($stmt_user_info === false) {
    die('Prepare failed for user info: ' . htmlspecialchars($conn->error));
}
$stmt_user_info->bind_param("i", $user_id);
$stmt_user_info->execute();
$result_user_info = $stmt_user_info->get_result();
if ($result_user_info->num_rows > 0) {
    $user_info = $result_user_info->fetch_assoc();
}
$stmt_user_info->close();

// --- Fetch Products User Sells ---
$sql_products_sold = "SELECT
                            p.product_id,
                            p.name,
                            p.price,
                            p.stock_quantity,
                            p.image_url,
                            c.name as category_name
                          FROM products p
                          JOIN categories c ON p.category_id = c.category_id
                          WHERE p.seller_id = ?
                          ORDER BY p.created_at DESC";
$stmt_products_sold = $conn->prepare($sql_products_sold);
if ($stmt_products_sold === false) {
    die('Prepare failed for products sold: ' . htmlspecialchars($conn->error));
}
$stmt_products_sold->bind_param("i", $user_id);
$stmt_products_sold->execute();
$result_products_sold = $stmt_products_sold->get_result();
if ($result_products_sold->num_rows > 0) {
    while ($row = $result_products_sold->fetch_assoc()) {
        $products_sold_by_user[] = $row;
    }
}
$stmt_products_sold->close();

// --- Fetch Products User Buys (Distinct products from their orders) ---
$sql_products_bought = "SELECT DISTINCT
                            p.product_id,
                            p.name,
                            p.price,
                            p.image_url,
                            c.name as category_name
                        FROM orders o
                        JOIN order_items oi ON o.order_id = oi.order_id
                        JOIN products p ON oi.product_id = p.product_id
                        JOIN categories c ON p.category_id = c.category_id
                        WHERE o.user_id = ?
                        ORDER BY p.name ASC"; // Ordered by name for display
$stmt_products_bought = $conn->prepare($sql_products_bought);
if ($stmt_products_bought === false) {
    die('Prepare failed for products bought: ' . htmlspecialchars($conn->error));
}
$stmt_products_bought->bind_param("i", $user_id);
$stmt_products_bought->execute();
$result_products_bought = $stmt_products_bought->get_result();
if ($result_products_bought->num_rows > 0) {
    while ($row = $result_products_bought->fetch_assoc()) {
        $products_bought_by_user[] = $row;
    }
}
$stmt_products_bought->close();

// --- Calculate Total Earnings as Seller ---
// Removed the `o.status = 'completed'` filter to align with sales_report.php's total revenue calculation
$sql_earnings = "SELECT SUM(oi.quantity * p.price) AS total_earnings
                 FROM order_items oi
                 JOIN products p ON oi.product_id = p.product_id
                 WHERE p.seller_id = ?";
$stmt_earnings = $conn->prepare($sql_earnings);
if ($stmt_earnings === false) {
    die('Prepare failed for earnings: ' . htmlspecialchars($conn->error));
}
$stmt_earnings->bind_param("i", $user_id);
$stmt_earnings->execute();
$result_earnings = $stmt_earnings->get_result();
if ($result_earnings->num_rows > 0) {
    $earnings_data = $result_earnings->fetch_assoc();
    $total_earnings_as_seller = $earnings_data['total_earnings'] ?? 0;
}
$stmt_earnings->close();

// --- Calculate Total Spending as Buyer ---
// Keeping 'completed' status filter for total spending as it typically refers to successful purchases
$sql_spending = "SELECT SUM(oi.quantity * p.price) AS total_spending
                 FROM orders o
                 JOIN order_items oi ON o.order_id = oi.order_id
                 JOIN products p ON oi.product_id = p.product_id
                 WHERE o.user_id = ? AND LOWER(o.order_status) = 'completed'"; // Added LOWER for consistency
$stmt_spending = $conn->prepare($sql_spending);
if ($stmt_spending === false) {
    die('Prepare failed for spending: ' . htmlspecialchars($conn->error));
}
$stmt_spending->bind_param("i", $user_id);
$stmt_spending->execute();
$result_spending = $stmt_spending->get_result();
if ($result_spending->num_rows > 0) {
    $spending_data = $result_spending->fetch_assoc();
    $total_spending_as_buyer = $spending_data['total_spending'] ?? 0;
}
$stmt_spending->close();

$conn->close();
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
                        <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Account Settings /</span> Profile</h4>

                        <div class="row">
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title mb-4">User Information</h5>
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="avatar avatar-xl me-3">
                                                <img src="https://placehold.co/80x80/cccccc/000000?text=<?php echo substr(htmlspecialchars($user_info['username'] ?? 'User'), 0, 1); ?>" alt="User Avatar" class="rounded-circle" />
                                            </div>
                                            <div>
                                                <h4 class="mb-0"><?php echo htmlspecialchars($user_info['username'] ?? 'N/A'); ?></h4>
                                                <small class="text-muted"><?php echo htmlspecialchars($user_info['email'] ?? 'N/A'); ?></small>
                                            </div>
                                        </div>
                                        <p class="mb-2"><i class="bx bx-id-card me-2"></i> User ID: <strong><?php echo htmlspecialchars($user_id); ?></strong></p>
                                        <p class="mb-0"><i class="bx bx-envelope me-2"></i> Email: <strong><?php echo htmlspecialchars($user_info['email'] ?? 'N/A'); ?></strong></p>
                                        <small class="text-muted mt-2 d-block">This is your general profile information.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-8">
                                <div class="row">
                                    <div class="col-sm-6 mb-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="d-flex align-items-start justify-content-between">
                                                    <div class="avatar flex-shrink-0">
                                                        <i class="bx bx-dollar-circle bx-md text-success"></i>
                                                    </div>
                                                </div>
                                                <span class="fw-semibold d-block mb-1">Total Earnings (as Seller)</span>
                                                <h3 class="card-title mb-2">PHP <?php echo number_format($total_earnings_as_seller, 2); ?></h3>
                                                <small class="text-muted">From your product sales.</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 mb-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="d-flex align-items-start justify-content-between">
                                                    <div class="avatar flex-shrink-0">
                                                        <i class="bx bx-credit-card-alt bx-md text-danger"></i>
                                                    </div>
                                                </div>
                                                <span class="fw-semibold d-block mb-1">Total Spending (as Buyer)</span>
                                                <h3 class="card-title mb-2">PHP <?php echo number_format($total_spending_as_buyer, 2); ?></h3>
                                                <small class="text-muted">For your completed purchases.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <h5 class="card-header">Products You Sell</h5>
                            <div class="card-body">
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Image</th>
                                                <th>Product Name</th>
                                                <th>Price</th>
                                                <th>Stock</th>
                                                <th>Category</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-border-bottom-0">
                                            <?php if (!empty($products_sold_by_user)): ?>
                                                <?php foreach ($products_sold_by_user as $product): ?>
                                                    <tr>
                                                        <td>
                                                            <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'https://via.placeholder.com/40?text=N/A'); ?>" alt="Product Image" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                                        </td>
                                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                        <td>PHP <?php echo number_format($product['price'], 2); ?></td>
                                                        <td><span class="badge <?php echo ($product['stock_quantity'] > 0) ? 'bg-label-success' : 'bg-label-danger'; ?>"><?php echo htmlspecialchars($product['stock_quantity']); ?></span></td>
                                                        <td><span class="badge bg-label-primary me-1"><?php echo htmlspecialchars($product['category_name']); ?></span></td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                                    <i class="bx bx-dots-vertical-rounded"></i>
                                                                </button>
                                                                <div class="dropdown-menu">
                                                                    <a class="dropdown-item" href="edit_product.php?id=<?php echo htmlspecialchars($product['product_id']); ?>"><i class="bx bx-edit-alt me-1"></i> Edit</a>
                                                                    <a class="dropdown-item" href="delete_product.php?id=<?php echo htmlspecialchars($product['product_id']); ?>" onclick="return confirm('Are you sure you want to delete this product?');"><i class="bx bx-trash me-1"></i> Delete</a>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">You are not selling any products yet. <a href="add_product.php">Add a new product</a></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <h5 class="card-header">Products You Have Bought</h5>
                            <div class="card-body">
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Image</th>
                                                <th>Product Name</th>
                                                <th>Price (at purchase)</th>
                                                <th>Category</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-border-bottom-0">
                                            <?php if (!empty($products_bought_by_user)): ?>
                                                <?php foreach ($products_bought_by_user as $product): ?>
                                                    <tr>
                                                        <td>
                                                            <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'https://via.placeholder.com/40?text=N/A'); ?>" alt="Product Image" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                                        </td>
                                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                        <td>PHP <?php echo number_format($product['price'], 2); ?></td>
                                                        <td><span class="badge bg-label-primary me-1"><?php echo htmlspecialchars($product['category_name']); ?></span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">You have not bought any products yet. <a href="index.php">Start shopping!</a></td>
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
