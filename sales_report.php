<?php
// sales_report.php
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

$seller_id = $_SESSION['user_id']; // Get the logged-in user's ID

$total_sales_revenue = 0;
$total_products_sold = 0;
$total_orders_received = 0; // This was 'Total Orders' in the dashboard image
$total_customers = 0; // New card from the example dashboard image

$recent_sales = [];
$top_selling_products = [];
$sales_overview_data = []; // For the chart

// --- DATABASE QUERIES ---

// Fetch Total Sales Revenue, Total Products Sold, and Total Orders Received for seller
$sql_summary = "SELECT 
                    SUM(oi.quantity * p.price) AS total_revenue, 
                    SUM(oi.quantity) AS total_products_sold,
                    COUNT(DISTINCT o.order_id) AS total_orders_received
                FROM order_items oi
                JOIN products p ON oi.product_id = p.product_id
                JOIN orders o ON oi.order_id = o.order_id
                WHERE p.seller_id = ?";
$stmt_summary = $conn->prepare($sql_summary);
if ($stmt_summary === false) {
    die('Prepare failed for summary: ' . $conn->error);
}
$stmt_summary->bind_param("i", $seller_id);
$stmt_summary->execute();
$result_summary = $stmt_summary->get_result();
if ($result_summary->num_rows > 0) {
    $summary_data = $result_summary->fetch_assoc();
    $total_sales_revenue = $summary_data['total_revenue'] ?? 0;
    $total_products_sold = $summary_data['total_products_sold'] ?? 0;
    $total_orders_received = $summary_data['total_orders_received'] ?? 0;
}
$stmt_summary->close();

// Fetch Total Customers (buyers who purchased from this seller)
// This query counts distinct users who bought products from the seller.
$sql_customers = "SELECT COUNT(DISTINCT o.user_id) AS total_customers
                  FROM orders o
                  JOIN order_items oi ON o.order_id = oi.order_id
                  JOIN products p ON oi.product_id = p.product_id
                  WHERE p.seller_id = ?";
$stmt_customers = $conn->prepare($sql_customers);
if ($stmt_customers === false) {
    die('Prepare failed for customers: ' . $conn->error);
}
$stmt_customers->bind_param("i", $seller_id);
$stmt_customers->execute();
$result_customers = $stmt_customers->get_result();
if ($result_customers->num_rows > 0) {
    $customer_data = $result_customers->fetch_assoc();
    $total_customers = $customer_data['total_customers'] ?? 0;
}
$stmt_customers->close();

// Fetch Recent Sales Transactions (similar to previous "Recent Orders")
// This pulls the last 10 completed orders for this seller's products.
$sql_recent_sales = "SELECT 
                        o.order_id,
                        o.order_date,
                        o.status, 
                        SUM(oi.quantity * p.price) AS order_total, 
                        GROUP_CONCAT(p.name SEPARATOR ', ') AS products_in_order
                      FROM orders o
                      JOIN order_items oi ON o.order_id = oi.order_id
                      JOIN products p ON oi.product_id = p.product_id
                      WHERE p.seller_id = ? AND o.status = 'completed'
                      GROUP BY o.order_id, o.order_date, o.status
                      ORDER BY o.order_date DESC
                      LIMIT 10";

$stmt_recent = $conn->prepare($sql_recent_sales);
if ($stmt_recent === false) {
    die('Prepare failed for recent sales: ' . $conn->error);
}
$stmt_recent->bind_param("i", $seller_id);
$stmt_recent->execute();
$result_recent = $stmt_recent->get_result();
if ($result_recent->num_rows > 0) {
    while ($row = $result_recent->fetch_assoc()) {
        $recent_sales[] = $row;
    }
}
$stmt_recent->close();

// Fetch Top Selling Products
// This fetches products sold by the seller, ordered by total quantity sold.
$sql_top_selling = "SELECT 
                        p.product_id, 
                        p.name, 
                        p.price, 
                        p.image_url,
                        SUM(oi.quantity) AS total_quantity_sold
                    FROM products p
                    JOIN order_items oi ON p.product_id = oi.product_id
                    WHERE p.seller_id = ?
                    GROUP BY p.product_id, p.name, p.price, p.image_url
                    ORDER BY total_quantity_sold DESC
                    LIMIT 5"; // Limit to top 5 products

