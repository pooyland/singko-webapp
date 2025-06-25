<?php
// index.php

// Start session at the very beginning, before any includes or output.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure your database connection is included.
// This file should now define the $conn object for MySQLi database connection.
require_once 'db_config.php'; // This correctly provides $conn (now MySQLi)

$message = ''; // For potential messages like "Added to cart"

// Include the header file that contains the top navigation bar and opens <main>
// IMPORTANT: Ensure header.php does NOT output anything before this point if you
// intend to use header() redirects in this file.
include 'includes/header.php';

// Fetch all products from the database for the "Featured Products" section
$products = [];
// MODIFIED SQL QUERY: Added p.stock_quantity to the SELECT list
// Using $conn for MySQLi queries
$sql_products = "SELECT p.product_id, p.name AS product_name, p.description, p.price, p.image_url, u.username as seller_name, p.stock_quantity
                 FROM products p
                 JOIN users u ON p.seller_id = u.user_id
                 WHERE p.status = 'active'
                 ORDER BY p.created_at DESC LIMIT 8"; // Fetching a few for featured section

try {
    // MySQLi: Use query() for simple SELECT statements
    $result_products = $conn->query($sql_products);
    if ($result_products && $result_products->num_rows > 0) { // MySQLi: num_rows
        while($row = $result_products->fetch_assoc()) { // MySQLi: fetch_assoc
            $products[] = $row;
        }
    }
} catch (mysqli_sql_exception $e) { // Catch MySQLi specific exception or generic Exception
    error_log("Error fetching products on index page (MySQLi): " . $e->getMessage());
    // Optionally set a user-friendly message
    $message = '<div class="alert alert-danger text-center">Error loading products. Please try again later.</div>';
}


// Example categories (adjust as needed, ideally from a database)
// Using your categories from the database images: Electronics, Apparel, Home Goods, Books
$categories = [
    ['name' => 'Electronics', 'icon' => 'bx-laptop', 'link' => 'shop.php?category=Electronics'],
    ['name' => 'Apparel', 'icon' => 'bx-t-shirt', 'link' => 'shop.php?category=Apparel'],
    ['name' => 'Home Goods', 'icon' => 'bx-home-alt', 'link' => 'shop.php?category=HomeGoods'],
    ['name' => 'Books', 'icon' => 'bx-book', 'link' => 'shop.php?category=Books']
];


