<?php
// login.php

// 1. Start session at the very beginning, before any includes or output.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Include database configuration.
// This file should now define the $conn object for MySQLi database connection.
require_once 'db_config.php'; // This correctly provides $conn (now MySQLi)

$message = ''; // Initialize message variable

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and trim user input
    $username_or_email = trim($_POST['username']);
    $password = $_POST['password']; // Password should not be trimmed or sanitized here

    if (empty($username_or_email) || empty($password)) {
        $message = '<div class="alert alert-danger">Please enter username/email and password.</div>';
    } else {
        try {
            // MySQLi: Prepare statement
            $stmt = $conn->prepare("SELECT user_id, username, password, role FROM users WHERE username = ? OR email = ?");

            // Check if prepare was successful
            if ($stmt === false) {
                // Log MySQLi error, not PDO errorInfo()
                error_log("Login prepare statement error: " . $conn->error);
                $message = '<div class="alert alert-danger">A database error occurred during query preparation. Please try again later.</div>';
            } else {
                // MySQLi: Bind parameters (s for string)
                $stmt->bind_param("ss", $username_or_email, $username_or_email);
                
                // MySQLi: Execute the statement
                $stmt->execute();
                
                // MySQLi: Get the result set
                $result = $stmt->get_result();
                
                // MySQLi: Fetch the user data
                $user = $result->fetch_assoc(); // Fetch as associative array

                if ($user) {
                    // IMPORTANT SECURITY WARNING: Directly comparing plain text passwords.
                    // In a production environment, ALWAYS use password_verify($password, $user['password'])
                    // where $user['password'] is a hashed password from your database.
                    // Example: if (password_verify($password, $user['password'])) { ... }
                    if ($password === $user['password']) { // Direct comparison as per instruction
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role']; // Store user role in session

                        // Redirect based on role
                        if ($user['role'] === 'admin') {
                            header("Location: admin/dashboard.php");
                        } elseif ($user['role'] === 'seller' || $user['role'] === 'seller and buyer') {
                            header("Location: dashboard.php"); // Redirect to the user dashboard
                        } else {
                            // Default redirection for other roles or general users (e.g., 'buyer')
                            header("Location: index.php"); 
                        }
                        exit(); // Crucial: stop script execution after header redirect
                    } else {
                        $message = '<div class="alert alert-danger">Invalid username/email or password.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger">Invalid username/email or password.</div>';
                }
            }
        } catch (Exception $e) { // Catch generic Exception now, as PDOException is gone
            // Log the error for debugging purposes
            error_log("Login database error: " . $e->getMessage());
            $message = '<div class="alert alert-danger">A database error occurred. Please try again later.</div>';
        }
    }
}

// 3. Include HTML header AFTER all PHP logic and potential redirects
// (No session_start() here as it's already at the very top)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login to Singko eCommerce</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9O9FeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css"> <!-- Path adjusted to 'css/style.css' -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <style>
        /* Shared auth page styles - consider moving to a separate CSS file */
        body.auth-page {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f5f5f9; /* Light background for auth pages */
        }
        .auth-card {
            background-color: #fff;
            padding: 2.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            width: 100%;
            max-width: 400px; /* Adjust max-width for better form readability */
        }
        .auth-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .app-logo-text {
            font-size: 2.5rem;
            font-weight: 700;
            color: #007bff; /* Reverted to a standard blue for a clean look */
        }
        .auth-card h4 {
            font-weight: 600;
            text-align: center;
            margin-bottom: 0.5rem;
            color: #343a40;
        }
        .auth-card p {
            text-align: center;
            color: #6c757d;
            margin-bottom: 2rem;
        }
        .form-group-custom {
            margin-bottom: 1.5rem;
        }
        .form-group-custom label {
            font-weight: 500;
            display: block;
            margin-bottom: 0.5rem;
            color: #495057;
        }
        .form-control {
            border-radius: 0.375rem; /* Slightly rounded corners */
            padding: 0.75rem 1rem;
            border: 1px solid #ced4da;
        }
        .form-control:focus {
            border-color: #007bff; /* Reverted to standard blue focus */
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25); /* Reverted to standard blue shadow */
        }
        .btn-primary-sneat {
            background-color: #007bff; /* Reverted to standard blue primary button */
            border-color: #007bff;
            color: #fff;
            padding: 0.75rem 1.25rem;
            border-radius: 0.375rem;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
        }
        .btn-primary-sneat:hover {
            background-color: #0056b3; /* Reverted to standard blue hover */
            border-color: #0056b3;
        }
        .auth-links {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .auth-links a {
            color: #007bff; /* Reverted to standard blue link color */
            text-decoration: none;
            font-weight: 500;
        }
        .auth-links a:hover {
            text-decoration: underline;
        }
        .alert {
            margin-bottom: 1.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 0.375rem;
            font-size: 0.9rem;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        /* Specific alignment for the "Remember Me" checkbox */
        .form-check {
            display: flex; /* Use flexbox */
            align-items: center; /* Align items vertically in the middle */
            margin-top: 1rem; /* Add some top margin for spacing */
            margin-bottom: 1.5rem; /* Ensure consistent spacing with other form groups */
        }
        .form-check-input {
            margin-right: 0.5rem; /* Space between checkbox and label */
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <span class="app-logo-text">Singko</span>
        </div>
        <h4>Welcome to Singko eCommerce! 👋</h4>
        <p>Please sign-in to your account and start the adventure</p>

        <?php echo $message; ?>

        <form action="login.php" method="POST">
            <div class="form-group-custom">
                <label for="username" class="form-label">EMAIL OR USERNAME</label>
                <input type="text" class="form-control" id="username" name="username" autofocus required>
            </div>
            
            <div class="form-group-custom">
                
                <div class="input-group input-group-merge">
                    <input type="password" id="password" class="form-control" name="password" aria-describedby="password" required>
                </div>
                <div class="d-flex justify-content-between align-items-center"> 
                    <label class="form-label" for="password">PASSWORD</label>
                    <a href="#" class="forgot-password-link">Forgot Password?</a>
                </div>
            </div>
            
            <!-- REVISED REMEMBER ME CHECKBOX SECTION -->
            <div class="form-check"> 
                <input class="form-check-input" type="checkbox" id="remember-me" name="remember-me">
                <label class="form-check-label" for="remember-me"> Remember Me </label>
            </div>
            <!-- END REVISED SECTION -->

            <button type="submit" class="btn btn-primary-sneat w-100">Sign in</button>
        </form>

        <p class="text-center mt-3 auth-links">
            <span>New on our platform?</span>
            <a href="register.php">
                <span>Create an account</span>
            </a>
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
