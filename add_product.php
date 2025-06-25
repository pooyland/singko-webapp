<?php
// add_product.php
// 1. ALWAYS START SESSION FIRST.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include your database configuration. This should now provide the $conn MySQLi object.
require_once 'db_config.php'; // Ensure this path is correct and it initializes $conn as a MySQLi object

// Check if $conn is a valid MySQLi object immediately after inclusion
if (!$conn instanceof mysqli) {
    error_log("Add Product Page: Database connection (\$conn) is not a MySQLi object. Check db_config.php.");
    // Display error message to user via session, then redirect
    $_SESSION['message'] = "Critical database error: Connection not established correctly. Please try again later.";
    $_SESSION['message_type'] = "danger";
    header('Location: login.php'); // Redirect to login or an error page
    exit;
}

// User Login Check (MUST happen BEFORE any HTML output)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Please login to add products.";
    $_SESSION['message_type'] = "danger";
    header('Location: login.php');
    exit;
}

$message = '';
$message_type = '';

// Fetch categories for the dropdown (MySQLi)
$categories = [];
try {
    $category_result = $conn->query("SELECT category_id, name FROM categories ORDER BY name");
    if ($category_result) {
        while ($row = $category_result->fetch_assoc()) {
            $categories[] = $row;
        }
        $category_result->free(); // Free result set
    } else {
        error_log("Add Product Page: Error fetching categories (MySQLi): " . $conn->error);
        throw new Exception("Error fetching categories for dropdown.");
    }
} catch (Exception $e) {
    $message = "Error fetching categories: " . $e->getMessage();
    $message_type = "danger";
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Get form data
    $product_name = trim($_POST['product_name'] ?? '');
    $product_description = trim($_POST['product_description'] ?? '');
    $product_price = trim($_POST['product_price'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $stock_quantity = $_POST['stock_quantity'] ?? 0;
    // Assuming seller_id comes from the logged-in user's session
    $seller_id = $_SESSION['user_id']; 

    // Initialize values for form sticky behavior
    $current_product_name = $product_name;
    $current_product_description = $product_description;
    $current_product_price = $product_price;
    $current_category_id = $category_id;
    $current_stock_quantity = $stock_quantity;

    // 2. Validate input
    if (empty($product_name) || empty($product_description) || empty($product_price) || empty($category_id) || !isset($_FILES['product_image']) || $_FILES['product_image']['error'] !== UPLOAD_ERR_OK) {
        $message = "Please fill in all required fields, upload an image, and ensure the image upload was successful.";
        $message_type = "danger";
    } elseif (!is_numeric($product_price) || $product_price <= 0) {
        $message = "Product price must be a positive number.";
        $message_type = "danger";
    } elseif (!is_numeric($stock_quantity) || $stock_quantity < 0) {
        $message = "Stock quantity must be a non-negative number.";
        $message_type = "danger";
    } else {
        // 3. Handle image upload
        $target_dir = "uploads/products/"; // Directory where images will be stored
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $image_file_name = uniqid() . "_" . basename($_FILES["product_image"]["name"]);
        $target_file = $target_dir . $image_file_name;
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Check if image file is a actual image or fake image
        $check = getimagesize($_FILES["product_image"]["tmp_name"]);
        if ($check !== false) {
            // Check file size (e.g., 5MB limit)
            if ($_FILES["product_image"]["size"] > 5000000) {
                $message = "Sorry, your file is too large (max 5MB).";
                $uploadOk = 0;
            }
            // Allow certain file formats
            if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
                $message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
                $uploadOk = 0;
            }
        } else {
            $message = "File is not an image.";
            $uploadOk = 0;
        }

        // If everything is ok, try to upload file
        if ($uploadOk == 0) {
            $message_type = "danger";
        } else {
            if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
                // 4. Insert data into database (MySQLi Prepared Statement)
                $stmt = $conn->prepare("INSERT INTO products (name, description, price, category_id, stock_quantity, image_url, seller_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                if ($stmt === false) {
                    error_log("Add Product (POST): MySQLi prepare failed for product insertion: " . $conn->error);
                    $message = "A database error occurred during product insertion preparation.";
                    $message_type = "danger";
                } else {
                    $stmt->bind_param("ssdiisi", $product_name, $product_description, $product_price, $category_id, $stock_quantity, $target_file, $seller_id);

                    if ($stmt->execute()) {
                        $message = "New product added successfully!";
                        $message_type = "success";
                        // Clear form fields after successful submission only if no errors
                        $current_product_name = '';
                        $current_product_description = '';
                        $current_product_price = '';
                        $current_category_id = '';
                        $current_stock_quantity = 0;
                    } else {
                        $message = "Error adding product: " . $stmt->error;
                        $message_type = "danger";
                    }
                    $stmt->close(); // Close the statement
                }
            } else {
                $message = "Sorry, there was an error uploading your file.";
                $message_type = "danger";
            }
        }
    }
} else {
    // Initialize variables for the form on first load (GET request)
    $current_product_name = '';
    $current_product_description = '';
    $current_product_price = '';
    $current_category_id = '';
    $current_stock_quantity = '';
}


