<?php include_once("header.php")?>
<?php require("utilities.php")?>
<?php require_once('db_connect.php'); // 放在最上面连接数据库 ?>

<?php
  $keyword = trim($_GET['keyword'] ?? '');
  $category = $_GET['cat'] ?? 'all';
  $ordering = $_GET['order_by'] ?? 'pricelow';
  $curr_page = $_GET['page'] ?? 1;

  $where = ['1=1'];
  $params = [];
  $types = '';

  if ($keyword !== '') {
      $where[] = '(a.title LIKE ? OR a.description LIKE ?)';
      $like = '%' . $keyword . '%';
      $params[] = $like;
      $params[] = $like;
      $types .= 'ss';
  }

  if ($category !== 'all' && ctype_digit((string) $category)) {
      $where[] = 'EXISTS (SELECT 1 FROM AuctionCategories acFilter WHERE acFilter.auction_id = a.auction_id AND acFilter.category_id = ?)';
      $params[] = (int) $category;
      $types .= 'i';
  }

  $order_sql = 'current_price ASC';
  switch ($ordering) {
      case 'pricehigh':
          $order_sql = 'current_price DESC';
          break;
      case 'date':
          $order_sql = 'a.end_time ASC';
          break;
      case 'newest':
          $order_sql = 'a.created_at DESC';
          break;
      case 'popular':
          $order_sql = 'bid_count DESC, current_price DESC';
          break;
      default:
          $order_sql = 'current_price ASC';
  }

  $sql = "
    SELECT a.*, u.username AS seller_name, u.user_id AS seller_id,
           COALESCE(b.max_bid, a.start_price) AS current_price,
           COALESCE(b.bid_count, 0) AS bid_count
    FROM Auctions a
    JOIN Users u ON u.user_id = a.user_id
    LEFT JOIN (
        SELECT auction_id, MAX(bid_amount) AS max_bid, COUNT(*) AS bid_count
        FROM Bids
        GROUP BY auction_id
    ) b ON b.auction_id = a.auction_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY {$order_sql}
  ";

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
      die("Failed to prepare query: " . $conn->error);
  }

  if (!empty($params)) {
      $stmt->bind_param($types, ...$params);
  }

  $stmt->execute();
  $result = $stmt->get_result();

  $active_auctions = [];
  $ended_auctions = [];
  $auction_ids = [];
  $now = new DateTime();
  
  while ($row = $result->fetch_assoc()) {
      $auction_ids[] = (int) $row['auction_id'];
      $end_time = new DateTime($row['end_time']);
      
      if ($end_time > $now) {
          $active_auctions[] = $row;
      } else {
          $ended_auctions[] = $row;
      }
  }
  $stmt->close();

  // Re-sort active and ended auctions separately to maintain sort order within each group
  // This ensures that the sort order is preserved after splitting into active/ended
  $sort_function = null;
  switch ($ordering) {
      case 'pricehigh':
          $sort_function = function($a, $b) {
              // current_price is already calculated in SQL query as COALESCE(b.max_bid, a.start_price)
              $price_a = isset($a['current_price']) ? (float)$a['current_price'] : 0;
              $price_b = isset($b['current_price']) ? (float)$b['current_price'] : 0;
              // DESC: high to low
              if ($price_b > $price_a) return 1;
              if ($price_b < $price_a) return -1;
              return 0;
          };
          break;
      case 'date':
          $sort_function = function($a, $b) {
              $time_a = strtotime($a['end_time']);
              $time_b = strtotime($b['end_time']);
              return $time_a <=> $time_b; // ASC
          };
          break;
      case 'newest':
          $sort_function = function($a, $b) {
              $time_a = strtotime($a['created_at'] ?? '1970-01-01');
              $time_b = strtotime($b['created_at'] ?? '1970-01-01');
              return $time_b <=> $time_a; // DESC
          };
          break;
      case 'popular':
          $sort_function = function($a, $b) {
              $bids_a = (int)($a['bid_count'] ?? 0);
              $bids_b = (int)($b['bid_count'] ?? 0);
              if ($bids_a !== $bids_b) {
                  return $bids_b <=> $bids_a; // DESC by bid count
              }
              // If bid count is same, sort by price DESC
              $price_a = isset($a['current_price']) ? (float)$a['current_price'] : 0;
              $price_b = isset($b['current_price']) ? (float)$b['current_price'] : 0;
              if ($price_b > $price_a) return 1;
              if ($price_b < $price_a) return -1;
              return 0;
          };
          break;
      default: // pricelow
          $sort_function = function($a, $b) {
              // current_price is already calculated in SQL query as COALESCE(b.max_bid, a.start_price)
              $price_a = isset($a['current_price']) ? (float)$a['current_price'] : 0;
              $price_b = isset($b['current_price']) ? (float)$b['current_price'] : 0;
              // ASC: low to high
              if ($price_a > $price_b) return 1;
              if ($price_a < $price_b) return -1;
              return 0;
          };
          break;
  }
  
  // Apply sorting to both arrays
  if ($sort_function) {
      usort($active_auctions, $sort_function);
      usort($ended_auctions, $sort_function);
  }

  $num_active = count($active_auctions);
  $num_ended = count($ended_auctions);
  $num_results = $num_active + $num_ended;

  // Pagination settings
  $items_per_page = 5;
  $active_page = isset($_GET['active_page']) ? max(1, (int)$_GET['active_page']) : 1;
  $ended_page = isset($_GET['ended_page']) ? max(1, (int)$_GET['ended_page']) : 1;
  
  // Calculate pagination for active auctions
  $total_active_pages = max(1, ceil($num_active / $items_per_page));
  $active_page = min($active_page, $total_active_pages);
  $active_start = ($active_page - 1) * $items_per_page;
  $active_paginated = array_slice($active_auctions, $active_start, $items_per_page);
  
  // Calculate pagination for ended auctions
  $total_ended_pages = max(1, ceil($num_ended / $items_per_page));
  $ended_page = min($ended_page, $total_ended_pages);
  $ended_start = ($ended_page - 1) * $items_per_page;
  $ended_paginated = array_slice($ended_auctions, $ended_start, $items_per_page);

  $categories_map = fetch_categories_for_auctions($conn, $auction_ids);
  
  // Collect seller IDs and fetch their ratings
  $seller_ids = [];
  foreach (array_merge($active_auctions, $ended_auctions) as $auction) {
      $seller_ids[] = (int) $auction['seller_id'];
  }
  $seller_ids = array_unique($seller_ids);
  
  $seller_ratings_map = [];
  foreach ($seller_ids as $sid) {
      $seller_ratings_map[$sid] = getSellerRating($conn, $sid);
  }

  // Load categories for dropdown
  $category_options = [];
  $cat_sql = "SELECT category_id, category_name FROM Categories ORDER BY category_name ASC";
  $cat_res = $conn->query($cat_sql);
  if ($cat_res) {
      while ($cat_row = $cat_res->fetch_assoc()) {
          $category_options[] = $cat_row;
      }
  }
