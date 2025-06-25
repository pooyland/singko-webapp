<?php
// checkout.php
// 1. ALWAYS START SESSION FIRST.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Include database config. Ensure db_config.php has no output (no closing  tag or stray spaces/newlines).
require_once 'db_config.php'; // This should now provide $conn as a MySQLi object

// Check if $conn is a valid MySQLi object immediately after inclusion
if (!$conn instanceof mysqli) {
    error_log("Checkout Page: Database connection (\$conn) is not a MySQLi object. Check db_config.php.");
    $_SESSION['message'] = "Critical database error: Connection not established correctly. Please try again later.";
    $_SESSION['message_type'] = "danger";
    header('Location: login.php'); // Redirect to login or an error page
    exit;
}

// 3. User Login Check (MUST happen BEFORE any HTML output or other includes that output HTML)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Please login to proceed to checkout.";
    $_SESSION['message_type'] = "danger";
    header('Location: login.php');
    exit; // Crucial to exit after header redirect
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Guest'; // Used for display, not critical for backend logic
$user_info = [];
$cart_items = [];
$cart_total = 0;
$message = '';
$message_type = '';

// 4. Handle Place Order (POST request) - This block MUST be BEFORE any HTML output or header includes
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_order'])) {
    // Re-fetch cart items and total here for safety and stock validation
    $re_fetched_cart_items = [];
    $re_fetched_cart_total = 0;

    // MySQLi: Start a transaction for atomicity for the entire order placement process
    $conn->begin_transaction();
    try {
        $sql_re_fetch_cart = "SELECT
                                ci.cart_item_id,
                                ci.product_id,
                                ci.quantity,
                                p.name AS product_name,
                                p.price AS product_price,
                                p.stock_quantity AS available_stock,
                                p.seller_id
                            FROM cart_items ci
                            JOIN products p ON ci.product_id = p.product_id
                            WHERE ci.user_id = ? FOR UPDATE"; // Added FOR UPDATE for pessimistic locking

        $stmt_re_fetch_cart = $conn->prepare($sql_re_fetch_cart);
        if ($stmt_re_fetch_cart === false) {
            error_log("Checkout (POST): MySQLi prepare failed for cart re-fetch: " . $conn->error);
            throw new Exception("An unexpected error occurred during cart validation preparation.");
        }
        $stmt_re_fetch_cart->bind_param("i", $user_id);
        $stmt_re_fetch_cart->execute();
        $result_re_fetch_cart = $stmt_re_fetch_cart->get_result(); // MySQLi get_result
        $re_fetched_cart_items_raw = $result_re_fetch_cart->fetch_all(MYSQLI_ASSOC); // Fetch all results
        $stmt_re_fetch_cart->close(); // Close statement

        if (!empty($re_fetched_cart_items_raw)) {
            foreach ($re_fetched_cart_items_raw as $row) {
                if ($row['quantity'] > $row['available_stock']) {
                    throw new Exception("Not enough stock for " . htmlspecialchars($row['product_name']) . ". Available: " . htmlspecialchars($row['available_stock']) . ", Requested: " . htmlspecialchars($row['quantity']) . ". Please adjust your cart.");
                }
                $re_fetched_cart_items[] = $row;
                $re_fetched_cart_total += ($row['quantity'] * $row['product_price']);
            }
        } else {
            throw new Exception("Your cart is empty. Cannot place an order.");
        }

        // 1. Create a new order in the 'orders' table (MySQLi Prepared Statement)
        $order_date = date('Y-m-d H:i:s');
        $order_status = 'Pending';
        $stmt_insert_order = $conn->prepare("INSERT INTO orders (user_id, order_date, order_status, total_amount) VALUES (?, ?, ?, ?)");
        if ($stmt_insert_order === false) {
            error_log("Checkout (POST): MySQLi prepare failed for order insertion: " . $conn->error);
            throw new Exception("Failed to prepare order insertion.");
        }
        $stmt_insert_order->bind_param("isss", $user_id, $order_date, $order_status, $re_fetched_cart_total); // Adjust type string as per your schema
        if (!$stmt_insert_order->execute()) {
            error_log("Checkout (POST): Error inserting order (MySQLi): " . $stmt_insert_order->error);
            throw new Exception("Error inserting order into database.");
        }
        $order_id = $conn->insert_id; // MySQLi: last inserted ID
        $stmt_insert_order->close(); // Close statement

        // 2. Move items from cart to 'order_items' and deduct stock
        foreach ($re_fetched_cart_items as $item) {
            $current_stock = $item['available_stock'];

            if ($item['quantity'] > $current_stock) {
                throw new Exception("Not enough stock for " . htmlspecialchars($item['product_name']) . " after initial check. Available: " . htmlspecialchars($current_stock) . ", Requested: " . htmlspecialchars($item['quantity']));
            }

            // Insert into order_items (MySQLi Prepared Statement)
            $stmt_insert_order_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
            if ($stmt_insert_order_item === false) {
                error_log("Checkout (POST): MySQLi prepare failed for order item insertion: " . $conn->error);
                throw new Exception("Failed to prepare order item insertion.");
            }
            $stmt_insert_order_item->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['product_price']);
            if (!$stmt_insert_order_item->execute()) {
                error_log("Checkout (POST): Error inserting order item for " . htmlspecialchars($item['product_name']) . " (MySQLi): " . $stmt_insert_order_item->error);
                throw new Exception("Error inserting order item for " . htmlspecialchars($item['product_name']) . ".");
            }
            $stmt_insert_order_item->close(); // Close statement

            // Deduct stock from products table (MySQLi Prepared Statement)
            $new_stock = $current_stock - $item['quantity'];
            $stmt_deduct_stock = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE product_id = ?");
            if ($stmt_deduct_stock === false) {
                error_log("Checkout (POST): MySQLi prepare failed for stock deduction: " . $conn->error);
                throw new Exception("Failed to prepare stock deduction.");
            }
            $stmt_deduct_stock->bind_param("ii", $new_stock, $item['product_id']);
            if (!$stmt_deduct_stock->execute()) {
                error_log("Checkout (POST): Error deducting stock for " . htmlspecialchars($item['product_name']) . " (MySQLi): " . $stmt_deduct_stock->error);
                throw new Exception("Error deducting stock for " . htmlspecialchars($item['product_name']) . ".");
            }
            $stmt_deduct_stock->close(); // Close statement
        }

        // 3. Clear the user's cart (MySQLi Prepared Statement)
        $stmt_clear_cart = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
        if ($stmt_clear_cart === false) {
            error_log("Checkout (POST): MySQLi prepare failed for clearing cart: " . $conn->error);
            throw new Exception("Failed to prepare cart clearing.");
        }
        $stmt_clear_cart->bind_param("i", $user_id);
        if (!$stmt_clear_cart->execute()) {
            error_log("Checkout (POST): Error clearing cart (MySQLi): " . $stmt_clear_cart->error);
            throw new Exception("Error clearing cart.");
        }
        $stmt_clear_cart->close(); // Close statement

        // If all successful, commit the transaction
        $conn->commit(); // MySQLi: commit()
        $_SESSION['message'] = "Order placed successfully! Your Order ID is: " . $order_id;
        $_SESSION['message_type'] = "success";
        header('Location: my_orders.php');
        exit; // Always exit after a header redirect!

    } catch (Exception $e) {
        $conn->rollback(); // MySQLi: rollback()
        $_SESSION['message'] = "Order placement failed: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
        header('Location: my_cart.php'); // Redirect back to cart if order fails
        exit;
    }
}


