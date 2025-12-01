<?php include_once("header.php")?>
<?php require_once("utilities.php")?>
<?php require_once("db_connect.php"); // 连接数据库 ?>
<?php require_once("auction_helpers.php"); ?>

<?php
$item_id = $_GET['item_id'] ?? null;
if (!$item_id || !ctype_digit((string) $item_id)) {
    echo '<div class="container my-5 text-danger">Error: No item specified.</div>';
    include_once("footer.php");
    exit();
}
$item_id = (int) $item_id;

$auction_stmt = $conn->prepare("SELECT a.*, u.username AS seller_name, u.email AS seller_email\n                                 FROM Auctions a\n                                 JOIN Users u ON u.user_id = a.user_id\n                                 WHERE a.auction_id = ?");
$auction_stmt->bind_param('i', $item_id);
$auction_stmt->execute();
$auction_result = $auction_stmt->get_result();

if ($auction_result->num_rows == 0) {
    echo '<div class="container my-5 text-danger">Error: Item not found.</div>';
    include_once("footer.php");
    exit();
}

$row = $auction_result->fetch_assoc();
$auction_stmt->close();

$end_time = new DateTime($row['end_time']);
$created_at = !empty($row['created_at']) ? new DateTime($row['created_at']) : null;
$seller_id = $row['user_id'];
$title = $row['title'];
$description = $row['description'];
$start_price = $row['start_price'];

$bid_stmt = $conn->prepare("SELECT MAX(bid_amount) as max_bid, COUNT(*) as bid_count FROM Bids WHERE auction_id = ?");
$bid_stmt->bind_param('i', $item_id);
$bid_stmt->execute();
$bid_result = $bid_stmt->get_result();
$bid_data = $bid_result->fetch_assoc();
$bid_stmt->close();

$num_bids = (int) $bid_data['bid_count'];
$current_price = $bid_data['max_bid'] ?? $start_price;

finalizeAuctionIfNeeded($conn, $row);
$winner_info = fetchWinnerInfo($conn, $item_id);

$categories_map = fetch_categories_for_auctions($conn, [$item_id]);
$categories = $categories_map[$item_id] ?? [];
$category_ids = array_map(function ($cat) { return (int) $cat['id']; }, $categories);

// Categories are fetched from AuctionCategories junction table via fetch_categories_for_auctions()

$category_ids = array_values(array_unique($category_ids));

