<?php include_once("header.php")?>
<?php require("utilities.php")?>
<?php require_once("db_connect.php"); ?>

<div class="container">

<h2 class="my-3">My bids</h2>

<?php
  if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['account_type'], ['buyer', 'both'])) {
      echo '<div class="alert alert-danger">You must be logged in as a buyer to view this page.</div>';
  } else {
      $user_id = $_SESSION['user_id'];

      $sql = "SELECT a.*, u.username AS seller_name, COALESCE(agg.max_bid, a.start_price) AS current_price, COALESCE(agg.bid_count, 0) AS bid_count, latest.my_bid_amount, latest.my_bid_time FROM Auctions a JOIN (SELECT b.auction_id, b.bid_amount AS my_bid_amount, b.bid_time AS my_bid_time FROM Bids b JOIN (SELECT auction_id, MAX(bid_time) AS latest_time FROM Bids WHERE user_id = ? GROUP BY auction_id) recent ON recent.auction_id = b.auction_id AND recent.latest_time = b.bid_time WHERE b.user_id = ?) latest ON latest.auction_id = a.auction_id JOIN Users u ON u.user_id = a.user_id LEFT JOIN (SELECT auction_id, MAX(bid_amount) AS max_bid, COUNT(*) AS bid_count FROM Bids GROUP BY auction_id) agg ON agg.auction_id = a.auction_id ORDER BY latest.my_bid_time DESC";

      $stmt = $conn->prepare($sql);
      $stmt->bind_param("ii", $user_id, $user_id);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows == 0) {
          echo '<div class="alert alert-info">You haven\'t placed any bids yet.</div>';
      } else {
          $auctions = [];
          $ids = [];
          while ($row = $result->fetch_assoc()) {
              $auctions[] = $row;
              $ids[] = (int) $row['auction_id'];
          }
          $categories_map = fetch_categories_for_auctions($conn, $ids);

          echo '<ul class="list-group">';
          foreach ($auctions as $row) {
              $item_id = $row['auction_id'];
              $title = htmlspecialchars($row['title']);
              $description = $row['description'];
              $end_date = new DateTime($row['end_time']);
              $num_bids = (int) $row['bid_count'];
              $current_price = $row['current_price'];
              $my_bid = $row['my_bid_amount'];

              $extra = "\nYour latest bid: £" . format_price($my_bid) . ' on ' . (new DateTime($row['my_bid_time']))->format('j M Y H:i');
              $description_with_bid = $description . $extra;

              $categories = $categories_map[$item_id] ?? [];

              $meta = [
                  'seller' => $row['seller_name'],
                  'created_at' => $row['created_at'] ?? null,
                  'categories' => $categories,
                  'image_path' => $row['image_path'] ?? null,
              ];

              print_listing_li($item_id, $title, $description_with_bid, $current_price, $num_bids, $end_date, $meta);
          }
          echo '</ul>';
      }
      $stmt->close();
  }
?>

<?php include_once("footer.php")?>