<?php
// register.php
require_once 'db_config.php'; // Ensure your database connection is included

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$redirect_script = ''; // Initialize variable for JavaScript redirect

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $_POST['full_name'];
    $address = $_POST['address'];
    $contact_number = $_POST['contact_number'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $password = $_POST['password']; // IMPORTANT: In a real application, you MUST hash this password! e.g., password_hash($password, PASSWORD_DEFAULT);

    // Basic validation
    if (empty($full_name) || empty($address) || empty($contact_number) || empty($email) || empty($username) || empty($password)) {
        $message = '<div class="alert alert-danger">All fields are required.</div>';
    } else {
        // Check if username or email already exists
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        if ($stmt_check === false) {
            $message = '<div class="alert alert-danger">Database error: ' . htmlspecialchars($conn->error) . '</div>';
        } else {
            $stmt_check->bind_param("ss", $username, $email);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $message = '<div class="alert alert-danger">Username or Email already exists.</div>';
            } else {
                // Hash the password for security (recommended, uncomment and use in production)
                // $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert new user
                // Using plain text password for now, as per your previous code's implied behavior.
                // Re-emphasizing: HASH PASSWORDS IN PRODUCTION!
                $stmt_insert = $conn->prepare("INSERT INTO users (full_name, address, contact_number, email, username, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                // Set default role for new registrations as 'seller and buyer'
                $default_role = 'seller and buyer'; 
                
                if ($stmt_insert === false) {
                    $message = '<div class="alert alert-danger">Database error during insert preparation: ' . htmlspecialchars($conn->error) . '</div>';
                } else {
                    // Corrected bind_param call to include $username
                    $stmt_insert->bind_param("sssssss", $full_name, $address, $contact_number, $email, $username, $password, $default_role); 

                    if ($stmt_insert->execute()) {
                        // Set the message to display on the current page
                        $message = '<div class="alert alert-success">Registration successful! Redirecting to login...</div>';
                        
                        // Set JavaScript for delayed redirect
                        $redirect_script = '
                            <script>
                                setTimeout(function() {
                                    window.location.href = "login.php";
                                }, 3000); // Redirect after 3 seconds
                            </script>
                        ';
                        // Do NOT use header() here, as we want to display the message first
                    } else {
                        $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($stmt_insert->error) . '</div>';
                    }
                    $stmt_insert->close();
                }
            }
            $stmt_check->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register for Singko eCommerce</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9O9FeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css"> <!-- Path adjusted to 'css/style.css' -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <style>
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
            max-width: 450px; /* Adjust max-width for better form readability */
        }
        .auth-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .app-logo-text {
            font-size: 2.5rem;
            font-weight: 700;
            color: #007bff; /* Primary color */
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
            border-color: #007bff;
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }
        .btn-primary-sneat {
            background-color: #007bff; /* Primary color */
            border-color: #007bff;
            color: #fff;
            padding: 0.75rem 1.25rem;
            border-radius: 0.375rem;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
        }
        .btn-primary-sneat:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .auth-links {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .auth-links a {
            color: #007bff;
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
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <span class="app-logo-text">Singko</span>
        </div>
        <h4>Start your adventure with Singko eCommerce! 👋</h4>
        <p>Create your account and unlock awesome features</p>

        <?php echo $message; ?>

        <form action="register.php" method="POST">
            <div class="form-group-custom">
                <label for="full_name" class="form-label">FULL NAME</label>
                <input type="text" class="form-control" id="full_name" name="full_name"  required>
            </div>
            <div class="form-group-custom">
                <label for="address" class="form-label">ADDRESS</label>
                <input type="text" class="form-control" id="address" name="address"  required>
            </div>
            <div class="form-group-custom">
                <label for="contact_number" class="form-label">CONTACT NUMBER</label>
                <input type="text" class="form-control" id="contact_number" name="contact_number"  required>
            </div>
            <div class="form-group-custom">
                <label for="email" class="form-label">EMAIL ADDRESS</label>
                <input type="email" class="form-control" id="email" name="email"  required>
            </div>
            <div class="form-group-custom">
                <label for="username" class="form-label">USERNAME</label>
                <input type="text" class="form-control" id="username" name="username"  required>
            </div>
            <div class="form-group-custom">
                <label for="password" class="form-label">PASSWORD</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary-sneat w-100 mt-3">Register</button>
        </form>

        <p class="text-center mt-3 auth-links">
            <span>Already have an account?</span>
            <a href="login.php">
                <span>Login here</span>
            </a>
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <?php echo $redirect_script; // Echo the redirect script here ?>
</body>
</html>
<?php $conn->close(); ?>