?>

<div class="container">

<h2 class="my-3">Browse listings</h2>

<div id="searchSpecs">
<form method="get" action="browse.php">
  <div class="row">
    <div class="col-md-5 pr-0">
      <div class="form-group">
        <label for="keyword" class="sr-only">Search keyword:</label>
      <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text bg-transparent pr-0 text-muted">
              <i class="fa fa-search"></i>
            </span>
          </div>
          <input type="text" class="form-control border-left-0" id="keyword" name="keyword" placeholder="Search for anything" value="<?php echo htmlspecialchars($keyword); ?>">
        </div>
      </div>
    </div>
    <div class="col-md-3 pr-0">
      <div class="form-group">
        <label for="cat" class="sr-only">Search within:</label>
        <select class="form-control" id="cat" name="cat">
          <option value="all" <?php if($category=='all') echo 'selected'; ?>>All categories</option>
          <?php foreach ($category_options as $cat_row): ?>
            <option value="<?php echo $cat_row['category_id']; ?>" <?php if($category == $cat_row['category_id']) echo 'selected'; ?>>
              <?php echo htmlspecialchars($cat_row['category_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="col-md-3 pr-0">
      <div class="form-inline">
        <label class="mx-2" for="order_by">Sort by:</label>
        <select class="form-control" id="order_by" name="order_by">
          <option value="pricelow" <?php if($ordering=='pricelow') echo 'selected'; ?>>Price (low to high)</option>
          <option value="pricehigh" <?php if($ordering=='pricehigh') echo 'selected'; ?>>Price (high to low)</option>
          <option value="date" <?php if($ordering=='date') echo 'selected'; ?>>Soonest expiry</option>
          <option value="newest" <?php if($ordering=='newest') echo 'selected'; ?>>Newest listings</option>
          <option value="popular" <?php if($ordering=='popular') echo 'selected'; ?>>Most bids</option>
        </select>
      </div>
    </div>
    <div class="col-md-1 px-0">
      <button type="submit" class="btn btn-primary">Search</button>
    </div>
  </div>
</form>
</div> 

</div>

<div class="container mt-3">

<?php if ($num_results == 0): ?>
    <div class="alert alert-warning">No auctions found matching your criteria.</div>
<?php endif; ?>

<!-- Active Listings Section -->
<div class="mb-4">
  <h4 class="mb-3">
    <i class="fa fa-clock-o text-success"></i> Current Listings
    <span class="badge badge-success"><?php echo $num_active; ?></span>
  </h4>
  
  <?php if ($num_active == 0): ?>
    <div class="alert alert-info">No active auctions at the moment.</div>
  <?php else: ?>
    <ul class="list-group" id="activeResults">
    <?php
      foreach ($active_paginated as $row) {
          $item_id = $row['auction_id'];
          $title = htmlspecialchars($row['title']);
          $description = $row['description'];
          $end_date = new DateTime($row['end_time']);
          $num_bids = (int) $row['bid_count'];
          $current_price = $row['current_price'];

          $categories = $categories_map[$item_id] ?? [];

          $seller_id = (int) $row['seller_id'];
          $meta = [
              'seller' => $row['seller_name'],
              'seller_id' => $seller_id,
              'seller_rating' => $seller_ratings_map[$seller_id] ?? null,
              'created_at' => $row['created_at'] ?? null,
              'categories' => $categories,
              'image_path' => $row['image_path'] ?? null,
          ];

          print_listing_li($item_id, $title, $description, $current_price, $num_bids, $end_date, $meta);
      }
    ?>
    </ul>
    
    <!-- Pagination for Active Listings -->
    <?php if ($total_active_pages > 1): ?>
      <nav aria-label="Active listings pagination">
        <ul class="pagination justify-content-center mt-3">
          <?php if ($active_page > 1): ?>
            <li class="page-item">
              <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['active_page' => $active_page - 1])); ?>">Previous</a>
            </li>
          <?php else: ?>
            <li class="page-item disabled">
              <span class="page-link">Previous</span>
            </li>
          <?php endif; ?>
          
          <?php for ($i = 1; $i <= $total_active_pages; $i++): ?>
            <li class="page-item <?php echo $i == $active_page ? 'active' : ''; ?>">
              <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['active_page' => $i])); ?>"><?php echo $i; ?></a>
            </li>
          <?php endfor; ?>
          
          <?php if ($active_page < $total_active_pages): ?>
            <li class="page-item">
              <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['active_page' => $active_page + 1])); ?>">Next</a>
            </li>
          <?php else: ?>
            <li class="page-item disabled">
              <span class="page-link">Next</span>
            </li>
          <?php endif; ?>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Ended Listings Section -->