// --- Handle Add to Cart (MODIFIED TO USE DATABASE `cart_items` table with MySQLi) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    // Check if the database connection was established before proceeding
    if (!$conn) {
        error_log("Add to cart: Database connection not established (MySQLi).");
        $message = '<div class="alert alert-danger text-center">Database connection error. Cannot add to cart. Please try again later.</div>';
    } elseif (!isset($_SESSION['user_id'])) {
        $message = '<div class="alert alert-danger text-center">Please login to add items to your cart.</div>';
    } else {
        $user_id = $_SESSION['user_id'];
        $product_id_to_add = filter_var($_POST['product_id'], FILTER_VALIDATE_INT); // Sanitize product_id
        
        // Ensure product_id is valid
        if ($product_id_to_add === false || $product_id_to_add <= 0) {
            $message = '<div class="alert alert-danger text-center">Invalid product selected.</div>';
        } else {
            $quantity_to_add = 1; // Default to 1 when adding from the main page

            try {
                // 1. Check if product already exists in user's cart in the database (MySQLi Prepared Statement)
                $stmt_check_cart = $conn->prepare("SELECT cart_item_id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
                if ($stmt_check_cart === false) {
                    error_log("Add to cart: Error preparing cart check statement (MySQLi): " . $conn->error);
                    throw new Exception("Internal error during cart check preparation.");
                }
                $stmt_check_cart->bind_param("ii", $user_id, $product_id_to_add); // MySQLi bind_param
                $stmt_check_cart->execute();
                $result_check_cart = $stmt_check_cart->get_result(); // MySQLi get_result
                $cart_item = $result_check_cart->fetch_assoc(); // MySQLi fetch_assoc
                $stmt_check_cart->close(); // Close statement

                // Fetch product's stock quantity (MySQLi Prepared Statement)
                $stmt_stock = $conn->prepare("SELECT stock_quantity, name FROM products WHERE product_id = ?");
                if ($stmt_stock === false) {
                    error_log("Add to cart: Error preparing stock check statement (MySQLi): " . $conn->error);
                    throw new Exception("Internal error during stock check preparation.");
                }
                $stmt_stock->bind_param("i", $product_id_to_add); // MySQLi bind_param
                $stmt_stock->execute();
                $result_stock = $stmt_stock->get_result(); // MySQLi get_result
                $product_data = $result_stock->fetch_assoc(); // MySQLi fetch_assoc
                $product_stock = $product_data ? $product_data['stock_quantity'] : 0;
                $product_name = $product_data ? htmlspecialchars($product_data['name']) : 'Product';
                $stmt_stock->close(); // Close statement

                if ($cart_item) {
                    // Product already in cart, update quantity (MySQLi Prepared Statement)
                    $cart_item_id = $cart_item['cart_item_id'];
                    $current_quantity = $cart_item['quantity'];
                    $new_quantity = $current_quantity + $quantity_to_add;

                    if ($new_quantity <= $product_stock) {
                        $stmt_update = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
                        if ($stmt_update === false) {
                            error_log("Add to cart: Error preparing cart update statement (MySQLi): " . $conn->error);
                            throw new Exception("Internal error during cart update preparation.");
                        }
                        $stmt_update->bind_param("ii", $new_quantity, $cart_item_id); // MySQLi bind_param
                        if ($stmt_update->execute()) {
                            $message = '<div class="alert alert-success text-center">' . $product_name . ' quantity updated in cart!</div>';
                        } else {
                            $message = '<div class="alert alert-danger text-center">Error updating ' . $product_name . ' quantity.</div>';
                        }
                        $stmt_update->close(); // Close statement
                    } else {
                        $message = '<div class="alert alert-warning text-center">Not enough stock for ' . $product_name . '! Only ' . htmlspecialchars($product_stock) . ' available in total.</div>';
                    }
                } else {
                    // Product not in cart, insert new item (MySQLi Prepared Statement)
                    if ($quantity_to_add <= $product_stock && $product_stock > 0) { // Also check if stock is > 0
                        $stmt_insert = $conn->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
                        if ($stmt_insert === false) {
                            error_log("Add to cart: Error preparing cart insert statement (MySQLi): " . $conn->error);
                            throw new Exception("Internal error during cart insert preparation.");
                        }
                        $stmt_insert->bind_param("iii", $user_id, $product_id_to_add, $quantity_to_add); // MySQLi bind_param
                        if ($stmt_insert->execute()) {
                            $message = '<div class="alert alert-success text-center">' . $product_name . ' added to cart!</div>';
                        } else {
                            $message = '<div class="alert alert-danger text-center">Error adding ' . $product_name . ' to cart.</div>';
                        }
                        $stmt_insert->close(); // Close statement
                    } else {
                        $message = '<div class="alert alert-danger text-center">' . $product_name . ' is out of stock or requested quantity is too high.</div>';
                    }
                }
            } catch (Exception $e) { // Catch generic Exception
                // Log the error for debugging purposes
                error_log("Cart operation database error (MySQLi): " . $e->getMessage());
                $message = '<div class="alert alert-danger text-center">A database error occurred during cart operation. Please try again later.</div>';
            }
        }
    }
}
// --- END MODIFIED ADD TO CART LOGIC ---
?>

        <?php echo $message; ?>

        <section class="hero-section text-center d-flex align-items-center justify-content-center">
            <div class="container">
               
                <h1 class="display-3 fw-bold text-dark mb-3">Discover Quality Products, Effortlessly.</h1>
                <p class="lead text-muted mb-5">Welcome to Singko eCommerce, your one-stop shop for amazing products from various sellers. Explore our curated collection and find exactly what you're looking for.</p>
                <div class="d-flex justify-content-center">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a class="btn btn-outline-dark btn-lg rounded-pill px-5 py-3 me-3" href="login.php">Login</a>
                        <a class="btn btn-dark btn-lg rounded-pill px-5 py-3" href="register.php">Register</a>
                    <?php else: ?>
                        <a class="btn btn-dark btn-lg rounded-pill px-5 py-3" href="shop.php">Shop Now</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="categories-section py-5">
            <div class="container">
                <h2 class="text-center mb-5 fw-bold">Categories of The Month</h2>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
                    <?php foreach ($categories as $category): ?>
                    <div class="col">
                        <a href="<?php echo htmlspecialchars($category['link']); ?>" class="category-item card h-100 text-decoration-none text-dark d-flex align-items-center justify-content-center">
                            <div class="card-body text-center">
                                <i class="bx <?php echo htmlspecialchars($category['icon']); ?> category-icon mb-3"></i> 
                                <h5 class="card-title fw-bold"><?php echo htmlspecialchars($category['name']); ?></h5>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="featured-products-section py-5 bg-light">
            <div class="container">
                <h2 class="text-center mb-5 fw-bold">Our Latest Products</h2>
                <?php if (!empty($products)): ?>
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                        <?php foreach ($products as $product): ?>
                            <div class="col">
                                <div class="product-card card h-100 border-0 shadow-sm">
                                    <a href="shop-single.php?product_id=<?php echo htmlspecialchars($product['product_id']); ?>">
                                        <?php
                                            $image_src = !empty($product['image_url']) ? htmlspecialchars($product['image_url']) : 'https://placehold.co/300x200/cccccc/000000?text=No+Image';
                                        ?>
                                        <img src="<?php echo $image_src; ?>" class="card-img-top product-img-thumbnail" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                    </a>
                                    <div class="card-body text-center d-flex flex-column">
                                        <h5 class="card-title product-title mb-1"><?php echo htmlspecialchars($product['product_name']); ?></h5>
                                        <p class="product-seller text-muted small mb-2">By: <?php echo htmlspecialchars($product['seller_name']); ?></p>
                                        <p class="product-price fw-bold mb-3">PHP <?php echo htmlspecialchars(number_format($product['price'], 2)); ?></p>
                                        <form action="index.php" method="POST" class="mt-auto">
                                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['product_id']); ?>">
                                            <button type="submit" name="add_to_cart" class="btn btn-outline-dark btn-sm rounded-pill px-4"
                                                <?php echo (isset($product['stock_quantity']) && $product['stock_quantity'] <= 0) ? 'disabled' : ''; ?>>
                                                <?php echo (isset($product['stock_quantity']) && $product['stock_quantity'] <= 0) ? 'Out of Stock' : 'Add to Cart'; ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <p class="lead">No products found at the moment. Please check back later or contact support.</p>
                    </div>
                <?php endif; ?>
                <div class="text-center mt-5">
                    <a href="shop.php" class="btn btn-outline-dark btn-lg rounded-pill px-5 py-3">View All Products</a>
                </div>
            </div>
        </section>
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
</body>
</html>