$stmt_top_selling = $conn->prepare($sql_top_selling);
if ($stmt_top_selling === false) {
    die('Prepare failed for top selling: ' . $conn->error);
}
$stmt_top_selling->bind_param("i", $seller_id);
$stmt_top_selling->execute();
$result_top_selling = $stmt_top_selling->get_result();
if ($result_top_selling->num_rows > 0) {
    while ($row = $result_top_selling->fetch_assoc()) {
        $top_selling_products[] = $row;
    }
}
$stmt_top_selling->close();


// --- Placeholder for Sales Overview Chart Data (Weekly/Monthly/Yearly) ---
// This part is complex and highly depends on how you want to aggregate time-series data.
// For a fully functional chart, you'd query your database to get sales data grouped by day/week/month.
// Example: Sales data for the last 7 days. This is illustrative.
// You would need to refine these queries significantly based on your actual data and charting needs.

// Sample Data for "Sales Overview" chart (replace with actual database queries)
// For simplicity, I'm using static data here. In a real application, you'd fetch this.
// For a weekly view, you'd need sales data for each day of the week.
// For a monthly view, sales data for each month.
// For a yearly view, sales data for each year.

// For now, let's just make sure the structure is there.
// If you want a functional chart, you'll need to write SQL queries to get sum of sales per day/month/year.
// Example (weekly):
// SELECT DATE(o.order_date) as sale_date, SUM(oi.quantity * p.price) as daily_sales
// FROM orders o JOIN order_items oi ON o.order_id = oi.order_id JOIN products p ON oi.product_id = p.product_id
// WHERE p.seller_id = ? AND o.order_date >= CURDATE() - INTERVAL 7 DAY AND o.status = 'completed'
// GROUP BY DATE(o.order_date) ORDER BY sale_date ASC;

// Sample static data for the chart to ensure it renders. Replace with dynamic data.
$sales_overview_chart_data = [
    'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
    'series_data' => [
        'weekly' => [3100, 4000, 5800, 3800, 5500, 7100, 6500], // Example sales data for weekly
        'monthly' => [15000, 22000, 18000, 25000, 20000, 28000, 24000, 30000, 26000, 32000, 29000, 35000], // Example for 12 months
        'yearly' => [150000, 180000, 200000, 250000, 230000], // Example for last 5 years
    ]
];
// You'd pass these into JavaScript for charting.

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
                        <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Analytics /</span> Sales Report</h4>

                        <div class="row">
                            <div class="col-lg-3 col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-dollar-circle bx-md text-success"></i>
                                            </div>
                                            </div>
                                        <span class="fw-semibold d-block mb-1">Total Sales</span>
                                        <h3 class="card-title mb-2">PHP <?php echo number_format($total_sales_revenue, 2); ?></h3>
                                        </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-cart bx-md text-info"></i>
                                            </div>
                                        </div>
                                        <span class="fw-semibold d-block mb-1">Total Orders</span>
                                        <h3 class="card-title mb-2"><?php echo number_format($total_orders_received); ?></h3>
                                        </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-package bx-md text-warning"></i>
                                            </div>
                                        </div>
                                        <span class="fw-semibold d-block mb-1">Total Products Sold</span>
                                        <h3 class="card-title mb-2"><?php echo number_format($total_products_sold); ?></h3>
                                        </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-user-check bx-md text-primary"></i>
                                            </div>
                                        </div>
                                        <span class="fw-semibold d-block mb-1">Total Customers</span>
                                        <h3 class="card-title mb-2"><?php echo number_format($total_customers); ?></h3>
                                        </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-8 mb-4 order-0">
                                <div class="card">
                                    <div class="d-flex align-items-end row">
                                        <div class="col-md-12">
                                            <div class="card-header d-flex align-items-center justify-content-between">
                                                <h5 class="card-title m-0 me-2">Sales Overview</h5>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="salesOverviewDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                        Weekly
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="salesOverviewDropdown">
                                                        <a class="dropdown-item" href="#" data-period="weekly">Weekly</a>
                                                        <a class="dropdown-item" href="#" data-period="monthly">Monthly</a>
                                                        <a class="dropdown-item" href="#" data-period="yearly">Yearly</a>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div id="salesOverviewChart"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-4 order-1">
                                <div class="card h-100">
                                    <div class="card-header d-flex align-items-center justify-content-between">
                                        <h5 class="card-title m-0 me-2">Top Selling Products</h5>
                                        <a href="my_products.php" class="btn btn-sm btn-outline-primary">View All</a>
                                    </div>
                                    <div class="card-body">
                                        <ul class="p-0 m-0">
                                            <?php if (!empty($top_selling_products)): ?>
                                                <?php foreach ($top_selling_products as $product): ?>
                                                    <li class="d-flex mb-4 pb-1">
                                                        <div class="avatar flex-shrink-0 me-3">
                                                            <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'https://placehold.co/40x40/cccccc/000000?text=No+Img'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="rounded" />
                                                        </div>
                                                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                            <div class="me-2">
                                                                <small class="text-muted d-block mb-1"><?php echo htmlspecialchars($product['name']); ?></small>
                                                                <h6 class="mb-0">PHP <?php echo number_format($product['price'], 2); ?></h6>
                                                            </div>
                                                            <div class="user-progress d-flex align-items-center gap-1">
                                                                <h6 class="mb-0"><?php echo number_format($product['total_quantity_sold']); ?> sold</h6>
                                                            </div>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <li class="text-center">No top-selling products found yet.</li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            </div>

                        <div class="card mt-4">
                            <h5 class="card-header">Recent Sales Transactions (from your products)</h5>
                            <div class="card-body">
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Order ID</th>
                                                <th>Date</th>
                                                <th>Products Included</th>
                                                <th>Total Sale</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-border-bottom-0">
                                            <?php if (!empty($recent_sales)): ?>
                                                <?php foreach ($recent_sales as $sale): ?>
                                                    <tr>
                                                        <td><i class="bx bx-hash bx-sm text-primary me-2"></i> <strong><?php echo htmlspecialchars($sale['order_id']); ?></strong></td>
                                                        <td><?php echo date('M d, Y', strtotime($sale['order_date'])); ?></td>
                                                        <td><?php echo htmlspecialchars($sale['products_in_order']); ?></td>
                                                        <td>PHP <?php echo number_format($sale['order_total'], 2); ?></td>
                                                        <td><span class="badge bg-label-success me-1"><?php echo htmlspecialchars(ucfirst($sale['status'])); ?></span></td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                                    <i class="bx bx-dots-vertical-rounded"></i>
                                                                </button>
                                                                <div class="dropdown-menu">
                                                                    <a class="dropdown-item" href="order_details.php?id=<?php echo htmlspecialchars($sale['order_id']); ?>"><i class="bx bx-edit-alt me-1"></i> View Details</a>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">No recent sales found for your products.</td>
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

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script> 

