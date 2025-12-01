<?php include_once("header.php")?>
<?php require_once("db_connect.php"); ?>

<div class="container" style="max-width: 600px;">

<h2 class="my-3">My Account</h2>

<?php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo '<div class="alert alert-danger">Please login to access your account settings.</div>';
    include_once("footer.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $new_username = trim($_POST['username'] ?? '');
        $new_email = trim($_POST['email'] ?? '');
        
        // Validation
        $errors = [];
        
        if (empty($new_username)) {
            $errors[] = 'Username is required.';
        } elseif (!preg_match('/^[a-zA-Z0-9_\-\.]{3,}$/', $new_username)) {
            $errors[] = 'Username must be at least 3 characters and contain only letters, numbers, dashes, underscores, or dots.';
        }
        
        if (empty($new_email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        
        // Check if username or email already taken by another user
        if (empty($errors)) {
            $check_stmt = $conn->prepare("SELECT user_id FROM Users WHERE (username = ? OR email = ?) AND user_id != ?");
            $check_stmt->bind_param("ssi", $new_username, $new_email, $user_id);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                $errors[] = 'Username or email is already taken by another user.';
            }
            $check_stmt->close();
        }
        
        if (empty($errors)) {
            $update_stmt = $conn->prepare("UPDATE Users SET username = ?, email = ? WHERE user_id = ?");
            $update_stmt->bind_param("ssi", $new_username, $new_email, $user_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['username'] = $new_username;
                $success_message = 'Profile updated successfully!';
            } else {
                $error_message = 'Failed to update profile. Please try again.';
            }
            $update_stmt->close();
        } else {
            $error_message = implode('<br>', $errors);
        }
    }
    
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $errors = [];
        
        if (empty($current_password)) {
            $errors[] = 'Current password is required.';
        }
        
        if (empty($new_password)) {
            $errors[] = 'New password is required.';
        } elseif (strlen($new_password) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match.';
        }
        
        // Verify current password
        if (empty($errors)) {
            $pwd_stmt = $conn->prepare("SELECT password FROM Users WHERE user_id = ?");
            $pwd_stmt->bind_param("i", $user_id);
            $pwd_stmt->execute();
            $pwd_result = $pwd_stmt->get_result();
            $user_data = $pwd_result->fetch_assoc();
            $pwd_stmt->close();
            
            if (!password_verify($current_password, $user_data['password'])) {
                $errors[] = 'Current password is incorrect.';
            }
        }
        
        if (empty($errors)) {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pwd_stmt = $conn->prepare("UPDATE Users SET password = ? WHERE user_id = ?");
            $update_pwd_stmt->bind_param("si", $new_password_hash, $user_id);
            
            if ($update_pwd_stmt->execute()) {
                $success_message = 'Password changed successfully!';
            } else {
                $error_message = 'Failed to change password. Please try again.';
            }
            $update_pwd_stmt->close();
        } else {
            $error_message = implode('<br>', $errors);
        }
    }
}

// Fetch current user data
$user_stmt = $conn->prepare("SELECT username, email, role, created_at FROM Users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

if (!$user) {
    echo '<div class="alert alert-danger">User not found.</div>';
    include_once("footer.php");
    exit();
}
?>

<?php if ($success_message): ?>
    <div class="alert alert-success"><?php echo $success_message; ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Profile Information</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" 
                       value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Account Type</label>
                <input type="text" class="form-control" value="<?php echo ucfirst(htmlspecialchars($user['role'])); ?>" disabled>
                <small class="text-muted">Contact support to change your account type.</small>
            </div>
            
            <div class="form-group">
                <label>Member Since</label>
                <input type="text" class="form-control" value="<?php echo (new DateTime($user['created_at']))->format('j M Y'); ?>" disabled>
            </div>
            
            <button type="submit" class="btn btn-primary">Update Profile</button>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Change Password</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" class="form-control" id="current_password" name="current_password" required>
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required>
                <small class="text-muted">Must be at least 8 characters.</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="btn btn-warning">Change Password</button>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Close Account</h5>
    </div>
    <div class="card-body">
        <p class="text-muted">Permanently delete your account and all associated data. This action cannot be reversed.</p>
        <button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#deleteAccountModal">
            Delete My Account
        </button>
    </div>
</div>

</div>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Delete Account</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete your account? This action cannot be undone.</p>
                <p>All your auctions, bids, and data will be permanently removed.</p>
                <form method="POST" action="delete_account.php">
                    <div class="form-group">
                        <label for="delete_password">Enter your password to confirm:</label>
                        <input type="password" class="form-control" id="delete_password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-danger">Yes, Delete My Account</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once("footer.php")?>

