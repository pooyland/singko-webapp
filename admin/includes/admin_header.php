<?php
// Ensure session is started for all admin pages
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in or not an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("location: /marketplace/admin/index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="/marketplace/admin/css/admin_style.css">
    <style>
        /* Basic admin styling to mimic Sneat dashboard */
        body {
            background-color: #f2f4f8; /* Light greyish blue */
            font-family: 'Arial', sans-serif;
        }
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        #sidebar {
            min-width: 250px;
            max-width: 250px;
            background: #fff;
            color: #333;
            transition: all 0.3s;
            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
            padding-top: 20px;
        }
        #sidebar .sidebar-header {
            padding: 20px;
            text-align: center;
            font-size: 1.5em;
            font-weight: bold;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        #sidebar ul.components {
            padding: 20px 0;
        }
        #sidebar ul li a {
            padding: 10px 20px;
            font-size: 1.1em;
            display: block;
            color: #555;
            text-decoration: none;
            transition: all 0.3s;
        }
        #sidebar ul li a:hover {
            color: #007bff;
            background: #e9ecef;
        }
        #content {
            width: 100%;
            padding: 20px;
            min-height: 100vh;
        }
        .admin-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <nav id="sidebar">
            <div class="sidebar-header">
                <h3>SNEAT Admin</h3>
            </div>
            <ul class="list-unstyled components">
                <li><a href="/marketplace/admin/dashboard.php">Dashboard</a></li>
                <li><a href="/marketplace/admin/manage_users.php">Manage Users</a></li>
                <li><a href="/marketplace/admin/manage_products.php">Manage Products</a></li>
                </ul>
        </nav>

        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4 admin-card">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-info d-none d-lg-block">
                        <i class="fas fa-align-left"></i> <span>Toggle Sidebar</span>
                    </button>
                    <span class="navbar-text ms-auto me-3">
                        Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (Admin)
                    </span>
                    <a href="/marketplace/admin/admin_logout.php" class="btn btn-danger btn-sm">Logout</a>
                </div>
            </nav>