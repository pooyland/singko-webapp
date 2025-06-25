<?php
// header.php
// Ensure session is started if not already. This is important for $_SESSION variables.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Include db_config.php if your header needs database access for dynamic content (e.g., user info, cart count)
require_once 'db_config.php'; // Assuming db_config.php is in the same directory as header.php

// Define a variable to set the active navigation link
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Singko eCommerce - Your Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Your custom style.css link - path adjusted to 'css/style.css' -->
    <link rel="stylesheet" href="css/style.css"> 
    
    <!-- Inline styles from your original header.php -->
    <style>
        /* Top Bar Styles */
        .top-bar {
            background-color: #343a40; /* Dark background for the top bar */
            color: #ffffff;
            padding: 0.5rem 0;
            font-size: 0.85rem;
        }
        .top-bar a {
            color: #ffffff;
            text-decoration: none;
            margin-right: 15px;
        }
        .top-bar a:hover {
            color: #cccccc;
        }

        /* Navbar Brand Styles */
        .navbar-brand-custom {
            font-weight: 700;
            color: #007bff !important; /* Adjust brand color if needed */
            font-size: 1.5rem;
            display: flex; /* Use flexbox for logo and text */
            align-items: center;
        }
        .navbar-brand-custom img {
            height: 40px; /* Adjust logo size */
            margin-right: 10px;
        }

        /* Navigation Links Styles */
        .nav-link-custom {
            color: #343a40 !important; /* Darker text for main nav links */
            font-weight: 500;
            margin-right: 15px;
        }
        .nav-link-custom:hover {
            color: #007bff !important; /* Hover effect */
        }

        /* Cart Icon and Count Styles */
        .cart-icon-link {
            position: relative;
            display: inline-block;
        }
        .cart-count {
            position: absolute;
            top: -5px;
            right: -10px;
            font-size: 0.7em;
            padding: 0.2em 0.5em;
            background-color: #007bff; /* Primary color for cart count */
            color: white;
            border-radius: 50%;
            line-height: 1; /* Adjust line height for better vertical alignment */
        }
        .navbar .dropdown-menu {
            border-top: 3px solid #007bff; /* Highlight dropdown menu */
        }

        /* General Layout Styles (moved from index.php that are header-related or general) */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main {
            flex-grow: 1;
        }
        /* Path for hero-bg.jpg adjusted assuming it's directly in assets/images/ */
        .hero-section {
            background: linear-gradient(rgba(255,255,255,0.8), rgba(255,255,255,0.8)), url('assets/images/hero-bg.jpg') no-repeat center center;
            background-size: cover;
            min-height: 50vh; /* Adjust hero height */
        }
        .hero-logo {
            max-width: 150px; /* Size for the hero section logo */
        }
        .category-item {
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }
        .category-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }
        .category-icon {
            font-size: 3rem;
            color: #007bff;
        }
        .product-card {
            transition: all 0.3s ease;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
        }
        .product-img-thumbnail {
            width: 100%;
            height: 200px; /* Fixed height for product images */
            object-fit: contain; /* Ensure image fits without cropping */
            padding: 10px;
        }
        .product-title {
            font-size: 1.1rem;
            font-weight: 600;
        }
        .product-price {
            color: #28a745; /* Price color */
            font-size: 1.2rem;
        }
        .footer {
            background-color: #212529 !important; /* Darker footer */
        }
        .footer a {
            color: #adb5bd !important;
        }
        .footer a:hover {
            color: #ffffff !important;
        }
    </style>
</head>
<body>
    <div class="top-bar d-none d-md-block">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="contact-info">
                <i class="bx bx-envelope me-1"></i> info@company.com
                <i class="bx bx-phone ms-3 me-1"></i> 010-020-0340
            </div>
            <div class="social-icons">
                <a href="#"><i class="bx bxl-facebook-square"></i></a>
                <a href="#"><i class="bx bxl-instagram-alt"></i></a>
                <a href="#"><i class="bx bxl-twitter"></i></a>
                <a href="#"><i class="bx bxl-linkedin-square"></i></a>
            </div>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg navbar-light bg-white py-3 border-bottom">
        <div class="container">
            <a class="navbar-brand logo h1 align-self-center" href="index.php">
              Singko eCommerce
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link-custom <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" aria-current="page" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link-custom <?php echo ($current_page == 'about.php') ? 'active' : ''; ?>" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link-custom <?php echo ($current_page == 'shop.php') ? 'active' : ''; ?>" href="shop.php">Shop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link-custom <?php echo ($current_page == 'contact.php') ? 'active' : ''; ?>" href="contact.php">Contact</a>
                    </li>
                </ul>

                <ul class="navbar-nav align-items-center">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link-custom dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bx bx-user me-1"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'seller and buyer'): ?>
                                    <li><a class="dropdown-item" href="seller/dashboard.php">Seller Dashboard</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="dashboard.php">User Dashboard</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link-custom btn btn-outline-dark rounded-pill px-4 me-2" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link-custom btn btn-dark rounded-pill px-4" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item ms-lg-3">
                        <a class="nav-link-custom cart-icon-link" href="my_cart.php">
                            <i class="bx bx-shopping-bag"></i>
                            <span class="badge bg-primary rounded-pill cart-count"><?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?></span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <main class="flex-grow-1"> <!-- This <main> tag starts here -->