$history_stmt = $conn->prepare("SELECT b.bid_id, b.bid_amount, b.bid_time, b.user_id, u.username\n                                FROM Bids b\n                                JOIN Users u ON u.user_id = b.user_id\n                                WHERE b.auction_id = ?\n                                ORDER BY b.bid_time DESC");
$history_stmt->bind_param('i', $item_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
$bid_history = [];
while ($history_row = $history_result->fetch_assoc()) {
    $bid_history[] = $history_row;
}
$history_stmt->close();

$also_bidding = fetchAlsoBiddingAuctions($conn, $item_id);
$similar_items = fetchSimilarCategoryAuctions($conn, $item_id, $category_ids);

$has_session = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$watching = false;
if ($has_session) {
    $user_id = $_SESSION['user_id'];
    $watch_sql = "SELECT COUNT(*) as count FROM Watchlist WHERE user_id = ? AND auction_id = ?";
    $watch_stmt = $conn->prepare($watch_sql);
    $watch_stmt->bind_param('ii', $user_id, $item_id);
    $watch_stmt->execute();
    $watch_res = $watch_stmt->get_result();
    $watch_data = $watch_res->fetch_assoc();
    if ($watch_data['count'] > 0) {
        $watching = true;
    }
    $watch_stmt->close();
}
?>

<div class="container">

<?php
// Display success/error messages from redirects
$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;
$amount = $_GET['amount'] ?? null;
$min = $_GET['min'] ?? null;

if ($success === 'bid_placed'): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fa fa-check-circle"></i> <strong>Success!</strong> Your bid of £<?php echo htmlspecialchars($amount); ?> has been placed successfully!
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
<?php endif; ?>

<?php if ($success === 'buy_now'): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fa fa-shopping-cart"></i> <strong>Congratulations!</strong> You have successfully purchased this item for £<?php echo htmlspecialchars($amount); ?>!
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
<?php endif; ?>

<?php if ($error === 'bid_too_low'): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fa fa-exclamation-circle"></i> <strong>Bid too low!</strong> You must bid at least £<?php echo htmlspecialchars($min); ?>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
<?php endif; ?>

<?php if ($error === 'auction_ended'): ?>
  <div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fa fa-clock-o"></i> <strong>Too late!</strong> This auction has already ended.
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
<?php endif; ?>

<?php if ($error === 'bid_failed'): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fa fa-times-circle"></i> <strong>Error!</strong> Failed to place bid. Please try again.
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
<?php endif; ?>

<?php if ($error === 'purchase_failed'): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fa fa-times-circle"></i> <strong>Error!</strong> Failed to complete purchase. Please try again.
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
<?php endif; ?>

<?php if ($error === 'already_purchased'): ?>
  <div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fa fa-info-circle"></i> This item has already been purchased.
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
<?php endif; ?>

<?php if ($error === 'own_auction'): ?>
  <div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fa fa-info-circle"></i> You cannot buy your own auction.
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
<?php endif; ?>

<?php 
// Get seller rating
$seller_rating = getSellerRating($conn, $seller_id);
?>

<div class="row">
  <div class="col-sm-8">
    <h2 class="my-3"><?php echo htmlspecialchars($title); ?></h2>
    
    <!-- Seller Rating Badge -->
    <?php echo renderSellerRatingBadge($seller_rating, $row['seller_name'], $seller_id); ?>
    
    <div class="text-muted small mb-2 mt-2">
      <?php if ($created_at): ?>Listed on <?php echo $created_at->format('j M Y H:i'); ?><?php endif; ?>
    </div>
    <?php echo render_category_badges($categories); ?>
  </div>
  <div class="col-sm-4 align-self-center">
  <?php 
    // Show edit image button if user is the seller
    if (isset($_SESSION['logged_in']) && isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$seller_id && in_array($_SESSION['account_type'], ['seller', 'both'])): ?>
      <a href="edit_auction_image.php?auction_id=<?php echo $item_id; ?>" class="btn btn-outline-primary btn-sm mb-2">
        <i class="fa fa-image"></i> Edit Image
      </a>
      <br>
  <?php endif; ?>
  <?php if (new DateTime() < $end_time): ?>
    <div id="watch_nowatch" <?php if ($has_session && $watching) echo('style="display: none"');?> >
      <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addToWatchlist()">+ Add to watchlist</button>
    </div>
    <div id="watch_watching" <?php if (!$has_session || !$watching) echo('style="display: none"');?> >
      <button type="button" class="btn btn-success btn-sm" disabled>Watching</button>
      <button type="button" class="btn btn-danger btn-sm" onclick="removeFromWatchlist()">Remove watch</button>
    </div>
  <?php else: ?>
    <div class="alert alert-info py-2 mb-0">Auction ended</div>
  <?php endif; ?>
  </div>
</div>

<div class="row">
  <div class="col-sm-8">
    <?php if (!empty($row['image_path'])): ?>
      <div class="mb-3">
        <img src="<?php echo htmlspecialchars($row['image_path']); ?>" alt="<?php echo htmlspecialchars($title); ?>" class="img-fluid rounded" style="max-width: 100%; max-height: 400px;">
      </div>
    <?php endif; ?>
    
    <div class="itemDescription">
      <?php echo nl2br(htmlspecialchars($description)); ?>
    </div>

    <?php if ($winner_info && new DateTime() > $end_time): ?>
      <div class="alert alert-success mt-3">
        <?php if (!empty($row['bought_now'])): ?>
          <?php echo htmlspecialchars($winner_info['username']); ?> purchased this item via Buy Now for £<?php echo format_price($winner_info['bid_amount']); ?>.
        <?php else: ?>
          <?php echo htmlspecialchars($winner_info['username']); ?> won this auction with a bid of £<?php echo format_price($winner_info['bid_amount']); ?>.
        <?php endif; ?>
        
        <?php 
        // Show "Rate Seller" button if current user is the winner and hasn't rated yet
        if (isset($_SESSION['user_id']) && (int)$winner_info['user_id'] === (int)$_SESSION['user_id'] && canRateSeller($conn, $item_id, $_SESSION['user_id'])): ?>
          <div class="mt-2">
            <a href="rate_seller.php?auction_id=<?php echo $item_id; ?>" class="btn btn-outline-success btn-sm">
              <i class="fa fa-star"></i> Rate this Seller
            </a>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <h4 class="mt-4">Bid history</h4>
    <p class="text-muted">Everyone can review every bid, even after the auction is complete.</p>
    <?php if (empty($bid_history)): ?>
      <div class="alert alert-light">No bids yet. Be the first one!</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped table-sm">
          <thead>
            <tr>
              <th scope="col">Bidder</th>
              <th scope="col">Amount</th>
              <th scope="col">Time</th>
              <th scope="col">Status</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($bid_history as $bid):
            $isWinning = $winner_info && (int)$winner_info['user_id'] === (int)$bid['user_id'] && (float)$winner_info['bid_amount'] == (float)$bid['bid_amount'];
            $rowClass = $isWinning ? 'table-success' : '';
            $time = new DateTime($bid['bid_time']);
          ?>
            <tr class="<?php echo $rowClass; ?>">
              <td><?php echo htmlspecialchars($bid['username']); ?></td>
              <td>£<?php echo format_price($bid['bid_amount']); ?></td>
              <td><?php echo $time->format('j M Y H:i'); ?></td>
              <td><?php echo $isWinning ? 'Highest bid' : 'Placed'; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="col-sm-4">
    <?php $now = new DateTime(); ?>
    <?php if ($now > $end_time): ?>
      <p>This auction ended <?php echo($end_time->format('j M H:i')); ?></p>
    <?php else: ?>
      <?php $time_to_end = date_diff($now, $end_time); ?>
      <p>Auction ends <?php echo($end_time->format('j M H:i') . ' (in ' . display_time_remaining($time_to_end) . ')'); ?></p>
    <?php endif; ?>
    <p class="lead">Current price: £<?php echo format_price($current_price); ?></p>
    
    <?php
    // Check if current user is the highest bidder
    $is_highest_bidder = false;
    $auction_ended_check = $now > $end_time;
    if (isset($_SESSION['user_id']) && !empty($bid_history)) {
        $highest_bid = $bid_history[0]; // First one is the highest (sorted DESC)
        if ((int)$highest_bid['user_id'] === (int)$_SESSION['user_id']) {
            $is_highest_bidder = true;
        }
    }
    ?>
    
    <?php if ($is_highest_bidder && !$auction_ended_check): ?>
      <div class="alert alert-success py-2 mb-3">
        <i class="fa fa-trophy"></i> <strong>You are the highest bidder!</strong>
      </div>
    <?php elseif (isset($_SESSION['user_id']) && !empty($bid_history) && !$auction_ended_check): ?>
      <?php
      // Check if user has bid but is not highest
      $user_has_bid = false;
      foreach ($bid_history as $bid) {
          if ((int)$bid['user_id'] === (int)$_SESSION['user_id']) {
              $user_has_bid = true;
              break;
          }
      }
      if ($user_has_bid): ?>
        <div class="alert alert-warning py-2 mb-3">
          <i class="fa fa-exclamation-triangle"></i> <strong>You've been outbid!</strong> Place a higher bid to win.
        </div>
      <?php endif; ?>
    <?php endif; ?>
    
    <?php if (!empty($row['reserve_price']) && $row['reserve_price'] > 0): ?>
      <p class="text-muted">Reserve price: £<?php echo format_price($row['reserve_price']); ?>
        <?php if ($current_price >= $row['reserve_price']): ?>
          <span class="badge badge-success">Met</span>
        <?php else: ?>
          <span class="badge badge-warning">Not met</span>
        <?php endif; ?>
      </p>
    <?php endif; ?>
    <p><?php echo $num_bids; ?> bids so far.</p>

    <?php 
    $has_buy_now = !empty($row['buy_now_price']) && $row['buy_now_price'] > 0 && empty($row['bought_now']);
    $auction_ended = $now >= $end_time;
    ?>

    <?php if ($has_buy_now && !$auction_ended): ?>
      <div class="alert alert-success py-2">
        <strong>Buy Now: £<?php echo format_price($row['buy_now_price']); ?></strong>
        <small class="d-block">Purchase instantly at this price</small>
      </div>
      <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true && in_array($_SESSION['account_type'], ['buyer', 'both'])): ?>
        <button type="button" class="btn btn-success form-control mb-3" data-toggle="modal" data-target="#buyNowModal">
          Buy Now for £<?php echo format_price($row['buy_now_price']); ?>
        </button>
        <hr>
        <p class="text-muted small">Or place a bid below:</p>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (!$auction_ended): ?>
      <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true && in_array($_SESSION['account_type'], ['buyer', 'both'])): ?>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text">£</span>
          </div>
          <input type="number" class="form-control" id="bid" name="bid" required min="<?php echo $current_price + 0.01; ?>" step="0.01">
        </div>
        <button type="button" class="btn btn-primary form-control mt-2" onclick="showBidConfirmModal()">Place bid</button>
      <?php elseif (isset($_SESSION['account_type']) && $_SESSION['account_type'] == 'seller'): ?>
        <div class="alert alert-info">Sellers cannot place bids.</div>
      <?php else: ?>
        <div class="alert alert-warning">Please <a href="#" data-toggle="modal" data-target="#loginModal">login</a> to bid.</div>
      <?php endif; ?>
    <?php else: ?>
      <div class="alert alert-secondary">Bidding closed.</div>
    <?php endif; ?>
  </div>
