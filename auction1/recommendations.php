<?php include_once("header.php")?>
<?php require("utilities.php")?>
<?php require_once("db_connect.php"); ?>
<?php require_once("auction_helpers.php"); ?>

<div class="container">

<h2 class="my-3">Recommendations for you</h2>

<?php
  if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['account_type'], ['buyer', 'both'])) {
      echo '<div class="alert alert-danger">Please login as a buyer to view recommendations.</div>';
      include_once("footer.php");
      exit();
  }

  $user_id = $_SESSION['user_id'];

  $collab_sql = "
    SELECT a.auction_id, a.title, a.description, a.end_time, a.created_at, a.image_path, u.username AS seller_name,
           COALESCE(agg.max_bid, a.start_price) AS current_price,
           COALESCE(agg.bid_count, 0) AS bid_count
    FROM Bids b
    JOIN Auctions a ON b.auction_id = a.auction_id
    JOIN Users u ON u.user_id = a.user_id
    LEFT JOIN (
        SELECT auction_id, MAX(bid_amount) AS max_bid, COUNT(*) AS bid_count
        FROM Bids
        GROUP BY auction_id
    ) agg ON agg.auction_id = a.auction_id
    WHERE b.user_id IN (
        SELECT DISTINCT b2.user_id
        FROM Bids b2
        WHERE b2.auction_id IN (SELECT b3.auction_id FROM Bids b3 WHERE b3.user_id = ?)
          AND b2.user_id != ?
    )
      AND b.auction_id NOT IN (SELECT auction_id FROM Bids WHERE user_id = ?)
      AND a.end_time > NOW()
    GROUP BY a.auction_id
    ORDER BY COUNT(DISTINCT b.user_id) DESC, a.end_time ASC
    LIMIT 10
  ";

  $collab_recs = fetchAuctionCardData($conn, $collab_sql, 'iii', [$user_id, $user_id, $user_id]);

  // Get categories from auctions user has bid on (via AuctionCategories junction table)
  $category_ids = [];
  $cat_stmt = $conn->prepare("
    SELECT DISTINCT ac.category_id
    FROM Bids b
    JOIN Auctions a ON a.auction_id = b.auction_id
    JOIN AuctionCategories ac ON ac.auction_id = a.auction_id
    WHERE b.user_id = ?
  ");
  $cat_stmt->bind_param('i', $user_id);
  $cat_stmt->execute();
  $cat_res = $cat_stmt->get_result();
  while ($row = $cat_res->fetch_assoc()) {
      $category_ids[] = (int) $row['category_id'];
  }
  $cat_stmt->close();
  $category_ids = array_values(array_unique($category_ids));

  $category_recs = [];
  if (!empty($category_ids)) {
      $placeholders = implode(',', array_fill(0, count($category_ids), '?'));
      $category_sql = "
        SELECT DISTINCT a.auction_id, a.title, a.description, a.end_time, a.created_at, a.image_path, u.username AS seller_name,
               COALESCE(agg.max_bid, a.start_price) AS current_price,
               COALESCE(agg.bid_count, 0) AS bid_count
        FROM Auctions a
        JOIN Users u ON u.user_id = a.user_id
        JOIN AuctionCategories ac ON ac.auction_id = a.auction_id
        LEFT JOIN (
            SELECT auction_id, MAX(bid_amount) AS max_bid, COUNT(*) AS bid_count
            FROM Bids
            GROUP BY auction_id
        ) agg ON agg.auction_id = a.auction_id
        WHERE ac.category_id IN ($placeholders)
          AND a.auction_id NOT IN (SELECT auction_id FROM Bids WHERE user_id = ?)
          AND a.end_time > NOW()
        GROUP BY a.auction_id
        ORDER BY a.end_time ASC
        LIMIT 10
      ";
      $types = str_repeat('i', count($category_ids)) . 'i';
      $params = array_merge($category_ids, [$user_id]);
      $category_recs = fetchAuctionCardData($conn, $category_sql, $types, $params);
  }

  $has_bid_history = $conn->query("SELECT bid_id FROM Bids WHERE user_id = {$user_id} LIMIT 1");
?>

<div class="mt-4">
  <h4>Other bidders are also bidding</h4>
  <p class="text-muted small">Items that users who bid on the same auctions as you have also bid on.</p>
  <?php if (empty($collab_recs)): ?>
    <?php if ($has_bid_history && $has_bid_history->num_rows == 0): ?>
      <div class="alert alert-info">Bid on a few items so we can tailor suggestions.</div>
    <?php else: ?>
      <div class="alert alert-warning">No recommendations yet. Other bidders haven't bid on different items.</div>
    <?php endif; ?>
  <?php else: ?>
    <ul class="list-group">
      <?php foreach ($collab_recs as $row):
        $categories = $row['categories'] ?? [];
        $meta = [
          'seller' => $row['seller_name'],
          'created_at' => $row['created_at'] ?? null,
          'categories' => $categories,
          'image_path' => $row['image_path'] ?? null,
        ];
        $end_date = new DateTime($row['end_time']);
        print_listing_li($row['auction_id'], htmlspecialchars($row['title']), $row['description'], $row['current_price'], $row['bid_count'], $end_date, $meta);
      endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<div class="mt-4">
  <h4>Similar items in your favorite categories</h4>
  <?php if (empty($category_recs)): ?>
    <div class="alert alert-light">We need a bit more bidding history to offer category-based picks.</div>
  <?php else: ?>
    <ul class="list-group">
      <?php foreach ($category_recs as $row):
        $categories = $row['categories'] ?? [];
        $meta = [
          'seller' => $row['seller_name'],
          'created_at' => $row['created_at'] ?? null,
          'categories' => $categories,
          'image_path' => $row['image_path'] ?? null,
        ];
        $end_date = new DateTime($row['end_time']);
        print_listing_li($row['auction_id'], htmlspecialchars($row['title']), $row['description'], $row['current_price'], $row['bid_count'], $end_date, $meta);
      endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

</div>

<?php include_once("footer.php")?>