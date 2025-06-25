<?php
require_once '../db_config.php'; // Path relative to admin folder

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $message = '<div class="alert alert-danger">Please enter username and password.</div>';
    } else {
        $stmt = $conn->prepare("SELECT user_id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($user_id, $db_username, $db_password, $role);
            $stmt->fetch();

            // NOTE: Directly comparing plain text passwords as requested
            if ($password === $db_password) {
                if ($role === 'admin') {
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $db_username;
                    $_SESSION['role'] = $role;
                    header("location: dashboard.php"); // Redirect to admin dashboard
                    exit();
                } else {
                    $message = '<div class="alert alert-danger">You do not have administrative privileges.</div>';
                }
            } else {
                $message = '<div class="alert alert-danger">Invalid username or password.</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Invalid username or password.</div>';
        }
        $stmt->close();
    }
}

// Admin login form has minimal header/footer for simplicity
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f2f4f8;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 40px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .login-container h2 {
            margin-bottom: 30px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Admin Login</h2>
        <?php echo $message; ?>
        <form action="index.php" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
            <p class="text-center mt-3"><a href="/marketplace/login.php">Back to Marketplace Login</a></p>
        </form>
    </div>
</body>
</html>
<?php $conn->close(); ?>