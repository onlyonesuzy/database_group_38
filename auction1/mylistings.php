<?php include_once("header.php")?>
<?php require("utilities.php")?>
<?php require_once("db_connect.php"); ?>

<div class="container">

<h2 class="my-3">My Listings</h2>
<p class="text-muted">Your auction listings with earnings breakdown (3% platform fee applied)</p>

<?php
  if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['account_type'], ['seller', 'both'])) {
      echo '<div class="alert alert-danger">You must be logged in as a seller to view this page.</div>';
  } else {
      $user_id = $_SESSION['user_id'];
      $fee_rate = 0.03;

      $sql = "SELECT a.*, u.username AS seller_name,
                     COALESCE(b.max_bid, a.start_price) AS current_price,
                     COALESCE(b.bid_count, 0) AS bid_count
              FROM Auctions a
              JOIN Users u ON u.user_id = a.user_id
              LEFT JOIN (
                  SELECT auction_id, MAX(bid_amount) AS max_bid, COUNT(*) AS bid_count
                  FROM Bids
                  GROUP BY auction_id
              ) b ON b.auction_id = a.auction_id
              WHERE a.user_id = ?
              ORDER BY a.created_at DESC";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows == 0) {
          echo '<div class="alert alert-info">You haven\'t created any auctions yet.</div>';
          echo '<a href="create_auction.php" class="btn btn-primary">Create your first auction</a>';
      } else {
          $auctions = [];
          $ids = [];
          while ($row = $result->fetch_assoc()) {
              $auctions[] = $row;
              $ids[] = (int) $row['auction_id'];
          }
          $categories_map = fetch_categories_for_auctions($conn, $ids);

          echo '<div class="list-group">';
          foreach ($auctions as $row) {
              $item_id = $row['auction_id'];
              $title = htmlspecialchars($row['title']);
              $description = htmlspecialchars(substr($row['description'], 0, 100)) . (strlen($row['description']) > 100 ? '...' : '');
              $end_date = new DateTime($row['end_time']);
              $now = new DateTime();
              $num_bids = (int) $row['bid_count'];
              $current_price = (float) $row['current_price'];
              $buy_now_price = !empty($row['buy_now_price']) ? (float) $row['buy_now_price'] : null;
              $is_ended = $now >= $end_date;
              $is_bought_now = !empty($row['bought_now']);
              
              // Calculate seller payouts
              $current_fee = $current_price * $fee_rate;
              $current_payout = $current_price - $current_fee;
              
              $buy_now_fee = $buy_now_price ? $buy_now_price * $fee_rate : 0;
              $buy_now_payout = $buy_now_price ? $buy_now_price - $buy_now_fee : 0;

              $categories = $categories_map[$item_id] ?? [];
              
              $status_class = $is_ended ? 'list-group-item-secondary' : '';
              if ($is_bought_now) $status_class = 'list-group-item-success';
?>
              <div class="list-group-item <?php echo $status_class; ?>">
                <div class="row">
                  <div class="col-md-6">
                    <div class="d-flex">
                      <?php if (!empty($row['image_path'])): ?>
                        <div class="mr-3" style="flex-shrink: 0;">
                          <a href="listing.php?item_id=<?php echo $item_id; ?>">
                            <img src="<?php echo htmlspecialchars($row['image_path']); ?>" alt="<?php echo $title; ?>" 
                                 class="img-thumbnail" style="width: 60px; height: 60px; object-fit: cover;">
                          </a>
                        </div>
                      <?php endif; ?>
                      <div class="flex-grow-1">
                    <h5 class="mb-1">
                      <a href="listing.php?item_id=<?php echo $item_id; ?>"><?php echo $title; ?></a>
                      <?php if ($is_bought_now): ?>
                        <span class="badge badge-success">Sold (Buy Now)</span>
                      <?php elseif ($is_ended): ?>
                        <span class="badge badge-secondary">Ended</span>
                      <?php else: ?>
                        <span class="badge badge-primary">Active</span>
                      <?php endif; ?>
                    </h5>
                    <p class="mb-1 text-muted small"><?php echo $description; ?></p>
                    <small class="text-muted">
                      <?php echo $num_bids; ?> bid<?php echo $num_bids != 1 ? 's' : ''; ?> · 
                      <?php if ($is_ended): ?>
                        Ended <?php echo $end_date->format('j M Y H:i'); ?>
                      <?php else: ?>
                        Ends <?php echo $end_date->format('j M Y H:i'); ?>
                        (<?php echo display_time_remaining(date_diff($now, $end_date)); ?> remaining)
                      <?php endif; ?>
                    </small>
                    <div class="mt-2">
                      <?php echo render_category_badges($categories); ?>
                    </div>
                    <div class="mt-2">
                      <a href="edit_auction_image.php?auction_id=<?php echo $item_id; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fa fa-image"></i> Edit Image
                      </a>
                    </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="row text-center">
                      <div class="col-6">
                        <div class="border rounded p-2 h-100">
                          <small class="text-muted d-block">Current Bid</small>
                          <strong class="text-primary">£<?php echo number_format($current_price, 2); ?></strong>
                          <div class="mt-1 small">
                            <span class="text-muted">Fee: £<?php echo number_format($current_fee, 2); ?></span><br>
                            <span class="text-success font-weight-bold">You receive: £<?php echo number_format($current_payout, 2); ?></span>
                          </div>
                        </div>
                      </div>
                      <div class="col-6">
                        <div class="border rounded p-2 h-100">
                          <small class="text-muted d-block">Buy Now Price</small>
                          <?php if ($buy_now_price): ?>
                            <strong class="text-success">£<?php echo number_format($buy_now_price, 2); ?></strong>
                            <div class="mt-1 small">
                              <span class="text-muted">Fee: £<?php echo number_format($buy_now_fee, 2); ?></span><br>
                              <span class="text-success font-weight-bold">You receive: £<?php echo number_format($buy_now_payout, 2); ?></span>
                            </div>
                          <?php else: ?>
                            <span class="text-muted">Not set</span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
<?php
          }
          echo '</div>';
      }
      $stmt->close();
  }
?>

</div>

<?php include_once("footer.php")?>