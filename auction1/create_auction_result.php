<?php include_once("header.php")?>
<?php require_once("db_connect.php")?>

<div class="container my-5">
<?php
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['account_type'], ['seller', 'both'])) {
    echo '<div class="alert alert-danger">Only sellers can create auctions.</div>';
    include_once("footer.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$title = trim($_POST['auctionTitle'] ?? '');
$details = trim($_POST['auctionDetails'] ?? '');
$category_order = trim($_POST['categoryOrder'] ?? '');
$start_price_raw = $_POST['auctionStartPrice'] ?? '';
$reserve_price_raw = $_POST['auctionReservePrice'] ?? '';
$end_date_raw = $_POST['auctionEndDate'] ?? '';

$category_ids = array_values(array_filter(array_map('intval', array_filter(explode(',', $category_order)))));
$errors = [];

if ($title === '') {
    $errors[] = 'Title is required.';
}
if ($details === '') {
    $errors[] = 'Description is required.';
}
if (empty($category_ids)) {
    $errors[] = 'Select at least one category and arrange them.';
}

if ($start_price_raw === '' || !is_numeric($start_price_raw) || (float)$start_price_raw <= 0) {
    $errors[] = 'Provide a valid starting price greater than 0.';
}
$start_price = round((float)$start_price_raw, 2);

$reserve_price = null;
if ($reserve_price_raw !== '') {
    if (!is_numeric($reserve_price_raw) || (float)$reserve_price_raw <= 0) {
        $errors[] = 'Reserve price must be a positive number if provided.';
    } else {
        $reserve_price = round((float)$reserve_price_raw, 2);
        if ($reserve_price < $start_price) {
            $errors[] = 'Reserve price cannot be lower than the starting price.';
        }
    }
}

$buy_now_price_raw = $_POST['auctionBuyNowPrice'] ?? '';
$buy_now_price = null;
if ($buy_now_price_raw !== '') {
    if (!is_numeric($buy_now_price_raw) || (float)$buy_now_price_raw <= 0) {
        $errors[] = 'Buy Now price must be a positive number if provided.';
    } else {
        $buy_now_price = round((float)$buy_now_price_raw, 2);
        if ($buy_now_price <= $start_price) {
            $errors[] = 'Buy Now price must be higher than the starting price.';
        }
    }
}

$end_time = null;
if ($end_date_raw === '') {
    $errors[] = 'End date is required.';
} else {
    try {
        // Set timezone to match your local time (UK time)
        $timezone = new DateTimeZone('Europe/London');
        $end_time = new DateTime($end_date_raw, $timezone);
        $now = new DateTime('now', $timezone);
        
        // Allow a small buffer (end time must be at least 1 minute from now)
        $now->modify('+1 minute');
        if ($end_time < $now) {
            $errors[] = 'End date must be at least a few minutes in the future.';
        }
    } catch (Exception $e) {
        $errors[] = 'Invalid end date supplied.';
    }
}

// Handle image upload
$image_path = null;
if (isset($_FILES['auctionImage']) && $_FILES['auctionImage']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['auctionImage'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        $errors[] = 'Invalid image type. Please upload JPG, PNG, GIF, or WebP.';
    } elseif ($file['size'] > $max_size) {
        $errors[] = 'Image file is too large. Maximum size is 5MB.';
    } else {
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('auction_') . '_' . time() . '.' . $extension;
        $upload_dir = __DIR__ . '/uploads/';
        $upload_path = $upload_dir . $filename;
        
        // Create uploads directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $image_path = 'uploads/' . $filename;
        } else {
            $errors[] = 'Failed to upload image. Please try again.';
        }
    }
}

if (!empty($errors)) {
    echo '<div class="alert alert-danger"><h5 class="mb-3">Please fix the following:</h5><ul>';
    foreach ($errors as $error) {
        echo '<li>' . htmlspecialchars($error) . '</li>';
    }
    echo '</ul><a href="create_auction.php" class="btn btn-outline-secondary mt-3">Back to form</a></div>';
    include_once("footer.php");
    exit();
}

$fee_percentage = 3.0;
$fee_amount = round($start_price * ($fee_percentage / 100), 2);
$seller_payout = round($start_price - $fee_amount, 2);
$end_time_sql = $end_time->format('Y-m-d H:i:s');

try {
    $conn->begin_transaction();

    // Insert auction - categories are linked via AuctionCategories junction table
    $insert_sql = "INSERT INTO Auctions (user_id, title, description, start_price, reserve_price, buy_now_price, end_time, fee_percentage, fee_amount, seller_payout, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param(
        'issdddsddds',
        $user_id,
        $title,
        $details,
        $start_price,
        $reserve_price,
        $buy_now_price,
        $end_time_sql,
        $fee_percentage,
        $fee_amount,
        $seller_payout,
        $image_path
    );
    $stmt->execute();
    $auction_id = $stmt->insert_id;
    $stmt->close();

    $cat_stmt = $conn->prepare("INSERT INTO AuctionCategories (auction_id, category_id, position) VALUES (?, ?, ?)");
    foreach ($category_ids as $position => $cat_id) {
        $cat_stmt->bind_param('iii', $auction_id, $cat_id, $position);
        $cat_stmt->execute();
    }
    $cat_stmt->close();

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    echo '<div class="alert alert-danger">Failed to create auction: ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<a href="create_auction.php" class="btn btn-outline-secondary mt-3">Try again</a>';
    include_once("footer.php");
    exit();
}

