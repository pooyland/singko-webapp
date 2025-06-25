<?php
// my_products.php
include 'includes/header.php'; // Includes the top bar, navigation, and opens <main>

// Include your database configuration
require_once 'db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$seller_id = $_SESSION['user_id']; // Get the logged-in user's ID

$products = [];
$search_query = "";

// Handle search functionality
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_query = trim($_GET['search']);
    $sql = "SELECT p.product_id, p.name, p.description, p.price, p.stock_quantity, p.image_url, c.name as category_name
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            WHERE p.seller_id = ? AND (p.name LIKE ? OR p.description LIKE ?)
            ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }
    $search_param = '%' . $search_query . '%';
    $stmt->bind_param("iss", $seller_id, $search_param, $search_param);
} else {
    // Fetch all products for the logged-in seller
    $sql = "SELECT p.product_id, p.name, p.description, p.price, p.stock_quantity, p.image_url, c.name as category_name
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            WHERE p.seller_id = ?
            ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }
    $stmt->bind_param("i", $seller_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
$stmt->close();
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
                        <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Product /</span> My Products</h4>

                        <div class="card">
                            <h5 class="card-header d-flex justify-content-between align-items-center">
                                My Product Listings
                                <a href="add_product.php" class="btn btn-primary-sneat">Add New Product</a>
                            </h5>
                            <div class="card-body">
                                <form action="" method="GET" class="mb-3">
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="Search products..." name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                                        <button class="btn btn-outline-secondary" type="submit">Search</button>
                                        <?php if (!empty($search_query)): ?>
                                            <a href="my_products.php" class="btn btn-outline-danger">Clear Search</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Product ID</th>
                                                <th>Image</th>
                                                <th>Name</th>
                                                <th>Description</th>
                                                <th>Category</th>
                                                <th>Price</th>
                                                <th>Stock</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-border-bottom-0">
                                            <?php if (!empty($products)): ?>
                                                <?php foreach ($products as $product): ?>
                                                    <tr>
                                                        <td><strong>#<?php echo htmlspecialchars($product['product_id']); ?></strong></td>
                                                        <td>
                                                            <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'https://via.placeholder.com/50?text=No+Image'); ?>" alt="Product Image" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                                        </td>
                                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                        <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($product['description']); ?></td>
                                                        <td><span class="badge bg-label-primary me-1"><?php echo htmlspecialchars($product['category_name']); ?></span></td>
                                                        <td>PHP <?php echo number_format($product['price'], 2); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo ($product['stock_quantity'] > 10) ? 'bg-label-success' : (($product['stock_quantity'] > 0) ? 'bg-label-warning' : 'bg-label-danger'); ?> me-1">
                                                                <?php echo htmlspecialchars($product['stock_quantity']); ?>
                                                            </span>
                                                        </td>
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
                                                    <td colspan="8" class="text-center">No products found for this seller. <a href="add_product.php">Add a new product</a></td>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="js/helpers.js"></script>
<script src="js/config.js"></script>
<script src="js/menu.js"></script>
<script src="js/main.js"></script>
<script async defer src="https://buttons.github.io/buttons.js"></script>

</body>
</html>