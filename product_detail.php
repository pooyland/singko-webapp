<?php
// product_detail.php
include 'includes/header.php'; // Includes the top bar, navigation, and opens <main>

// Include your database configuration
require_once 'db_config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$product_id = $_GET['id'] ?? null;
$product = null;
$message = '';
$message_type = '';

// Check for and display session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']); // Clear the message after displaying
    unset($_SESSION['message_type']);
}

if ($product_id === null || !is_numeric($product_id) || $product_id <= 0) {
    $message = "Invalid product ID provided.";
    $message_type = "danger";
    // Redirect to shop page if invalid ID
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $message_type;
    header('Location: shop.php');
    exit;
}

try {
    // Fetch product details
    $sql_product = "SELECT p.product_id, p.name, p.description, p.price, p.image_url, p.stock_quantity, c.name AS category_name, u.username AS seller_username
                    FROM products p
                    JOIN categories c ON p.category_id = c.category_id
                    JOIN users u ON p.seller_id = u.user_id
                    WHERE p.product_id = ?";
    $stmt_product = $conn->prepare($sql_product);
    if ($stmt_product === false) {
        throw new Exception("Failed to prepare product detail query: " . $conn->error);
    }
    $stmt_product->bind_param("i", $product_id);
    $stmt_product->execute();
    $result_product = $stmt_product->get_result();
    $product = $result_product->fetch_assoc();
    $stmt_product->close();

    if (!$product) {
        $message = "Product not found.";
        $message_type = "danger";
        // Redirect to shop page if product not found
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = $message_type;
        header('Location: shop.php');
        exit;
    }

} catch (Exception $e) {
    error_log("Product Detail Page: Error fetching product details - " . $e->getMessage());
    $message = "Error loading product details: " . $e->getMessage();
    $message_type = "danger";
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $message_type;
    header('Location: shop.php');
    exit;
} finally {
    if ($conn && $conn->ping()) {
        $conn->close();
    }
}
?>

            <div class="content-wrapper" style="padding-left: 0; padding-right: 0;">
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Product /</span> <?php echo htmlspecialchars($product['name']); ?></h4>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card mb-4 shadow-sm">
                        <div class="row g-0">
                            <div class="col-md-5">
                                <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'https://placehold.co/600x400/cccccc/000000?text=No+Image'); ?>" class="img-fluid rounded-start w-100 h-100 object-cover" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            </div>
                            <div class="col-md-7">
                                <div class="card-body">
                                    <h2 class="card-title text-primary"><?php echo htmlspecialchars($product['name']); ?></h2>
                                    <p class="card-text text-muted mb-2"><small>Category: <?php echo htmlspecialchars($product['category_name']); ?></small></p>
                                    <p class="card-text text-muted mb-4"><small>Sold by: <?php echo htmlspecialchars($product['seller_username']); ?></small></p>

                                    <h3 class="text-success mb-3">PHP <?php echo number_format($product['price'], 2); ?></h3>

                                    <p class="card-text mb-4"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>

                                    <div class="mb-3">
                                        <strong>Stock:</strong>
                                        <?php if ($product['stock_quantity'] > 0): ?>
                                            <span class="badge bg-label-success ms-2"><?php echo htmlspecialchars($product['stock_quantity']); ?> in stock</span>
                                            <?php if ($product['stock_quantity'] <= 5): ?>
                                                <small class="text-danger ms-2">Low stock!</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-label-danger ms-2">Out of Stock</span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($product['stock_quantity'] > 0): ?>
                                        <div class="d-flex align-items-center mb-3">
                                            <label for="quantityInput" class="form-label me-2 mb-0">Quantity:</label>
                                            <input type="number" id="quantityInput" class="form-control w-25 me-3" value="1" min="1" max="<?php echo htmlspecialchars($product['stock_quantity']); ?>">
                                        </div>

                                        <div class="d-flex gap-2">
                                            <!-- Add to Cart Button/Form -->
                                            <form action="add_to_cart.php" method="POST" class="d-inline-block" id="addToCartForm">
                                                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['product_id']); ?>">
                                                <input type="hidden" name="quantity" id="cartQuantity" value="1">
                                                <button type="submit" class="btn btn-outline-secondary">
                                                    <i class="bx bx-cart-add me-1"></i> Add to Cart
                                                </button>
                                            </form>

                                            <!-- Buy Now Button/Form -->
                                            <form action="buy_now.php" method="POST" class="d-inline-block" id="buyNowForm">
                                                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['product_id']); ?>">
                                                <input type="hidden" name="quantity" id="buyNowQuantity" value="1">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bx bx-credit-card me-1"></i> Buy Now
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled>Out of Stock</button>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>
                    </div>

                    <a href="shop.php" class="btn btn-outline-secondary mt-3"><i class="bx bx-arrow-back me-1"></i> Back to Shop</a>

                </div>
                <div class="content-backdrop fade"></div>
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

    <script>
        // Synchronize quantity input with hidden form fields for Add to Cart and Buy Now
        document.addEventListener('DOMContentLoaded', function() {
            const quantityInput = document.getElementById('quantityInput');
            const cartQuantityInput = document.getElementById('cartQuantity');
            const buyNowQuantityInput = document.getElementById('buyNowQuantity');

            if (quantityInput && cartQuantityInput && buyNowQuantityInput) {
                quantityInput.addEventListener('change', function() {
                    let quantity = parseInt(this.value);
                    const maxStock = parseInt(this.max);

                    if (isNaN(quantity) || quantity < 1) {
                        quantity = 1;
                    }
                    if (quantity > maxStock) {
                        quantity = maxStock;
                    }
                    this.value = quantity; // Update visible input
                    cartQuantityInput.value = quantity;
                    buyNowQuantityInput.value = quantity;
                });
            }
        });
    </script>

</body>
</html>
