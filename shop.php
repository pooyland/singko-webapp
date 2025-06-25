<?php
// shop.php

// Start session at the very beginning, before any includes or output.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include your database configuration. This should now define the $conn MySQLi object.
require_once 'db_config.php'; // Ensure this path is correct and it initializes $conn as a MySQLi object

// Check if $conn is a valid MySQLi object immediately after inclusion
if (!$conn instanceof mysqli) {
    error_log("Shop Page: Database connection (\$conn) is not a MySQLi object. Check db_config.php.");
    $_SESSION['message'] = "Critical database error: Connection not established correctly. Please try again later.";
    $_SESSION['message_type'] = "danger";
    header('Location: login.php'); // Redirect to login or an error page
    exit;
}

$message = ''; // Initialize message variable for session messages
$message_type = ''; // Initialize message type for session messages

// --- Handle Add to Cart (MOVED TO TOP FOR HEADER REDIRECTS) ---
// This block will remain for compatibility, though the button on this page will now be "View Product"
// The actual add to cart logic will be called from product_detail.php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['message'] = 'Please login to add items to your cart.'; // No div here, handled by Bootstrap alert
        $_SESSION['message_type'] = 'danger';
    } else {
        $user_id = $_SESSION['user_id'];
        $product_id_to_add = filter_var($_POST['product_id'], FILTER_VALIDATE_INT);
        $quantity_to_add = filter_var($_POST['quantity'], FILTER_VALIDATE_INT) ?: 1; // Get quantity from POST or default to 1

        if ($product_id_to_add === false || $product_id_to_add <= 0 || $quantity_to_add <= 0) {
            $_SESSION['message'] = 'Invalid product or quantity selected.';
            $_SESSION['message_type'] = 'danger';
        } else {
            // Using MySQLi transactions for atomicity
            $conn->begin_transaction();
            try {
                // 1. Check if product already exists in user's cart in the database (MySQLi Prepared Statement)
                $stmt_check_cart = $conn->prepare("SELECT cart_item_id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
                if ($stmt_check_cart === false) {
                    error_log("Add to cart (shop.php): Error preparing cart check statement (MySQLi): " . $conn->error);
                    throw new Exception("Internal error during cart check preparation.");
                }
                $stmt_check_cart->bind_param("ii", $user_id, $product_id_to_add);
                $stmt_check_cart->execute();
                $result_check_cart = $stmt_check_cart->get_result();
                $cart_item = $result_check_cart->fetch_assoc();
                $stmt_check_cart->close();

                // Fetch product's stock quantity (MySQLi Prepared Statement)
                $stmt_stock = $conn->prepare("SELECT stock_quantity, name FROM products WHERE product_id = ?");
                if ($stmt_stock === false) {
                    error_log("Add to cart (shop.php): Error preparing stock check statement (MySQLi): " . $conn->error);
                    throw new Exception("Internal error during stock check preparation.");
                }
                $stmt_stock->bind_param("i", $product_id_to_add);
                $stmt_stock->execute();
                $result_stock = $stmt_stock->get_result();
                $product_data = $result_stock->fetch_assoc();
                $product_stock = $product_data ? $product_data['stock_quantity'] : 0;
                $product_name = $product_data ? htmlspecialchars($product_data['name']) : 'Product';
                $stmt_stock->close();

                if ($product_stock < $quantity_to_add) {
                    throw new Exception("Insufficient stock for " . $product_name . ". Available: " . htmlspecialchars($product_stock) . ".");
                }

                if ($cart_item) {
                    // Product already in cart, update quantity (MySQLi Prepared Statement)
                    $cart_item_id = $cart_item['cart_item_id'];
                    $current_quantity = $cart_item['quantity'];
                    $new_quantity = $current_quantity + $quantity_to_add;

                    if ($new_quantity <= $product_stock) {
                        $stmt_update = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
                        if ($stmt_update === false) {
                            error_log("Add to cart (shop.php): Error preparing cart update statement (MySQLi): " . $conn->error);
                            throw new Exception("Internal error during cart update preparation.");
                        }
                        $stmt_update->bind_param("ii", $new_quantity, $cart_item_id);
                        if ($stmt_update->execute()) {
                            $_SESSION['message'] = $product_name . ' quantity updated in cart!';
                            $_SESSION['message_type'] = 'success';
                        } else {
                            $_SESSION['message'] = 'Error updating ' . $product_name . ' quantity.';
                            $_SESSION['message_type'] = 'danger';
                        }
                        $stmt_update->close();
                    } else {
                        $_SESSION['message'] = 'Cannot add more ' . $product_name . ' to cart. Only ' . htmlspecialchars($product_stock) . ' available in total.';
                        $_SESSION['message_type'] = 'warning';
                    }
                } else {
                    // Product not in cart, insert new item (MySQLi Prepared Statement)
                    if ($quantity_to_add <= $product_stock && $product_stock > 0) {
                        $stmt_insert = $conn->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
                        if ($stmt_insert === false) {
                            error_log("Add to cart (shop.php): Error preparing cart insert statement (MySQLi): " . $conn->error);
                            throw new Exception("Internal error during cart insert preparation.");
                        }
                        $stmt_insert->bind_param("iii", $user_id, $product_id_to_add, $quantity_to_add);
                        if ($stmt_insert->execute()) {
                            $_SESSION['message'] = $product_name . ' added to cart!';
                            $_SESSION['message_type'] = 'success';
                        } else {
                            $_SESSION['message'] = 'Error adding ' . $product_name . ' to cart.';
                            $_SESSION['message_type'] = 'danger';
                        }
                        $stmt_insert->close();
                    } else {
                        $_SESSION['message'] = $product_name . ' is out of stock or requested quantity is too high.';
                        $_SESSION['message_type'] = 'danger';
                    }
                }
                $conn->commit(); // Commit transaction on success
            } catch (Exception $e) {
                $conn->rollback(); // Rollback transaction on error
                error_log("Add to cart (shop.php): Database error during cart operation (MySQLi): " . $e->getMessage());
                $_SESSION['message'] = 'A database error occurred during cart operation. Please try again later.';
                $_SESSION['message_type'] = 'danger';
            }
        }
    }
    // Redirect after POST to prevent form re-submission and display message
    $redirect_url = "shop.php";
    $query_params = [];
    if (isset($_GET['category'])) { // Check if category_filter was set before POST
        $query_params['category'] = urlencode($_GET['category']);
    }
    if (isset($_GET['search'])) { // Check if search_query was set before POST
        $query_params['search'] = urlencode($_GET['search']);
    }
    if (!empty($query_params)) {
        $redirect_url .= '?' . http_build_query($query_params);
    }

    header("Location: " . $redirect_url);
    exit();
}
// --- END Add to Cart ---