<div class="mb-4">
  <h4 class="mb-3">
    <i class="fa fa-history text-secondary"></i> Ended Listings
    <span class="badge badge-secondary"><?php echo $num_ended; ?></span>
  </h4>
  
  <?php if ($num_ended == 0): ?>
    <div class="alert alert-light">No ended auctions to show.</div>
  <?php else: ?>
    <ul class="list-group" id="endedResults">
    <?php
      foreach ($ended_paginated as $row) {
          $item_id = $row['auction_id'];
          $title = htmlspecialchars($row['title']);
          $description = $row['description'];
          $end_date = new DateTime($row['end_time']);
          $num_bids = (int) $row['bid_count'];
          $current_price = $row['current_price'];

          $categories = $categories_map[$item_id] ?? [];

          $seller_id = (int) $row['seller_id'];
          $meta = [
              'seller' => $row['seller_name'],
              'seller_id' => $seller_id,
              'seller_rating' => $seller_ratings_map[$seller_id] ?? null,
              'created_at' => $row['created_at'] ?? null,
              'categories' => $categories,
              'image_path' => $row['image_path'] ?? null,
          ];

          print_listing_li($item_id, $title, $description, $current_price, $num_bids, $end_date, $meta);
      }
    ?>
    </ul>
    
    <!-- Pagination for Ended Listings -->
    <?php if ($total_ended_pages > 1): ?>
      <nav aria-label="Ended listings pagination">
        <ul class="pagination justify-content-center mt-3">
          <?php if ($ended_page > 1): ?>
            <li class="page-item">
              <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['ended_page' => $ended_page - 1])); ?>">Previous</a>
            </li>
          <?php else: ?>
            <li class="page-item disabled">
              <span class="page-link">Previous</span>
            </li>
          <?php endif; ?>
          
          <?php for ($i = 1; $i <= $total_ended_pages; $i++): ?>
            <li class="page-item <?php echo $i == $ended_page ? 'active' : ''; ?>">
              <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['ended_page' => $i])); ?>"><?php echo $i; ?></a>
            </li>
          <?php endfor; ?>
          
          <?php if ($ended_page < $total_ended_pages): ?>
            <li class="page-item">
              <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['ended_page' => $ended_page + 1])); ?>">Next</a>
            </li>
          <?php else: ?>
            <li class="page-item disabled">
              <span class="page-link">Next</span>
            </li>
          <?php endif; ?>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

