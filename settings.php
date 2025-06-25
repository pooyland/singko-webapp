<?php
// settings.php
include 'includes/header.php'; // Includes the top bar, navigation, and opens <main>

// Include your database configuration
require_once 'db_config.php';

// Start session if not already started (header.php might do this already)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

$message = '';
$message_type = '';

// Check for and display session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']); // Clear the message after displaying
    unset($_SESSION['message_type']);
}

// Fetch current user information for pre-filling forms
$user_info = [];
try {
    $sql_fetch_user = "SELECT username, email, email_notifications FROM users WHERE user_id = ?";
    $stmt_fetch_user = $conn->prepare($sql_fetch_user);
    if ($stmt_fetch_user === false) {
        throw new Exception("Failed to prepare user fetch query: " . $conn->error);
    }
    $stmt_fetch_user->bind_param("i", $user_id);
    $stmt_fetch_user->execute();
    $result_fetch_user = $stmt_fetch_user->get_result();
    if ($result_fetch_user->num_rows > 0) {
        $user_info = $result_fetch_user->fetch_assoc();
    } else {
        throw new Exception("User not found.");
    }
    $stmt_fetch_user->close();
} catch (Exception $e) {
    error_log("Settings Page: Error fetching user info - " . $e->getMessage());
    $message = "Error loading user information: " . $e->getMessage();
    $message_type = "danger";
}


// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- Handle Personal Information Update ---
    if (isset($_POST['update_profile'])) {
        $new_username = trim($_POST['username']);
        $new_email = trim($_POST['email']);
        $current_password_input = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_new_password = $_POST['confirm_new_password'] ?? '';

        // Basic validation
        if (empty($new_username) || empty($new_email)) {
            $message = "Username and email cannot be empty.";
            $message_type = "danger";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format.";
            $message_type = "danger";
        } else {
            // Check if email or username already exists for another user
            $sql_check_duplicate = "SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?";
            $stmt_check_duplicate = $conn->prepare($sql_check_duplicate);
            if ($stmt_check_duplicate === false) {
                error_log("Settings: MySQLi prepare failed for duplicate check: " . $conn->error);
                $message = "Database error during duplicate check.";
                $message_type = "danger";
            } else {
                $stmt_check_duplicate->bind_param("ssi", $new_username, $new_email, $user_id);
                $stmt_check_duplicate->execute();
                $result_duplicate = $stmt_check_duplicate->get_result();
                if ($result_duplicate->num_rows > 0) {
                    $message = "Username or Email already exists. Please choose another.";
                    $message_type = "danger";
                }
                $stmt_check_duplicate->close();
            }

            if ($message == '') { // Only proceed if no previous errors
                $update_password_sql = "";
                $params = [];
                $types = "";

                // Handle password change if fields are filled
                if (!empty($new_password)) {
                    if (empty($current_password_input)) {
                        $message = "Current password is required to change password.";
                        $message_type = "danger";
                    } elseif ($new_password !== $confirm_new_password) {
                        $message = "New password and confirm password do not match.";
                        $message_type = "danger";
                    } elseif (strlen($new_password) < 6) { // Basic password length validation
                        $message = "New password must be at least 6 characters long.";
                        $message_type = "danger";
                    } else {
                        // Verify current password
                        $sql_verify_password = "SELECT password_hash FROM users WHERE user_id = ?";
                        $stmt_verify_password = $conn->prepare($sql_verify_password);
                        if ($stmt_verify_password === false) {
                            error_log("Settings: MySQLi prepare failed for password verify: " . $conn->error);
                            $message = "Database error during password verification.";
                            $message_type = "danger";
                        } else {
                            $stmt_verify_password->bind_param("i", $user_id);
                            $stmt_verify_password->execute();
                            $result_verify_password = $stmt_verify_password->get_result();
                            $user_db_password = $result_verify_password->fetch_assoc()['password_hash'] ?? '';
                            $stmt_verify_password->close();

                            if (!password_verify($current_password_input, $user_db_password)) {
                                $message = "Current password is incorrect.";
                                $message_type = "danger";
                            } else {
                                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                                $update_password_sql = ", password_hash = ?";
                                $params[] = $hashed_new_password;
                                $types .= "s";
                            }
                        }
                    }
                }

                if ($message == '') { // Only proceed if no password errors either
                    // Update user data
                    $sql_update_user = "UPDATE users SET username = ?, email = ?" . $update_password_sql . " WHERE user_id = ?";
                    $stmt_update_user = $conn->prepare($sql_update_user);
                    if ($stmt_update_user === false) {
                        error_log("Settings: MySQLi prepare failed for user update: " . $conn->error);
                        $message = "Database error during profile update.";
                        $message_type = "danger";
                    } else {
                        // Bind parameters dynamically
                        array_unshift($params, $new_username, $new_email);
                        $types = "ss" . $types; // prepend types for username and email
                        $params[] = $user_id; // append user_id for WHERE clause
                        $types .= "i";

                        // Use call_user_func_array for dynamic bind_param
                        $bind_params = [];
                        foreach ($params as $key => $value) {
                            $bind_params[$key] = &$params[$key]; // Pass by reference
                        }
                        call_user_func_array([$stmt_update_user, 'bind_param'], array_merge([$types], $bind_params));

                        if ($stmt_update_user->execute()) {
                            $_SESSION['username'] = $new_username; // Update session username
                            $message = "Profile updated successfully!";
                            $message_type = "success";
                            // Re-fetch user info to update the displayed values immediately
                            $user_info['username'] = $new_username;
                            $user_info['email'] = $new_email;
                        } else {
                            $message = "Failed to update profile: " . $stmt_update_user->error;
                            $message_type = "danger";
                            error_log("Settings: Failed to update profile - " . $stmt_update_user->error);
                        }
                        $stmt_update_user->close();
                    }
                }
            }
        }
    }

    // --- Handle Notification Preferences Update ---
    if (isset($_POST['update_notifications'])) {
        $email_notifications_value = isset($_POST['email_notifications']) ? 1 : 0; // Checkbox value

        try {
            $sql_update_notifications = "UPDATE users SET email_notifications = ? WHERE user_id = ?";
            $stmt_update_notifications = $conn->prepare($sql_update_notifications);
            if ($stmt_update_notifications === false) {
                throw new Exception("Failed to prepare notification update query: " . $conn->error);
            }
            $stmt_update_notifications->bind_param("ii", $email_notifications_value, $user_id);
            if ($stmt_update_notifications->execute()) {
                $message = "Notification preferences updated successfully!";
                $message_type = "success";
                $user_info['email_notifications'] = $email_notifications_value; // Update local info
            } else {
                $message = "Failed to update notification preferences: " . $stmt_update_notifications->error;
                $message_type = "danger";
            }
            $stmt_update_notifications->close();
        } catch (Exception $e) {
            error_log("Settings Page: Error updating notifications - " . $e->getMessage());
            $message = "Error updating notification preferences: " . $e->getMessage();
            $message_type = "danger";
        }
    }

    // Store message in session and redirect to prevent form resubmission on refresh
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $message_type;
    header("Location: settings.php");
    exit;
}

$conn->close();
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
                    
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Account Settings /</span> Settings</h4>

                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <h5 class="card-header">Personal Information</h5>
                            <div class="card-body">
                                <form method="POST" action="settings.php">
                                    <input type="hidden" name="update_profile" value="1">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user_info['username'] ?? ''); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" required>
                                    </div>
                                    <hr class="my-4">
                                    <h6 class="mb-3">Change Password (optional)</h6>
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password">
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password">
                                        <div class="form-text">Minimum 6 characters.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_new_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password">
                                    </div>
                                    <button type="submit" class="btn btn-primary">Update Profile</button>
                                </form>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <h5 class="card-header">Notification Preferences</h5>
                            <div class="card-body">
                                <form method="POST" action="settings.php">
                                    <input type="hidden" name="update_notifications" value="1">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="emailNotifications" name="email_notifications" <?php echo ($user_info['email_notifications'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="emailNotifications">Receive email notifications for order updates and promotions.</label>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Save Preferences</button>
                                </form>
                            </div>
                        </div>

                    </div>
                    <div class="content-backdrop fade"></div>
                </div>
                </div>
            </div>

        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    </main> <footer class="bg-dark text-white py-4 mt-5">
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
</html>