// Display any session messages AFTER POST handling and potential redirect
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Include the header file AFTER all PHP logic and potential redirects
include 'includes/header.php';

$products = [];
$category_filter = $_GET['category'] ?? null;
$search_query_param = $_GET['search'] ?? null; // Renamed to avoid conflict with $search_query in form input

// Build the SQL query for products based on filters
$sql_products = "SELECT p.product_id, p.name AS product_name, p.description, p.price, p.image_url, u.username as seller_name, c.name as category_name, p.stock_quantity
                 FROM products p
                 JOIN users u ON p.seller_id = u.user_id
                 JOIN categories c ON p.category_id = c.category_id
                 WHERE p.status = 'active'"; // Only show active products

$param_types = '';
$params = [];

if ($category_filter && $category_filter != '') {
    $sql_products .= " AND c.name = ?";
    $param_types .= 's';
    $params[] = $category_filter;
}

if ($search_query_param && $search_query_param != '') {
    $sql_products .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $param_types .= 'ss';
    $params[] = '%' . $search_query_param . '%';
    $params[] = '%' . $search_query_param . '%';
}

$sql_products .= " ORDER BY p.created_at DESC";

try {
    $stmt_products = $conn->prepare($sql_products);
    if ($stmt_products === false) {
        error_log("Shop Page (Display): Error preparing products query (MySQLi): " . $conn->error);
        throw new Exception("Failed to prepare product query for display.");
    }

    // Bind parameters only if there are any
    if (!empty($params)) {
        $stmt_products->bind_param($param_types, ...$params);
    }

    $stmt_products->execute();
    $result_products = $stmt_products->get_result();

    if ($result_products->num_rows > 0) {
        while ($row = $result_products->fetch_assoc()) {
            $products[] = $row;
        }
    }
    $stmt_products->close();

} catch (Exception $e) {
    error_log("Shop Page (Display): Database error fetching products (MySQLi): " . $e->getMessage());
    $message = 'Error loading products. Please try again later.';
    $message_type = 'danger';
}