</div>

<?php include_once("footer.php")?>

<script>
(function() {
  const buttons = document.querySelectorAll('.sort-button');
  const list = document.getElementById('browseResults');
  if (!list) {
    return;
  }

  function compare(a, b, key, direction = 'asc') {
    const valA = parseFloat(a.dataset[key]) || 0;
    const valB = parseFloat(b.dataset[key]) || 0;
    if (valA === valB) return 0;
    const result = valA < valB ? -1 : 1;
    return direction === 'asc' ? result : -result;
  }

  function parseDate(value) {
    return value ? new Date(value).getTime() : 0;
  }

  buttons.forEach(btn => {
    btn.addEventListener('click', () => {
      const items = Array.from(list.querySelectorAll('.auction-listing'));
      let sorted;
      switch (btn.dataset.sort) {
        case 'price-desc':
          sorted = items.sort((a, b) => compare(a, b, 'price', 'desc'));
          break;
        case 'end-soon':
          sorted = items.sort((a, b) => parseDate(a.dataset.end) - parseDate(b.dataset.end));
          break;
        case 'newest':
          sorted = items.sort((a, b) => parseDate(b.dataset.created) - parseDate(a.dataset.created));
          break;
        case 'bids':
          sorted = items.sort((a, b) => compare(a, b, 'bids', 'desc'));
          break;
        case 'price-asc':
        default:
          sorted = items.sort((a, b) => compare(a, b, 'price', 'asc'));
      }
      sorted.forEach(item => list.appendChild(item));
    });
  });
})();
</script>