<?php 
require_once('db_connect.php');
include_once("header.php"); 

$token = $_GET['token'] ?? '';
$valid_token = false;
$user_id = null;

if (!empty($token)) {
    // Verify token exists and hasn't expired
    $stmt = $conn->prepare("SELECT user_id, reset_token_expires FROM Users WHERE reset_token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user) {
        $expires = new DateTime($user['reset_token_expires']);
        $now = new DateTime();
        
        if ($now < $expires) {
            $valid_token = true;
            $user_id = $user['user_id'];
        }
    }
}

$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;
?>

<div class="container" style="max-width: 500px; margin-top: 50px;">
  
  <?php if ($success === 'password_reset'): ?>
    <div class="alert alert-success">
      <i class="fa fa-check-circle"></i> <strong>Password Reset Successfully!</strong> 
      You can now <a href="index.php">login</a> with your new password.
    </div>
  <?php elseif (!$valid_token): ?>
    <div class="alert alert-danger">
      <i class="fa fa-times-circle"></i> <strong>Invalid or Expired Link</strong>
      <p class="mb-0">This password reset link is invalid or has expired. Please request a new one.</p>
    </div>
    <div class="text-center mt-3">
      <a href="forgot_password.php" class="btn btn-primary">Request New Reset Link</a>
    </div>
  <?php else: ?>
  
    <?php if ($error === 'mismatch'): ?>
      <div class="alert alert-danger">
        <i class="fa fa-times-circle"></i> Passwords do not match. Please try again.
      </div>
    <?php endif; ?>
    
    <?php if ($error === 'weak'): ?>
      <div class="alert alert-danger">
        <i class="fa fa-times-circle"></i> Password must be at least 6 characters long.
      </div>
    <?php endif; ?>
    
    <div class="card">
      <div class="card-header">
        <h4 class="mb-0"><i class="fa fa-lock"></i> Reset Your Password</h4>
      </div>
      <div class="card-body">
        <p class="text-muted">Enter your new password below.</p>
        
        <form method="POST" action="reset_password_process.php">
          <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
          
          <div class="form-group">
            <label for="password">New Password</label>
            <input type="password" class="form-control" id="password" name="password" 
                   placeholder="Enter new password" required minlength="6">
            <small class="form-text text-muted">Minimum 6 characters</small>
          </div>
          
          <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                   placeholder="Confirm new password" required minlength="6">
          </div>
          
          <button type="submit" class="btn btn-primary form-control">
            <i class="fa fa-save"></i> Reset Password
          </button>
        </form>
      </div>
    </div>
    
  <?php endif; ?>
  
</div>

<?php include_once("footer.php"); ?>