</div>

<div class="row mt-4">
  <div class="col-12">
    <h4>Other bidders are also watching</h4>
    <?php if (empty($also_bidding)): ?>
      <div class="alert alert-light">No related bidding activity yet.</div>
    <?php else: ?>
      <ul class="list-group">
        <?php foreach ($also_bidding as $rec):
          $rec_meta = [
            'seller' => $rec['seller_name'],
            'created_at' => $rec['created_at'] ?? null,
            'categories' => $rec['categories'] ?? [],
          ];
          $end_date = new DateTime($rec['end_time']);
          print_listing_li($rec['auction_id'], htmlspecialchars($rec['title']), $rec['description'], $rec['current_price'], $rec['bid_count'], $end_date, $rec_meta);
        endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<div class="row mt-4">
  <div class="col-12">
    <h4>Similar items in these categories</h4>
    <?php if (empty($similar_items)): ?>
      <div class="alert alert-light">No similar live items right now.</div>
    <?php else: ?>
      <ul class="list-group">
        <?php foreach ($similar_items as $rec):
          $rec_meta = [
            'seller' => $rec['seller_name'],
            'created_at' => $rec['created_at'] ?? null,
            'categories' => $rec['categories'] ?? [],
          ];
          $end_date = new DateTime($rec['end_time']);
          print_listing_li($rec['auction_id'], htmlspecialchars($rec['title']), $rec['description'], $rec['current_price'], $rec['bid_count'], $end_date, $rec_meta);
        endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

