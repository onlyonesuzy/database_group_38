<?php include_once("header.php"); ?>
<?php require_once("utilities.php"); ?>
<?php require_once("db_connect.php"); ?>

<?php
$seller_id = $_GET['id'] ?? null;

if (!$seller_id || !ctype_digit((string)$seller_id)) {
    echo '<div class="container my-5"><div class="alert alert-danger">Invalid seller ID.</div></div>';
    include_once("footer.php");
    exit();
}

$seller_id = (int)$seller_id;

// Get seller info
$stmt = $conn->prepare("SELECT user_id, username, created_at FROM Users WHERE user_id = ?");
$stmt->bind_param('i', $seller_id);
$stmt->execute();
$seller = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$seller) {
    echo '<div class="container my-5"><div class="alert alert-danger">Seller not found.</div></div>';
    include_once("footer.php");
    exit();
}

// Get seller rating
$rating = getSellerRating($conn, $seller_id);

// Get seller's active listings
$listings_stmt = $conn->prepare("
    SELECT a.*, 
           COALESCE(b.max_bid, a.start_price) AS current_price,
           COALESCE(b.bid_count, 0) AS bid_count
    FROM Auctions a
    LEFT JOIN (
        SELECT auction_id, MAX(bid_amount) as max_bid, COUNT(*) as bid_count
        FROM Bids GROUP BY auction_id
    ) b ON b.auction_id = a.auction_id
    WHERE a.user_id = ? AND a.end_time > NOW()
    ORDER BY a.end_time ASC
");
$listings_stmt->bind_param('i', $seller_id);
$listings_stmt->execute();
$active_listings = $listings_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$listings_stmt->close();

// Get recent ratings
$ratings_stmt = $conn->prepare("
    SELECT r.*, u.username as buyer_name, a.title as auction_title
    FROM Ratings r
    JOIN Users u ON u.user_id = r.buyer_id
    JOIN Auctions a ON a.auction_id = r.auction_id
    WHERE r.seller_id = ?
    ORDER BY r.created_at DESC
    LIMIT 20
");
$ratings_stmt->bind_param('i', $seller_id);
$ratings_stmt->execute();
$recent_ratings = $ratings_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ratings_stmt->close();

$member_since = new DateTime($seller['created_at']);
?>

<div class="container my-4">
  
  <!-- Seller Header -->
  <div class="card mb-4">
    <div class="card-body">
      <div class="row">
        <div class="col-md-8">
          <div class="d-flex align-items-center">
            <i class="fa fa-user-circle fa-4x mr-3 text-info"></i>
            <div>
              <h2 class="mb-1"><?php echo htmlspecialchars($seller['username']); ?></h2>
              <?php if ($rating['total'] > 0): ?>
                <div class="mb-1">
                  <?php echo renderStars($rating['average'], true); ?>
                  <span class="h5 ml-2"><?php echo $rating['average']; ?> / 5</span>
                </div>
                <span class="text-muted"><?php echo number_format($rating['total']); ?> ratings</span>
              <?php else: ?>
                <span class="text-muted">New seller · No ratings yet</span>
              <?php endif; ?>
              <div class="text-muted small mt-1">
                Member since <?php echo $member_since->format('F Y'); ?>
              </div>
            </div>
          </div>
        </div>
        <?php if ($rating['total'] > 0): ?>
        <div class="col-md-4">
          <h6 class="text-muted mb-3">Rating Breakdown</h6>
          <?php for ($i = 5; $i >= 1; $i--): 
            $count = $rating['stars'][$i - 1];
            $percentage = $rating['total'] > 0 ? ($count / $rating['total']) * 100 : 0;
          ?>
          <div class="d-flex align-items-center mb-1">
            <span class="mr-2" style="width: 60px;"><?php echo $i; ?> <i class="fa fa-star text-warning"></i></span>
            <div class="progress flex-grow-1" style="height: 8px;">
              <div class="progress-bar bg-warning" style="width: <?php echo $percentage; ?>%"></div>
            </div>
            <span class="ml-2 text-muted" style="width: 30px;"><?php echo $count; ?></span>
          </div>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <!-- Active Listings -->
  <div id="listings">
    <h4 class="mb-3"><i class="fa fa-shopping-bag"></i> Active Listings <span class="badge badge-primary"><?php echo count($active_listings); ?></span></h4>
    
    <?php if (empty($active_listings)): ?>
      <div class="alert alert-info">This seller has no active listings at the moment.</div>
    <?php else: ?>
      <ul class="list-group mb-4">
        <?php foreach ($active_listings as $listing): 
          $end_date = new DateTime($listing['end_time']);
          $meta = ['image_path' => $listing['image_path'] ?? null];
          print_listing_li(
            $listing['auction_id'],
            htmlspecialchars($listing['title']),
            $listing['description'],
            $listing['current_price'],
            $listing['bid_count'],
            $end_date,
            $meta
          );
        endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
  
  <!-- Recent Ratings -->
  <div id="ratings">
    <h4 class="mb-3"><i class="fa fa-star"></i> Recent Ratings</h4>
    
    <?php if (empty($recent_ratings)): ?>
      <div class="alert alert-info">No ratings yet.</div>
    <?php else: ?>
      <div class="list-group">
        <?php foreach ($recent_ratings as $r): 
          $rating_date = new DateTime($r['created_at']);
          $stars = (int)$r['rating'];
        ?>
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <?php echo renderStars($stars); ?>
                <span class="ml-2 text-muted">by <?php echo htmlspecialchars($r['buyer_name']); ?></span>
                <?php if (!empty($r['comment'])): ?>
                  <p class="mb-0 mt-2"><?php echo htmlspecialchars($r['comment']); ?></p>
                <?php endif; ?>
                <small class="text-muted">
                  For: <a href="listing.php?item_id=<?php echo $r['auction_id']; ?>"><?php echo htmlspecialchars($r['auction_title']); ?></a>
                </small>
              </div>
              <small class="text-muted"><?php echo $rating_date->format('j M Y'); ?></small>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  
</div>

<?php include_once("footer.php"); ?>