$category_labels = [];
if (!empty($category_ids)) {
    $placeholder = implode(',', array_fill(0, count($category_ids), '?'));
    $types = str_repeat('i', count($category_ids));
    $cat_lookup = $conn->prepare("SELECT category_id, category_name FROM Categories WHERE category_id IN ($placeholder)");
    $cat_lookup->bind_param($types, ...$category_ids);
    $cat_lookup->execute();
    $cat_res = $cat_lookup->get_result();
    while ($row = $cat_res->fetch_assoc()) {
        $category_labels[$row['category_id']] = $row['category_name'];
    }
    $cat_lookup->close();
}
?>

<div class="card">
  <div class="card-body text-center">
    <h3 class="mb-3">Auction successfully created!</h3>
    <?php if ($image_path): ?>
      <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($title); ?>" class="img-thumbnail mb-3" style="max-width: 300px; max-height: 300px;">
    <?php endif; ?>
    <p>Your listing <strong><?php echo htmlspecialchars($title); ?></strong> is now live.</p>
    <a href="listing.php?item_id=<?php echo $auction_id; ?>" class="btn btn-primary mb-3">View your new listing</a>
    <div class="row">
      <div class="col-md-4">
        <div class="border rounded p-3">
          <div class="text-muted">Listing price</div>
          <div class="h4">£<?php echo number_format($start_price, 2); ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="border rounded p-3">
          <div class="text-muted">Fee (3%)</div>
          <div class="h4">£<?php echo number_format($fee_amount, 2); ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="border rounded p-3">
          <div class="text-muted">Seller receives</div>
          <div class="h4">£<?php echo number_format($seller_payout, 2); ?></div>
        </div>
      </div>
    </div>
    <div class="mt-4 text-left">
      <h5>Categories (in order):</h5>
      <ol>
        <?php foreach ($category_ids as $cat_id): ?>
          <li><?php echo htmlspecialchars($category_labels[$cat_id] ?? ('Category #' . $cat_id)); ?></li>
        <?php endforeach; ?>
      </ol>
      <?php if ($reserve_price !== null): ?>
        <p class="mb-0"><strong>Reserve price:</strong> £<?php echo number_format($reserve_price, 2); ?></p>
      <?php endif; ?>
      <?php if ($buy_now_price !== null): ?>
        <p class="mb-0"><strong>Buy Now price:</strong> £<?php echo number_format($buy_now_price, 2); ?></p>
      <?php endif; ?>
      <p><strong>Auction ends:</strong> <?php echo $end_time->format('j M Y H:i'); ?></p>
    </div>
  </div>
</div>

</div>

<?php include_once("footer.php")?>
