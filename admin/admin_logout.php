<?php
session_start();
// Check if it's an admin logging out to prevent accidental general user logout from admin panel
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $_SESSION = array(); // Unset all of the session variables
    session_destroy(); // Destroy the session.
}
header("location: /marketplace/admin/index.php"); // Redirect to admin login page
exit;
?>