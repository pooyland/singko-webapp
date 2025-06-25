<?php
require_once '../db_config.php';
require_once 'includes/admin_header.php';

$message = '';

// Handle actions (e.g., delete user, change role)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $user_id = $_POST['user_id'];

    if ($action === 'delete') {
        // Prevent deleting self or other admins easily
        if ($user_id == $_SESSION['user_id']) {
            $message = '<div class="alert alert-danger">You cannot delete your own admin account through this interface.</div>';
        } else {
            // Basic delete - in a real app, handle cascading deletes or soft deletes carefully
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role != 'admin'"); // Prevents accidental admin deletion
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = '<div class="alert alert-success">User deleted successfully (if not admin).</div>';
            } else {
                $message = '<div class="alert alert-danger">Error deleting user or user is an admin.</div>';
            }
            $stmt->close();
        }
    } elseif ($action === 'change_role' && isset($_POST['new_role'])) {
        $new_role = $_POST['new_role'];
        if ($user_id == $_SESSION['user_id'] && $new_role !== 'admin') {
             $message = '<div class="alert alert-danger">You cannot change your own admin role.</div>';
        } else {
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_role, $user_id);
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">User role updated successfully.</div>';
            } else {
                $message = '<div class="alert alert-danger">Error updating user role.</div>';
            }
            $stmt->close();
        }
    }
}

// Fetch all users
$sql = "SELECT user_id, full_name, email, username, role, registration_date FROM users ORDER BY registration_date DESC";
$result = $conn->query($sql);
?>

<h1 class="mb-4">Manage Users</h1>

<?php echo $message; ?>

<div class="admin-card">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Username</th>
                <th>Role</th>
                <th>Registration Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['user_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['role']); ?></td>
                        <td><?php echo htmlspecialchars($row['registration_date']); ?></td>
                        <td>
                            <form action="manage_users.php" method="POST" class="d-inline">
                                <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                <select name="new_role" class="form-select form-select-sm d-inline-block w-auto">
                                    <option value="buyer" <?php echo ($row['role'] == 'buyer') ? 'selected' : ''; ?>>Buyer</option>
                                    <option value="seller" <?php echo ($row['role'] == 'seller') ? 'selected' : ''; ?>>Seller</option>
                                    <option value="admin" <?php echo ($row['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                </select>
                                <button type="submit" name="action" value="change_role" class="btn btn-info btn-sm">Change Role</button>
                            </form>
                            <?php if ($row['user_id'] != $_SESSION['user_id']): // Cannot delete self ?>
                                <form action="manage_users.php" method="POST" class="d-inline ms-2" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                    <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                    <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                echo '<tr><td colspan="7" class="text-center">No users found.</td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>

<?php
require_once 'includes/admin_footer.php';
$conn->close();
?>