// Fetch categories for the filter dropdown (MySQLi)
$categories_list = [];
try {
    $stmt_categories = $conn->query("SELECT category_id, name FROM categories ORDER BY name"); // Fetch category_id as well
    if ($stmt_categories === false) {
        error_log("Shop Page (Display): Error fetching categories (MySQLi): " . $conn->error);
        throw new Exception("Failed to fetch categories.");
    }
    while ($row = $stmt_categories->fetch_assoc()) {
        $categories_list[] = $row;
    }
    // No need to close statement here, $conn will be closed at the end of the script.
} catch (Exception $e) {
    error_log("Shop Page (Display): General error fetching categories (MySQLi): " . $e->getMessage());
}

$conn->close(); // Close the database connection at the end of the script execution.

?>

<div class="container my-5">
    <h1 class="text-center mb-5">Explore Our Products</h1>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-12">
            <!-- Search and Filter matching original layout -->
            <form class="d-flex justify-content-end" method="GET" action="shop.php">
                <input class="form-control me-2" type="search" name="search" placeholder="Search products..." aria-label="Search" value="<?php echo htmlspecialchars($search_query_param ?? ''); ?>">
                <select class="form-select w-auto me-2" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories_list as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['category_id']); ?>" <?php echo ($category_filter == $cat['category_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-primary" type="submit">Filter</button>
            </form>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php if (!empty($products)): ?>
            <?php foreach ($products as $product): ?>
                <div class="col">
                    <div class="card product-card h-100">
                        <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'https://placehold.co/400x300/cccccc/000000?text=No+Image'); ?>" class="card-img-top product-img-thumbnail" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title product-title"><?php echo htmlspecialchars($product['product_name']); ?></h5>
                            <p class="card-text product-seller text-muted small mb-1">Category: <?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></p>
                            <p class="card-text text-truncate"><?php echo htmlspecialchars($product['description']); ?></p>
                            <div class="mt-auto d-flex justify-content-between align-items-center">
                                <span class="product-price">PHP <?php echo number_format($product['price'], 2); ?></span>
                                <div>
                                    <?php if ($product['stock_quantity'] > 0): ?>
                                        <!-- Changed button to "View Product" -->
                                        <a href="product_detail.php?id=<?php echo htmlspecialchars($product['product_id']); ?>" class="btn btn-primary">View Product</a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary" disabled>Out of Stock</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info text-center" role="alert">
                    No products available in this category or matching your search.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Closes <main> tag (opened in header.php)
echo '</main>';
// Add any site-wide footer content here, or include a separate footer.php
?>
<footer class="bg-dark text-white py-4 mt-5">
    <div class="container text-center">
        <p>&copy; <?php echo date('Y'); ?> Singko eCommerce. All rights reserved.</p>
    </div>
</footer>

<!-- Bootstrap JS Bundle (placed at the end of body for performance) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<!-- Your custom JS files -->
<script src="js/helpers.js"></script>
<script src="js/config.js"></script>
<script src="js/menu.js"></script>
<script src="js/main.js"></script>
<script async defer src="https://buttons.github.io/buttons.js"></script>
</body>
</html>
