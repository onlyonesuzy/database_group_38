<?php
  // FIXME: At the moment, I've allowed these values to be set manually.
  // But eventually, with a database, these should be set automatically
  // ONLY after the user's login credentials have been verified via a 
  // database query.
  session_start();
  require_once(__DIR__ . '/db_connect.php');
  require_once(__DIR__ . '/auction_helpers.php');
  
  $notification_count = 0;
  if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] && isset($_SESSION['user_id'])) {
      $notification_count = getUnreadNotificationCount($conn, $_SESSION['user_id']);
  }
?>


<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="theme-color" content="#0D0D0D">
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Bootstrap and FontAwesome CSS -->
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

  <!-- Custom CSS file -->
  <link rel="stylesheet" href="css/custom.css?v=<?php echo time(); ?>">

  <title>DataBid</title>
</head>


<body style="background: linear-gradient(135deg, #1A1446 0%, #5F4BFF 50%, #9A6BFF 100%); color: #FFFFFF;">

<!-- Navbars -->
<nav class="navbar navbar-expand-lg navbar-light">
  <a class="navbar-brand" href="index.php">DataBid</a>
  <ul class="navbar-nav ml-auto">
    <li class="nav-item">
    
<?php
  // Displays either login or logout on the right, depending on user's
  // current status (session).
  if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true) {
    // Notification bell
    echo '<a class="nav-link d-inline position-relative" href="notifications.php" title="Notifications">';
    echo '<i class="fa fa-bell"></i>';
    if ($notification_count > 0) {
      echo '<span class="badge badge-danger" style="position:absolute;top:0;right:-5px;font-size:10px;">' . $notification_count . '</span>';
    }
    echo '</a>';
    echo '<a class="nav-link d-inline ml-2" href="myaccount.php"><i class="fa fa-user"></i> My Account</a>';
    echo '<a class="nav-link d-inline ml-2" href="logout.php">Logout</a>';
  }
  else {
    echo '<button type="button" class="btn nav-link" data-toggle="modal" data-target="#loginModal">Login</button>';
  }
?>

    </li>
  </ul>
</nav>
<nav class="navbar navbar-expand-lg navbar-light">
  <ul class="navbar-nav align-middle">
	<li class="nav-item mx-1">
      <a class="nav-link" href="browse.php">Browse</a>
    </li>
<?php
  if (isset($_SESSION['account_type']) && in_array($_SESSION['account_type'], ['buyer','both'])) {
  echo('
	<li class="nav-item mx-1">
      <a class="nav-link" href="mybids.php">My Bids</a>
    </li>
	<li class="nav-item mx-1">
      <a class="nav-link" href="recommendations.php">Recommended</a>
    </li>
	<li class="nav-item mx-1">
      <a class="nav-link" href="mywatchlist.php">Watchlist</a>
    </li>');
  }
  if (isset($_SESSION['account_type']) && in_array($_SESSION['account_type'], ['seller','both'])) {
  echo('
	<li class="nav-item mx-1">
      <a class="nav-link" href="mylistings.php">My Listings</a>
    </li>
	<li class="nav-item ml-3">
      <a class="nav-link btn border-light" href="create_auction.php">+ Create auction</a>
    </li>');
  }
?>
  </ul>
</nav>

<?php
// Display login success/error messages
$login_success = $_GET['login_success'] ?? null;
$login_error = $_GET['login_error'] ?? null;

if ($login_success): ?>
<div class="container mt-3">
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fa fa-check-circle"></i> <strong>Welcome back, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</strong> You have successfully logged in.
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
</div>
<?php endif; ?>

<?php if ($login_error === 'wrong_password'): ?>
<div class="container mt-3">
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fa fa-times-circle"></i> <strong>Login failed!</strong> Incorrect password. Please try again.
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
</div>
<?php endif; ?>

<?php if ($login_error === 'user_not_found'): ?>
<div class="container mt-3">
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fa fa-times-circle"></i> <strong>Login failed!</strong> User not found. Please check your email or <a href="register.php">create an account</a>.
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
</div>
<?php endif; ?>

<?php if ($login_error === 'empty_fields'): ?>
<div class="container mt-3">
  <div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fa fa-exclamation-triangle"></i> Please fill in all fields.
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
</div>
<?php endif; ?>

<!-- Login modal -->
<div class="modal fade" id="loginModal">
  <div class="modal-dialog">
    <div class="modal-content">

      <!-- Modal Header -->
      <div class="modal-header">
        <h4 class="modal-title">Login</h4>
      </div>

      <!-- Modal body -->
      <div class="modal-body">
        <form method="POST" action="login_result.php">
          <div class="form-group">
            <label for="email">Email</label>
            <input type="text" class="form-control" id="email" name="email" placeholder="Email">
          </div>
          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Password">
          </div>
          <button type="submit" class="btn btn-primary form-control">Sign in</button>
        </form>
        <div class="text-center mt-3">
          <a href="forgot_password.php">Forgot password?</a>
        </div>
        <hr>
        <div class="text-center">or <a href="register.php">create an account</a></div>
      </div>

    </div>
  </div>
</div> <!-- End modal -->