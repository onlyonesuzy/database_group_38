<?php 
require_once('db_connect.php');
include_once("header.php"); 

$step = 1; // Step 1: Enter email, Step 2: Enter code, Step 3: Reset password
$error = $_GET['error'] ?? null;
$success = $_GET['success'] ?? null;
$email = '';
$verification_code = '';

// Check if we're in step 2 (code was generated)
if (isset($_SESSION['reset_email']) && isset($_SESSION['reset_code']) && isset($_SESSION['reset_expires'])) {
    $expires = new DateTime($_SESSION['reset_expires']);
    $now = new DateTime();
    
    if ($now < $expires) {
        $step = 2;
        $email = $_SESSION['reset_email'];
        $verification_code = $_SESSION['reset_code'];
    } else {
        // Expired, clear session
        unset($_SESSION['reset_email'], $_SESSION['reset_code'], $_SESSION['reset_expires'], $_SESSION['reset_user_id']);
    }
}

// Check if code was verified (step 3)
if (isset($_SESSION['code_verified']) && $_SESSION['code_verified'] === true) {
    $step = 3;
}
?>

<div class="container" style="max-width: 500px; margin-top: 50px;">
  
  <?php if ($success === 'password_reset'): ?>
    <div class="alert alert-success">
      <i class="fa fa-check-circle"></i> <strong>Password Reset Successfully!</strong> 
      You can now <a href="index.php">login</a> with your new password.
    </div>
  <?php endif; ?>
  
  <?php if ($error === 'invalid_email'): ?>
    <div class="alert alert-danger">
      <i class="fa fa-times-circle"></i> Please enter a valid email address.
    </div>
  <?php endif; ?>
  
  <?php if ($error === 'user_not_found'): ?>
    <div class="alert alert-danger">
      <i class="fa fa-times-circle"></i> No account found with that email address.
    </div>
  <?php endif; ?>
  
  <?php if ($error === 'wrong_code'): ?>
    <div class="alert alert-danger">
      <i class="fa fa-times-circle"></i> Incorrect verification code. Please try again.
    </div>
  <?php endif; ?>
  
  <?php if ($error === 'mismatch'): ?>
    <div class="alert alert-danger">
      <i class="fa fa-times-circle"></i> Passwords do not match.
    </div>
  <?php endif; ?>
  
  <?php if ($error === 'weak'): ?>
    <div class="alert alert-danger">
      <i class="fa fa-times-circle"></i> Password must be at least 6 characters.
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <h4 class="mb-0"><i class="fa fa-key"></i> Forgot Password</h4>
    </div>
    <div class="card-body">
      
      <?php if ($step === 1): ?>
        <!-- STEP 1: Enter Email -->
        <p class="text-muted">Enter your email address to receive a verification code.</p>
        
        <form method="POST" action="forgot_password_process.php">
          <input type="hidden" name="action" value="send_code">
          <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
          </div>
          <button type="submit" class="btn btn-primary form-control">
            <i class="fa fa-paper-plane"></i> Get Verification Code
          </button>
        </form>
        
      <?php elseif ($step === 2): ?>
        <!-- STEP 2: Show Code & Verify -->
        <div class="alert alert-info">
          <i class="fa fa-envelope"></i> Verification code for <strong><?php echo htmlspecialchars($email); ?></strong>
        </div>
        
        <div class="text-center my-4">
          <p class="text-muted mb-2">Your verification code is:</p>
          <div class="display-4 font-weight-bold text-success" style="letter-spacing: 10px; font-family: monospace;">
            <?php echo $verification_code; ?>
          </div>
          <small class="text-muted">Code expires in 10 minutes</small>
        </div>
        
        <hr>
        
        <form method="POST" action="forgot_password_process.php">
          <input type="hidden" name="action" value="verify_code">
          <div class="form-group">
            <label for="code">Enter Verification Code</label>
            <input type="text" class="form-control text-center" id="code" name="code" 
                   placeholder="Enter 6-digit code" required maxlength="6" 
                   style="font-size: 1.5rem; letter-spacing: 5px; font-family: monospace;">
          </div>
          <button type="submit" class="btn btn-primary form-control">
            <i class="fa fa-check"></i> Verify Code
          </button>
        </form>
        
        <div class="text-center mt-3">
          <a href="forgot_password_process.php?action=cancel">Start Over</a>
        </div>
        
      <?php elseif ($step === 3): ?>
        <!-- STEP 3: Reset Password -->
        <div class="alert alert-success">
          <i class="fa fa-check-circle"></i> Code verified! Now set your new password.
        </div>
        
        <form method="POST" action="forgot_password_process.php">
          <input type="hidden" name="action" value="reset_password">
          
          <div class="form-group">
            <label for="password">New Password</label>
            <input type="password" class="form-control" id="password" name="password" 
                   placeholder="Enter new password" required minlength="6">
            <small class="form-text text-muted">Minimum 6 characters</small>
          </div>
          
          <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                   placeholder="Confirm new password" required minlength="6">
          </div>
          
          <button type="submit" class="btn btn-success form-control">
            <i class="fa fa-save"></i> Reset Password
          </button>
        </form>
        
      <?php endif; ?>
      
      <div class="text-center mt-3">
        <a href="index.php">Back to Login</a>
      </div>
    </div>
  </div>
  
</div>

<?php include_once("footer.php"); ?>
