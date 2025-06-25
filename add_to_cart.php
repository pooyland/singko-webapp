<?php
// add_to_cart.php

// 1. ALWAYS START SESSION FIRST.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. User Login Check
if (!isset($_SESSION['user_id'])) {
    // If not logged in, redirect to login page with a message
    $_SESSION['message'] = "Please log in to add items to your cart.";
    $_SESSION['message_type'] = "warning";
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// 3. Include database config. Ensure db_config.php has no output.
require_once 'db_config.php';

// Check if $conn is a valid MySQLi object immediately after inclusion
if (!$conn instanceof mysqli) {
    error_log("Add to Cart: Database connection (\$conn) is not a MySQLi object. Check db_config.php.");
    $_SESSION['message'] = "Critical database error: Connection not established correctly. Please try again later.";
    $_SESSION['message_type'] = "danger";
    header('Location: product_detail.php?id=' . ($_POST['product_id'] ?? '')); // Redirect back to product page
    exit;
}

// 4. Get and validate product_id and quantity from POST request
$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

// Basic validation
if ($productId === false || $productId <= 0 || $quantity === false || $quantity <= 0) {
    $_SESSION['message'] = "Invalid product ID or quantity provided.";
    $_SESSION['message_type'] = "danger";
    header('Location: product_detail.php?id=' . ($productId ?: '')); // Redirect back
    exit;
}

// Start transaction for atomicity
$conn->begin_transaction();

try {
    // A. Check current product stock
    $stmt_stock = $conn->prepare("SELECT stock_quantity, name FROM products WHERE product_id = ? FOR UPDATE"); // FOR UPDATE locks the row
    if ($stmt_stock === false) {
        throw new Exception("Failed to prepare product stock query: " . $conn->error);
    }
    $stmt_stock->bind_param("i", $productId);
    $stmt_stock->execute();
    $result_stock = $stmt_stock->get_result();
    $product_data = $result_stock->fetch_assoc();
    $stmt_stock->close();

    if (!$product_data) {
        throw new Exception("Product not found.");
    }

    $available_stock = $product_data['stock_quantity'];
    $product_name = htmlspecialchars($product_data['name']);

    // B. Check if product already exists in the user's cart
    $stmt_check_cart = $conn->prepare("SELECT cart_item_id, quantity FROM cart_items WHERE user_id = ? AND product_id = ? FOR UPDATE");
    if ($stmt_check_cart === false) {
        throw new Exception("Failed to prepare cart check query: " . $conn->error);
    }
    $stmt_check_cart->bind_param("ii", $user_id, $productId);
    $stmt_check_cart->execute();
    $result_check_cart = $stmt_check_cart->get_result();
    $cart_item = $result_check_cart->fetch_assoc();
    $stmt_check_cart->close();

    if ($cart_item) {
        // Product is already in cart, update quantity
        $new_total_quantity = $cart_item['quantity'] + $quantity;

        if ($new_total_quantity > $available_stock) {
            $_SESSION['message'] = "Cannot add " . $quantity . " more of " . $product_name . ". Only " . htmlspecialchars($available_stock - $cart_item['quantity']) . " additional items available in stock.";
            $_SESSION['message_type'] = "warning";
            $conn->rollback(); // Rollback transaction
            header('Location: product_detail.php?id=' . $productId);
            exit;
        }

        $stmt_update = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ? AND user_id = ?");
        if ($stmt_update === false) {
            throw new Exception("Failed to prepare cart update query: " . $conn->error);
        }
        $stmt_update->bind_param("iii", $new_total_quantity, $cart_item['cart_item_id'], $user_id);
        if (!$stmt_update->execute()) {
            throw new Exception("Failed to update cart item quantity.");
        }
        $stmt_update->close();
        $_SESSION['message'] = $product_name . " quantity updated in cart to " . $new_total_quantity . ".";
        $_SESSION['message_type'] = "success";

    } else {
        // Product is not in cart, insert new item
        if ($quantity > $available_stock) {
            $_SESSION['message'] = "Cannot add " . $product_name . ". Only " . htmlspecialchars($available_stock) . " available in stock.";
            $_SESSION['message_type'] = "warning";
            $conn->rollback(); // Rollback transaction
            header('Location: product_detail.php?id=' . $productId);
            exit;
        }

        $stmt_insert = $conn->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
        if ($stmt_insert === false) {
            throw new Exception("Failed to prepare cart insert query: " . $conn->error);
        }
        $stmt_insert->bind_param("iii", $user_id, $productId, $quantity);
        if (!$stmt_insert->execute()) {
            throw new Exception("Failed to add new cart item.");
        }
        $stmt_insert->close();
        $_SESSION['message'] = $product_name . " added to cart successfully!";
        $_SESSION['message_type'] = "success";
    }

    $conn->commit(); // Commit transaction on success

} catch (Exception $e) {
    $conn->rollback(); // Rollback on any error
    error_log("Add to Cart Error: " . $e->getMessage());
    $_SESSION['message'] = "Error adding product to cart: " . $e->getMessage();
    $_SESSION['message_type'] = "danger";
} finally {
    // Ensure the connection is closed if it was opened in this script and not part of a persistent pool
    // This assumes your db_config.php does not close it globally.
    if ($conn && $conn->ping()) {
        $conn->close();
    }
}

// Redirect to the my_cart.php page
header('Location: my_cart.php');
exit();
?>