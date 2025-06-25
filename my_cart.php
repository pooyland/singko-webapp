<?php
// my_cart.php
// 1. ALWAYS START SESSION FIRST.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Include database config. Ensure db_config.php has no output (no closingtag or stray spaces/newlines).
require_once 'db_config.php'; // This should now provide $conn as a MySQLi object

// Check if $conn is a valid MySQLi object immediately after inclusion
if (!$conn instanceof mysqli) {
    error_log("My Cart Page: Database connection (\$conn) is not a MySQLi object. Check db_config.php.");
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
$cart_items = [];
$cart_total = 0;
$message = '';
$message_type = '';

// Handle cart updates (POST requests for update/remove)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_quantity'])) {
        $cart_item_id = filter_var($_POST['cart_item_id'], FILTER_VALIDATE_INT);
        $new_quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);

        if ($cart_item_id === false || $cart_item_id <= 0 || $new_quantity === false || $new_quantity < 0) {
            $_SESSION['message'] = "Invalid cart item ID or quantity.";
            $_SESSION['message_type'] = "danger";
        } else {
            // Start transaction for atomicity
            $conn->begin_transaction();
            try {
                // First, get current product stock and name with a lock
                $stmt_stock = $conn->prepare("SELECT p.stock_quantity, p.name FROM cart_items ci JOIN products p ON ci.product_id = p.product_id WHERE ci.cart_item_id = ? AND ci.user_id = ? FOR UPDATE");
                if ($stmt_stock === false) {
                    error_log("My Cart (POST): MySQLi prepare failed for stock check: " . $conn->error);
                    throw new Exception("Failed to prepare stock check query.");
                }
                $stmt_stock->bind_param("ii", $cart_item_id, $user_id);
                $stmt_stock->execute();
                $result_stock = $stmt_stock->get_result();
                $product_data = $result_stock->fetch_assoc();
                $stmt_stock->close();

                if ($product_data) {
                    $available_stock = $product_data['stock_quantity'];
                    $product_name = htmlspecialchars($product_data['name']);

                    if ($new_quantity == 0) {
                        // Remove item if quantity is 0
                        $stmt_delete = $conn->prepare("DELETE FROM cart_items WHERE cart_item_id = ? AND user_id = ?");
                        if ($stmt_delete === false) {
                            error_log("My Cart (POST): MySQLi prepare failed for delete item: " . $conn->error);
                            throw new Exception("Failed to prepare delete query.");
                        }
                        $stmt_delete->bind_param("ii", $cart_item_id, $user_id);
                        if ($stmt_delete->execute()) {
                            $_SESSION['message'] = $product_name . " removed from cart.";
                            $_SESSION['message_type'] = "success";
                        } else {
                            $_SESSION['message'] = "Error removing " . $product_name . " from cart.";
                            $_SESSION['message_type'] = "danger";
                        }
                        $stmt_delete->close();
                    } elseif ($new_quantity > $available_stock) {
                        $_SESSION['message'] = "Not enough stock for " . $product_name . "! Only " . htmlspecialchars($available_stock) . " available.";
                        $_SESSION['message_type'] = "warning";
                    } else {
                        // Update quantity
                        $stmt_update = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ? AND user_id = ?");
                        if ($stmt_update === false) {
                            error_log("My Cart (POST): MySQLi prepare failed for update quantity: " . $conn->error);
                            throw new Exception("Failed to prepare update quantity query.");
                        }
                        $stmt_update->bind_param("iii", $new_quantity, $cart_item_id, $user_id);
                        if ($stmt_update->execute()) {
                            $_SESSION['message'] = $product_name . " quantity updated to " . htmlspecialchars($new_quantity) . ".";
                            $_SESSION['message_type'] = "success";
                        } else {
                            $_SESSION['message'] = "Error updating " . $product_name . " quantity.";
                            $_SESSION['message_type'] = "danger";
                        }
                        $stmt_update->close();
                    }
                } else {
                    $_SESSION['message'] = "Product not found in cart or you don't have permission.";
                    $_SESSION['message_type'] = "danger";
                }
                $conn->commit(); // Commit transaction on success
            } catch (Exception $e) {
                $conn->rollback(); // Rollback on error
                error_log("My Cart (POST): Transaction failed: " . $e->getMessage());
                $_SESSION['message'] = "Cart update failed: " . $e->getMessage();
                $_SESSION['message_type'] = "danger";
            }
        }
    } elseif (isset($_POST['remove_item'])) {
        $cart_item_id = filter_var($_POST['cart_item_id'], FILTER_VALIDATE_INT);
        if ($cart_item_id === false || $cart_item_id <= 0) {
            $_SESSION['message'] = "Invalid cart item ID.";
            $_SESSION['message_type'] = "danger";
        } else {
            try {
                $stmt_delete = $conn->prepare("DELETE FROM cart_items WHERE cart_item_id = ? AND user_id = ?");
                if ($stmt_delete === false) {
                    error_log("My Cart (POST): MySQLi prepare failed for remove item: " . $conn->error);
                    throw new Exception("Failed to prepare remove item query.");
                }
                $stmt_delete->bind_param("ii", $cart_item_id, $user_id);
                if ($stmt_delete->execute()) {
                    $_SESSION['message'] = "Item removed from cart.";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Error removing item from cart.";
                    $_SESSION['message_type'] = "danger";
                }
                $stmt_delete->close();
            } catch (Exception $e) {
                error_log("My Cart (POST): Error removing item: " . $e->getMessage());
                $_SESSION['message'] = "Error removing item: " . $e->getMessage();
                $_SESSION['message_type'] = "danger";
            }
        }
    }
    // Redirect after POST to prevent form re-submission
    header("Location: my_cart.php");
    exit;
}

