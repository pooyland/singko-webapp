<?php
require_once '../db_config.php';
require_once 'includes/admin_header.php';

$message = '';

// Handle actions (e.g., delete product, change status)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $product_id = $_POST['product_id'];

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Product deleted successfully.</div>';
        } else {
            $message = '<div class="alert alert-danger">Error deleting product.</div>';
        }
        $stmt->close();
    } elseif ($action === 'change_status' && isset($_POST['new_status'])) {
        $new_status = $_POST['new_status'];
        $stmt = $conn->prepare("UPDATE products SET status = ? WHERE product_id = ?");
        $stmt->bind_param("si", $new_status, $product_id);
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Product status updated successfully.</div>';
        } else {
            $message = '<div class="alert alert-danger">Error updating product status.</div>';
        }
        $stmt->close();
    }
}

// Fetch all products with seller and category info
$sql = "SELECT p.*, u.username as seller_username, c.name as category_name
        FROM products p
        JOIN users u ON p.seller_id = u.user_id
        JOIN categories c ON p.category_id = c.category_id
        ORDER BY p.created_at DESC";
$result = $conn->query($sql);
?>

<h1 class="mb-4">Manage Products</h1>

<?php echo $message; ?>

<div class="admin-card">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>Product Name</th>
                <th>Seller</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['product_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['seller_username']); ?></td>
                        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                        <td>$<?php echo htmlspecialchars(number_format($row['price'], 2)); ?></td>
                        <td><?php echo htmlspecialchars($row['stock_quantity']); ?></td>
                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                        <td>
                            <form action="manage_products.php" method="POST" class="d-inline">
                                <input type="hidden" name="product_id" value="<?php echo $row['product_id']; ?>">
                                <select name="new_status" class="form-select form-select-sm d-inline-block w-auto">
                                    <option value="active" <?php echo ($row['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($row['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="sold_out" <?php echo ($row['status'] == 'sold_out') ? 'selected' : ''; ?>>Sold Out</option>
                                </select>
                                <button type="submit" name="action" value="change_status" class="btn btn-info btn-sm">Update Status</button>
                            </form>
                            <form action="manage_products.php" method="POST" class="d-inline ms-2" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                <input type="hidden" name="product_id" value="<?php echo $row['product_id']; ?>">
                                <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                echo '<tr><td colspan="8" class="text-center">No products found.</td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>

<?php
require_once 'includes/admin_footer.php';
$conn->close();
?>