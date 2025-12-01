<?php include_once("header.php"); ?>
<?php require_once("db_connect.php"); ?>

<?php
// Must be logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$auction_id = $_GET['auction_id'] ?? null;
$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;

if (!$auction_id || !ctype_digit((string)$auction_id)) {
    echo '<div class="container my-5"><div class="alert alert-danger">Invalid auction.</div></div>';
    include_once("footer.php");
    exit();
}

$auction_id = (int)$auction_id;

// Get auction info
$stmt = $conn->prepare("SELECT a.*, u.username as seller_name FROM Auctions a JOIN Users u ON u.user_id = a.user_id WHERE a.auction_id = ?");
$stmt->bind_param('i', $auction_id);
$stmt->execute();
$auction = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$auction) {
    echo '<div class="container my-5"><div class="alert alert-danger">Auction not found.</div></div>';
    include_once("footer.php");
    exit();
}

// Check if user can rate
$can_rate = canRateSeller($conn, $auction_id, $user_id);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_rate) {
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    
    if ($rating >= 1 && $rating <= 5 && submitSellerRating($conn, $auction_id, $auction['user_id'], $user_id, $rating, $comment)) {
        // Notify seller
        $star_text = $rating . ' star' . ($rating > 1 ? 's' : '');
        $notif_title = 'New ' . $star_text . ' Rating Received';
        $notif_message = 'You received a ' . $star_text . ' rating for "' . $auction['title'] . '".';
        createNotification($conn, $auction['user_id'], 'rating', $notif_title, $notif_message, 'seller_profile.php?id=' . $auction['user_id']);
        
        header('Location: rate_seller.php?auction_id=' . $auction_id . '&success=rated');
        exit();
    } else {
        header('Location: rate_seller.php?auction_id=' . $auction_id . '&error=failed');
        exit();
    }
}
?>

<div class="container my-4" style="max-width: 600px;">
  
  <?php if ($success === 'rated'): ?>
    <div class="alert alert-success">
      <i class="fa fa-check-circle"></i> <strong>Thank you!</strong> Your rating has been submitted successfully.
      <div class="mt-2">
        <a href="listing.php?item_id=<?php echo $auction_id; ?>" class="btn btn-sm btn-outline-success">Back to Listing</a>
        <a href="mybids.php" class="btn btn-sm btn-outline-secondary">My Bids</a>
      </div>
    </div>
  <?php elseif (!$can_rate): ?>
    <div class="alert alert-warning">
      <i class="fa fa-info-circle"></i> You cannot rate this seller. Either you didn't win this auction, or you've already submitted a rating.
      <div class="mt-2">
        <a href="listing.php?item_id=<?php echo $auction_id; ?>" class="btn btn-sm btn-outline-warning">Back to Listing</a>
      </div>
    </div>
  <?php else: ?>
    
    <?php if ($error === 'failed'): ?>
      <div class="alert alert-danger">
        <i class="fa fa-times-circle"></i> Failed to submit rating. Please try again.
      </div>
    <?php endif; ?>
    
    <div class="card">
      <div class="card-header">
        <h4 class="mb-0"><i class="fa fa-star"></i> Rate Seller</h4>
      </div>
      <div class="card-body">
        
        <div class="mb-4">
          <h5><?php echo htmlspecialchars($auction['title']); ?></h5>
          <p class="text-muted mb-0">
            Seller: <strong><?php echo htmlspecialchars($auction['seller_name']); ?></strong>
          </p>
        </div>
        
        <form method="POST" id="ratingForm">
          <div class="form-group">
            <label class="mb-3">How would you rate this seller?</label>
            
            <div class="star-rating-input text-center py-4" style="background: #1a1a1a; border-radius: 12px;">
              <div class="stars-container" style="font-size: 3rem;">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <i class="fa fa-star-o star-select" data-value="<?php echo $i; ?>" style="cursor: pointer; margin: 0 5px;"></i>
                <?php endfor; ?>
              </div>
              <input type="hidden" name="rating" id="ratingValue" required>
              <p class="rating-text text-muted mt-3 mb-0">Click to rate</p>
            </div>
          </div>
          
          <div class="form-group">
            <label for="comment">Comment (optional)</label>
            <textarea class="form-control" id="comment" name="comment" rows="3" 
                      placeholder="Share details about your experience..."></textarea>
          </div>
          
          <button type="submit" class="btn btn-primary form-control" id="submitBtn" disabled>
            <i class="fa fa-paper-plane"></i> Submit Rating
          </button>
        </form>
        
      </div>
    </div>
    
  <?php endif; ?>
  
</div>

<style>
.star-select {
  color: #404040;
  transition: all 0.2s ease;
}
.star-select:hover,
.star-select.hovered {
  color: #F2C94C;
  transform: scale(1.1);
}
.star-select.selected {
  color: #F2C94C;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const stars = document.querySelectorAll('.star-select');
  const ratingInput = document.getElementById('ratingValue');
  const ratingText = document.querySelector('.rating-text');
  const submitBtn = document.getElementById('submitBtn');
  
  const ratingLabels = {
    1: 'Poor - Very unsatisfied',
    2: 'Fair - Could be better',
    3: 'Good - Satisfactory',
    4: 'Very Good - Happy with purchase',
    5: 'Excellent - Highly recommended!'
  };
  
  stars.forEach(star => {
    star.addEventListener('mouseenter', function() {
      const val = parseInt(this.dataset.value);
      stars.forEach((s, idx) => {
        if (idx < val) {
          s.classList.add('hovered');
          s.classList.remove('fa-star-o');
          s.classList.add('fa-star');
        } else {
          s.classList.remove('hovered');
          if (!s.classList.contains('selected')) {
            s.classList.remove('fa-star');
            s.classList.add('fa-star-o');
          }
        }
      });
      ratingText.textContent = ratingLabels[val];
    });
    
    star.addEventListener('mouseleave', function() {
      const currentRating = parseInt(ratingInput.value) || 0;
      stars.forEach((s, idx) => {
        s.classList.remove('hovered');
        if (idx < currentRating) {
          s.classList.add('fa-star');
          s.classList.remove('fa-star-o');
        } else {
          s.classList.remove('fa-star');
          s.classList.add('fa-star-o');
        }
      });
      if (currentRating > 0) {
        ratingText.textContent = ratingLabels[currentRating];
      } else {
        ratingText.textContent = 'Click to rate';
      }
    });
    
    star.addEventListener('click', function() {
      const val = parseInt(this.dataset.value);
      ratingInput.value = val;
      submitBtn.disabled = false;
      
      stars.forEach((s, idx) => {
        s.classList.remove('selected');
        if (idx < val) {
          s.classList.add('selected');
          s.classList.add('fa-star');
          s.classList.remove('fa-star-o');
        } else {
          s.classList.remove('fa-star');
          s.classList.add('fa-star-o');
        }
      });
      ratingText.textContent = ratingLabels[val];
    });
  });
});
</script>

<?php include_once("footer.php"); ?>