</div>

<!-- Bid Confirmation Modal -->
<div class="modal fade" id="bidConfirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Confirm Your Bid</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body text-center">
        <i class="fa fa-gavel fa-3x text-primary mb-3"></i>
        <p class="lead">You are about to place a bid of:</p>
        <h2 class="text-primary" id="confirmBidAmount">£0.00</h2>
        <p class="text-muted mt-3">This action cannot be undone. Are you sure you want to proceed?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <form method="POST" action="place_bid.php" id="bidForm" class="d-inline">
          <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
          <input type="hidden" name="bid" id="hiddenBidAmount">
          <button type="submit" class="btn btn-primary">Yes, Place Bid</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Buy Now Confirmation Modal -->
<?php if ($has_buy_now && !$auction_ended): ?>
<div class="modal fade" id="buyNowModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Confirm Purchase</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body text-center">
        <i class="fa fa-shopping-cart fa-3x text-success mb-3"></i>
        <p class="lead">You are about to purchase:</p>
        <h4><?php echo htmlspecialchars($title); ?></h4>
        <h2 class="text-success mt-3">£<?php echo format_price($row['buy_now_price']); ?></h2>
        <div class="alert alert-warning mt-3 mb-0">
          <small><i class="fa fa-exclamation-triangle"></i> This will immediately end the auction and complete the purchase. This action cannot be undone.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <form method="POST" action="buy_now.php" class="d-inline">
          <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
          <button type="submit" class="btn btn-success">Yes, Buy Now</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include_once("footer.php")?>

<script>
function showBidConfirmModal() {
  var bidAmount = document.getElementById('bid').value;
  if (!bidAmount || parseFloat(bidAmount) <= 0) {
    alert('Please enter a valid bid amount.');
    return;
  }
  document.getElementById('confirmBidAmount').textContent = '£' + parseFloat(bidAmount).toFixed(2);
  document.getElementById('hiddenBidAmount').value = bidAmount;
  $('#bidConfirmModal').modal('show');
}

function addToWatchlist(button) {
  $.ajax('watchlist_funcs.php', {
    type: "POST",
    data: {functionname: 'add_to_watchlist', arguments: [<?php echo $item_id;?>]},
    success: function(obj, textstatus) {
      var response = obj.trim();
      if (response == "success") {
        $("#watch_nowatch").hide();
        $("#watch_watching").show();
      } else {
        console.log("Error: " + obj);
        alert("Failed to add to watchlist. Check console for details.");
      }
    },
    error: function(obj, textstatus) {
      console.log("Connection error");
    }
  });
}

function removeFromWatchlist(button) {
  $.ajax('watchlist_funcs.php', {
    type: "POST",
    data: {functionname: 'remove_from_watchlist', arguments: [<?php echo $item_id;?>]},
    success: function(obj, textstatus) {
      var response = obj.trim();
      if (response == "success") {
        $("#watch_watching").hide();
        $("#watch_nowatch").show();
      } else {
        console.log("Error: " + obj);
      }
    },
    error: function(obj, textstatus) {
      console.log("Connection error");
    }
  });
}

</script>