// Check for messages stored in session and then clear them
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']); // Clear the message after displaying
    unset($_SESSION['message_type']);
}

// Fetch Cart Items for display on page (GET request)
$sql_cart = "SELECT
                ci.cart_item_id,
                ci.product_id,
                ci.quantity,
                p.name AS product_name,
                p.price AS product_price,
                p.image_url,
                p.stock_quantity AS available_stock
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.product_id
            WHERE ci.user_id = ?";
try {
    $stmt_cart = $conn->prepare($sql_cart);
    if ($stmt_cart === false) {
        error_log("My Cart (Display): MySQLi prepare failed for cart items: " . $conn->error);
        throw new Exception("Failed to prepare cart items query.");
    }
    $stmt_cart->bind_param("i", $user_id);
    $stmt_cart->execute();
    $result_cart = $stmt_cart->get_result(); // MySQLi get_result

    if ($result_cart->num_rows > 0) {
        while ($row = $result_cart->fetch_assoc()) { // MySQLi fetch_assoc
            $cart_items[] = $row;
            $cart_total += ($row['quantity'] * $row['product_price']);
        }
    }
    $stmt_cart->close(); // Close statement

} catch (Exception $e) {
    error_log("My Cart (Display): Database error fetching cart items (MySQLi): " . $e->getMessage());
    $message = "Error fetching cart items. Please try again later.";
    $message_type = "danger";
}

// Include the header file AFTER all PHP logic and potential redirects/messages
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
                        <i class="menu-icon tf-icons bx bx-collection"></i> <div data-i18n="My Products">My Products</div> </a>
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
                        <i class='menu-icon tf-icons bx bx-bar-chart-alt-2'></i> <div data-i18n="Sales Report">Sales Report</div>
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
                    <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">My Account /</span> My Cart</h4>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <h5 class="card-header">Items in Your Cart</h5>
                        <div class="card-body">
                            <div class="table-responsive text-nowrap">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Subtotal</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-border-bottom-0">
                                        <?php if (!empty($cart_items)): ?>
                                            <?php foreach ($cart_items as $item): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <img src="<?php echo htmlspecialchars($item['image_url'] ?? 'https://via.placeholder.com/40?text=No+Img'); ?>" alt="Product Image" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;" class="me-2">
                                                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                            <?php if ($item['quantity'] > $item['available_stock']): ?>
                                                                <span class="badge bg-label-danger ms-2">Low Stock! (<?php echo htmlspecialchars($item['available_stock']); ?> available)</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>PHP <?php echo number_format($item['product_price'], 2); ?></td>
                                                    <td>
                                                        <form action="my_cart.php" method="POST" class="d-flex align-items-center">
                                                            <input type="hidden" name="cart_item_id" value="<?php echo htmlspecialchars($item['cart_item_id']); ?>">
                                                            <input type="number" name="quantity" class="form-control me-2" value="<?php echo htmlspecialchars($item['quantity']); ?>" min="0" style="width: 80px;" onchange="this.form.submit()">
                                                            <input type="hidden" name="update_quantity" value="1"> <?php /* Added hidden input to trigger update logic */ ?>
                                                        </form>
                                                    </td>
                                                    <td>PHP <?php echo number_format($item['quantity'] * $item['product_price'], 2); ?></td>
                                                    <td>
                                                        <form action="my_cart.php" method="POST" style="display:inline;">
                                                            <input type="hidden" name="cart_item_id" value="<?php echo htmlspecialchars($item['cart_item_id']); ?>">
                                                            <button type="submit" name="remove_item" class="btn btn-danger btn-sm">Remove</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center">Your cart is empty. <a href="index.php">Start shopping!</a></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-end fw-bold">Total:</td>
                                            <td class="fw-bold">PHP <?php echo number_format($cart_total, 2); ?></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div class="d-flex justify-content-end mt-4">
                                <a href="checkout.php" class="btn btn-success btn-lg" <?php echo empty($cart_items) ? 'disabled' : ''; ?>>Proceed to Checkout</a>
                            </div>
                        </div>
                    </div>
                </div>
                <footer class="content-footer footer bg-footer-theme">
                    <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                        <div class="mb-2 mb-md-0">
                            © <?php echo date('Y'); ?> Singko eCommerce. All rights reserved.
                        </div>
                    </div>
                </footer>
                <div class="content-backdrop fade"></div>
            </div>
            </div>
        </div>

    <div class="layout-overlay layout-menu-toggle"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="js/helpers.js"></script>
<script src="js/config.js"></script>
<script src="js/menu.js"></script>
<script src="js/main.js"></script>
<script async defer src="https://buttons.github.io/buttons.js"></script>

</body>
</html>