// Check for messages stored in session (e.g., from critical db error in db_config.php)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']); // Clear the message after displaying
    unset($_SESSION['message_type']);
}

// Include the header which contains <!DOCTYPE html>, <head>, and the top navigation bar
include 'includes/header.php';
?>

<!-- The <main> tag is opened in header.php -->

    <!-- Layout wrapper from dashboard template -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->
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
                    <!-- Dashboard -->
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
                    <!-- Sales Report Menu Item -->
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
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>
                    

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Product /</span> Add New Product</h4>

                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Product Details</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label class="form-label" for="product_name">Product Name</label>
                                        <input type="text" class="form-control" id="product_name" name="product_name" placeholder="Enter product name" value="<?php echo htmlspecialchars($current_product_name); ?>" required />
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="product_description">Description</label>
                                        <textarea class="form-control" id="product_description" name="product_description" rows="5" placeholder="Detailed product description" required><?php echo htmlspecialchars($current_product_description); ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="product_price">Price (PHP)</label>
                                        <input type="number" step="0.01" class="form-control" id="product_price" name="product_price" placeholder="e.g., 1299.99" value="<?php echo htmlspecialchars($current_product_price); ?>" required min="0.01" />
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="category_id">Category</label>
                                        <select class="form-select" id="category_id" name="category_id" required>
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['category_id']; ?>" <?php echo (isset($current_category_id) && $current_category_id == $category['category_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="stock_quantity">Stock Quantity</label>
                                        <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" placeholder="e.g., 100" value="<?php echo htmlspecialchars($current_stock_quantity); ?>" required min="0" />
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="product_image">Product Image</label>
                                        <input type="file" class="form-control" id="product_image" name="product_image" accept="image/*" required />
                                        <div class="form-text">Upload a high-quality image of your product (Max 5MB).</div>
                                    </div>
                                    <button type="submit" class="btn btn-primary-sneat">Add Product</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- / Content -->

                    <div class="content-backdrop fade"></div>
                </div>
                <!-- Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

</main> <!-- The <main> tag closes here -->

<!-- Replaced with your simpler footer -->
<footer class="bg-dark text-white py-4 mt-5">
    <div class="container text-center">
        <p>&copy; <?php echo date('Y'); ?> Singko eCommerce. All rights reserved.</p>
    </div>
</footer>

<!-- Core JS (Bootstrap and other JS, now at the very end of the body) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<!-- The following scripts are from the template and are assumed to be placed in your js/ folder -->
<script src="js/helpers.js"></script>
<script src="js/config.js"></script>
<script src="js/menu.js"></script>
<script src="js/main.js"></script>
<script async defer src="https://buttons.github.com/buttons.js"></script>

</body>
</html>