<script src="js/helpers.js"></script>
<script src="js/config.js"></script>
<script src="js/menu.js"></script>
<script src="js/main.js"></script>
<script async defer src="https://buttons.github.io/buttons.js"></script>

<script>
    // JavaScript for Sales Overview Chart (ApexCharts example)
    document.addEventListener('DOMContentLoaded', function() {
        const salesOverviewChartEl = document.querySelector('#salesOverviewChart');
        if (salesOverviewChartEl) {
            const chartData = <?php echo json_encode($sales_overview_chart_data); ?>;
            let currentPeriod = 'weekly'; // Default period

            const options = {
                chart: {
                    height: 350,
                    type: 'line',
                    toolbar: {
                        show: false
                    }
                },
                series: [{
                    name: 'Sales',
                    data: chartData.series_data[currentPeriod]
                }],
                xaxis: {
                    categories: chartData.labels, // This will need to be dynamic based on period
                },
                tooltip: {
                    x: {
                        show: true
                    },
                    y: {
                        formatter: function(value) {
                            return "PHP " + value.toLocaleString();
                        }
                    }
                }
            };

            const chart = new ApexCharts(salesOverviewChartEl, options);
            chart.render();

            // Handle dropdown period change
            const salesOverviewDropdown = document.getElementById('salesOverviewDropdown');
            salesOverviewDropdown.addEventListener('click', function(event) {
                if (event.target.classList.contains('dropdown-item')) {
                    event.preventDefault();
                    currentPeriod = event.target.dataset.period;
                    salesOverviewDropdown.textContent = event.target.textContent; // Update button text

                    // Update chart data and categories based on the selected period
                    // In a real application, you'd fetch data for this period from the server
                    // For this example, we're using pre-defined static data
                    let newLabels = [];
                    if (currentPeriod === 'weekly') {
                        newLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    } else if (currentPeriod === 'monthly') {
                        newLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    } else if (currentPeriod === 'yearly') {
                        // Assuming last 5 years for example
                        const currentYear = new Date().getFullYear();
                        for (let i = 4; i >= 0; i--) {
                            newLabels.push(currentYear - i);
                        }
                    }

                    chart.updateOptions({
                        series: [{
                            data: chartData.series_data[currentPeriod]
                        }],
                        xaxis: {
                            categories: newLabels
                        }
                    });
                }
            });
        }
    });
</script>

</body>
</html>