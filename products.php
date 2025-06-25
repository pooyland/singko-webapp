<?php
require_once 'db_config.php';
require_once 'includes/header.php';

// Fetch all active products
$sql = "SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.category_id WHERE p.status = 'active' ORDER BY p.name ASC";
$result = $conn->query($sql);

?>

<h1 class="mb-4">Our Products</h1>

<div class="row">
    <?php
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            ?>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="card product-card">
                    <img src="<?php echo htmlspecialchars($row['image_url'] ?: 'https://via.placeholder.com/150'); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($row['name']); ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($row['name']); ?></h5>
                        <p class="card-text text-muted"><?php echo htmlspecialchars($row['category_name']); ?></p>
                        <p class="card-text"><strong>$<?php echo htmlspecialchars(number_format($row['price'], 2)); ?></strong></p>
                        <a href="#" class="btn btn-sm btn-green">View Product</a>
                        <a href="#" class="btn btn-sm btn-outline-secondary">Add to Cart</a>
                    </div>
                </div>
            </div>
            <?php
        }
    } else {
        echo "<p class='text-center col-12'>No products found.</p>";
    }
    ?>
</div>

<?php
require_once 'includes/footer.php';
$conn->close();
?>