// 5. Fetch user's full details for display (MySQLi Prepared Statement)
$sql_user_details = "SELECT full_name, address, contact_number, email FROM users WHERE user_id = ?";
try {
    $stmt_user_details = $conn->prepare($sql_user_details);
    if ($stmt_user_details === false) {
        error_log("Checkout (Display): MySQLi prepare failed for user details: " . $conn->error);
        die('Failed to prepare user details query.'); // Critical error, stop execution
    }
    $stmt_user_details->bind_param("i", $user_id);
    $stmt_user_details->execute();
    $result_user_details = $stmt_user_details->get_result();
    
    if ($user_info_temp = $result_user_details->fetch_assoc()) {
        $user_info = $user_info_temp;
    } else {
        $message = "User details not found. Please log in again.";
        $message_type = "danger";
    }
    $stmt_user_details->close(); // Close statement
} catch (Exception $e) { // Catch generic Exception
    error_log("Checkout (Display): Database error fetching user details (MySQLi): " . $e->getMessage());
    $message = "Error fetching user details. Please try again later.";
    $message_type = "danger";
}


// 6. Fetch Cart Items for display on page (MySQLi Prepared Statement)
$sql_cart = "SELECT
                                ci.cart_item_id,
                                ci.product_id,
                                ci.quantity,
                                p.name AS product_name,
                                p.price AS product_price,
                                p.image_url,
                                p.stock_quantity AS available_stock,
                                p.seller_id
                            FROM cart_items ci
                            JOIN products p ON ci.product_id = p.product_id
                            WHERE ci.user_id = ?";
