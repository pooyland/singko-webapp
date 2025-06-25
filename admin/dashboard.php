<?php
require_once '../db_config.php';
require_once 'includes/admin_header.php'; // This handles admin role check and redirects if not admin

// Fetch some summary data for the dashboard
$total_users = 0;
$total_sellers = 0;
$total_products = 0;
$total_orders = 0;

$sql_users = "SELECT COUNT(*) AS count FROM users WHERE role = 'buyer' OR role = 'admin'";
$result_users = $conn->query($sql_users);
if ($result_users) {
    $total_users = $result_users->fetch_assoc()['count'];
}

$sql_sellers = "SELECT COUNT(*) AS count FROM users WHERE role = 'seller'";
$result_sellers = $conn->query($sql_sellers);
if ($result_sellers) {
    $total_sellers = $result_sellers->fetch_assoc()['count'];
}

$sql_products = "SELECT COUNT(*) AS count FROM products";
$result_products = $conn->query($sql_products);
if ($result_products) {
    $total_products = $result_products->fetch_assoc()['count'];
}

$sql_orders = "SELECT COUNT(*) AS count FROM orders";
$result_orders = $conn->query($sql_orders);
if ($result_orders) {
    $total_orders = $result_orders->fetch_assoc()['count'];
}
?>

<h1 class="mb-4">Dashboard</h1>

<div class="row">
    <div class="col-md-3">
        <div class="admin-card text-center">
            <h5>Total Users (Buyers & Admin)</h5>
            <h2 class="display-4 text-primary"><?php echo $total_users; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <h5>Total Sellers</h5>
            <h2 class="display-4 text-success"><?php echo $total_sellers; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <h5>Total Products</h5>
            <h2 class="display-4 text-warning"><?php echo $total_products; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <h5>Total Orders</h5>
            <h2 class="display-4 text-info"><?php echo $total_orders; ?></h2>
        </div>
    </div>
</div>

<div class="admin-card mt-4">
    <h3>Recent Activities (Example)</h3>
    <p>This section would display recent orders, new registrations, product updates, etc.</p>
    <p>As an admin, you can view everything sellers sell and buyers buy.</p>
    <ul>
        <li><a href="manage_users.php">Go to Manage Users</a></li>
        <li><a href="manage_products.php">Go to Manage Products</a></li>
        </ul>
</div>

<?php
require_once 'includes/admin_footer.php';
$conn->close();
?>