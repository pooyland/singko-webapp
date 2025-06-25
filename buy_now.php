<?php
// buy_now.php
// This script handles direct product purchases, bypassing the cart.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Please log in to buy products directly.";
    $_SESSION['message_type'] = "danger";
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = $_POST['product_id'] ?? null;
$quantity = $_POST['quantity'] ?? 1; // Default quantity to 1 for Buy Now

$message = "";
$message_type = "";

if (empty($product_id) || !is_numeric($product_id) || $product_id <= 0) {
    $message = "Invalid product ID provided.";
    $message_type = "danger";
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $message_type;
    header('Location: shop.php');
    exit;
}

if (!is_numeric($quantity) || $quantity <= 0) {
    $message = "Invalid quantity provided.";
    $message_type = "danger";
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $message_type;
    header('Location: shop.php');
    exit;
}

try {
    // Start a transaction for atomicity
    $conn->begin_transaction();

    // 1. Fetch product details and check stock
    $sql_product = "SELECT product_id, name, price, stock_quantity FROM products WHERE product_id = ? FOR UPDATE"; // FOR UPDATE to lock row
    $stmt_product = $conn->prepare($sql_product);
    if ($stmt_product === false) {
        throw new Exception("Failed to prepare product fetch query: " . $conn->error);
    }
    $stmt_product->bind_param("i", $product_id);
    $stmt_product->execute();
    $result_product = $stmt_product->get_result();
    $product = $result_product->fetch_assoc();
    $stmt_product->close();

    if (!$product) {
        throw new Exception("Product not found.");
    }

    if ($product['stock_quantity'] < $quantity) {
        throw new Exception("Insufficient stock for " . htmlspecialchars($product['name']) . ". Available: " . htmlspecialchars($product['stock_quantity']) . ".");
    }

    // 2. Create a new order
    $order_date = date('Y-m-d H:i:s');
    $order_status = 'Pending'; // Or 'Completed' if no further payment step
    $sql_insert_order = "INSERT INTO orders (user_id, order_date, order_status) VALUES (?, ?, ?)";
    $stmt_insert_order = $conn->prepare($sql_insert_order);
    if ($stmt_insert_order === false) {
        throw new Exception("Failed to prepare order insert query: " . $conn->error);
    }
    $stmt_insert_order->bind_param("iss", $user_id, $order_date, $order_status);
    $stmt_insert_order->execute();
    $order_id = $conn->insert_id; // Get the ID of the newly created order
    $stmt_insert_order->close();

    if (!$order_id) {
        throw new Exception("Failed to create order.");
    }

    // 3. Add item to order_items
    $sql_insert_item = "INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)";
    $stmt_insert_item = $conn->prepare($sql_insert_item);
    if ($stmt_insert_item === false) {
        throw new Exception("Failed to prepare order item insert query: " . $conn->error);
    }
    $price_at_purchase = $product['price']; // Store current price
    $stmt_insert_item->bind_param("iiid", $order_id, $product_id, $quantity, $price_at_purchase);
    $stmt_insert_item->execute();
    $stmt_insert_item->close();

    // 4. Update product stock quantity
    $sql_update_stock = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?";
    $stmt_update_stock = $conn->prepare($sql_update_stock);
    if ($stmt_update_stock === false) {
        throw new Exception("Failed to prepare stock update query: " . $conn->error);
    }
    $stmt_update_stock->bind_param("ii", $quantity, $product_id);
    $stmt_update_stock->execute();
    $stmt_update_stock->close();

    // If all steps successful, commit the transaction
    $conn->commit();
    $message = "Product purchased successfully! Your Order ID: " . $order_id;
    $message_type = "success";

} catch (Exception $e) {
    // Rollback transaction on any error
    $conn->rollback();
    $message = "Error processing purchase: " . $e->getMessage();
    $message_type = "danger";
    error_log("Buy Now Error: " . $e->getMessage());
} finally {
    if ($conn && $conn->ping()) {
        $conn->close();
    }
}

// Redirect to my_orders.php with the message
$_SESSION['message'] = $message;
$_SESSION['message_type'] = $message_type;
header('Location: my_orders.php');
exit;

?>