try {
    $stmt_cart = $conn->prepare($sql_cart);
    if ($stmt_cart === false) {
        error_log("Checkout (Display): MySQLi prepare failed for cart items: " . $conn->error);
        die('Failed to prepare cart items query.'); // Critical error, stop execution
    }
    $stmt_cart->bind_param("i", $user_id);
    $stmt_cart->execute();
    $result_cart = $stmt_cart->get_result();
    
    $cart_items_temp = [];
    if ($result_cart->num_rows > 0) {
        while ($row = $result_cart->fetch_assoc()) {
            $cart_items_temp[] = $row;
        }
    }
    $stmt_cart->close(); // Close statement

    if (!empty($cart_items_temp)) {
        foreach ($cart_items_temp as $row) {
            if ($row['quantity'] > $row['available_stock']) {
                $message = "One or more items in your cart exceed available stock. Please adjust quantities.";
                $message_type = "warning";
            }
            $cart_items[] = $row;
            $cart_total += ($row['quantity'] * $row['product_price']);
        }
    } else {
        if (!isset($_SESSION['message'])) { // Only redirect if no other message is pending
            $_SESSION['message'] = "Your cart is empty. Please add items before checking out.";
            $_SESSION['message_type'] = "info";
            header('Location: my_cart.php');
            exit;
        }
    }
} catch (Exception $e) { // Catch generic Exception
    error_log("Checkout (Display): Database error fetching cart items (MySQLi): " . $e->getMessage());
    $message = "Error fetching cart items. Please try again later.";
    $message_type = "danger";
}


// 7. Check for messages stored in session and then clear them
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// 8. NOW, include the header which contains the initial HTML structure.
// This MUST be AFTER ALL PHP logic that might redirect.
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
                                                        <small class="text-muted">Buyer</small>
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
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Checkout /</span> Confirm Order</h4>

                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card mb-4">
                                    <h5 class="card-header">Order Summary</h5>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>Product</th>
                                                        <th>Price</th>
                                                        <th>Quantity</th>
                                                        <th>Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($cart_items)): ?>
                                                        <?php foreach ($cart_items as $item): ?>
                                                            <tr>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <img src="<?php echo htmlspecialchars($item['image_url'] ?? 'https://via.placeholder.com/40?text=No+Img'); ?>" alt="Product Image" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;" class="me-2">
                                                                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                                    </div>
                                                                </td>
                                                                <td>PHP <?php echo number_format($item['product_price'], 2); ?></td>
                                                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                                                <td>PHP <?php echo number_format($item['quantity'] * $item['product_price'], 2); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="4" class="text-center">No items in your cart to checkout.</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="3" class="text-end fw-bold">Total:</td>
                                                        <td class="fw-bold">PHP <?php echo number_format($cart_total, 2); ?></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="card mb-4">
                                    <h5 class="card-header">Shipping & Payment</h5>
                                    <div class="card-body">
                                        <h6>Shipping Address:</h6>
                                        <p>
                                            <strong><?php echo htmlspecialchars($user_info['full_name'] ?? 'N/A'); ?></strong><br>
                                            <?php echo htmlspecialchars($user_info['address'] ?? 'N/A'); ?><br>
                                            Phone: <?php echo htmlspecialchars($user_info['contact_number'] ?? 'N/A'); ?><br>
                                            Email: <?php echo htmlspecialchars($user_info['email'] ?? 'N/A'); ?>
                                        </p>
                                        <hr>
                                        <h6>Payment Method:</h6>
                                        <p>Cash on Delivery (COD)</p>
                                        <small class="text-muted">Currently, only Cash on Delivery is supported. Contact support for other options.</small>
                                        <hr>
                                        <form action="checkout.php" method="POST">
                                            <input type="hidden" name="place_order" value="1">
                                            <button type="submit" class="btn btn-success w-100 btn-lg" <?php echo empty($cart_items) ? 'disabled' : ''; ?>
                                                onclick="return confirm('Are you sure you want to place this order?');">
                                                Place Order (PHP <?php echo number_format($cart_total, 2); ?>)
                                            </button>
                                        </form>
                                        <div class="mt-2 text-center">
                                            <a href="my_cart.php" class="btn btn-outline-secondary w-100">Back to Cart</a>
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
    </main>
    <footer class="bg-dark text-white py-4 mt-5">
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
</ht