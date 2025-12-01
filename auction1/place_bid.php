<?php
require_once("db_connect.php");
require_once("auction_helpers.php");
session_start();

// 1. Validate user is logged in and is a buyer
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !in_array($_SESSION['account_type'], ['buyer', 'both'])) {
    header("Location: browse.php");
    exit();
}

// 2. Get and validate input data
$item_id = $_POST['item_id'] ?? null;
$bid_amount = $_POST['bid'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$item_id || !$bid_amount) {
    header("Location: browse.php?error=missing_data");
    exit();
}

// 3. Query current auction status
$stmt = $conn->prepare("SELECT end_time, start_price, title FROM Auctions WHERE auction_id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    header("Location: browse.php?error=auction_not_found");
    exit();
}

$auction = $res->fetch_assoc();
$end_time = new DateTime($auction['end_time']);
$start_price = $auction['start_price'];
$auction_title = $auction['title'];
$now = new DateTime();

// 3.1 Check if auction has ended
if ($now > $end_time) {
    header("Location: listing.php?item_id=$item_id&error=auction_ended");
    exit();
}

// 3.2 Get current highest bid and bidder
$bid_stmt = $conn->prepare("SELECT b.bid_amount, b.user_id FROM Bids b WHERE b.auction_id = ? ORDER BY b.bid_amount DESC LIMIT 1");
$bid_stmt->bind_param("i", $item_id);
$bid_stmt->execute();
$bid_res = $bid_stmt->get_result();
$current_bid_info = $bid_res->fetch_assoc();
$current_max = $current_bid_info['bid_amount'] ?? null;
$current_high_bidder = $current_bid_info['user_id'] ?? null;

// Calculate minimum valid bid
if ($current_max === null) {
    $min_valid_bid = $start_price;
} else {
    $min_valid_bid = $current_max + 0.01;
}

// 3.3 Validate bid amount
if ($bid_amount < $min_valid_bid) {
    header("Location: listing.php?item_id=$item_id&error=bid_too_low&min=" . number_format($min_valid_bid, 2));
    exit();
}

// 4. Insert new bid
$insert_stmt = $conn->prepare("INSERT INTO Bids (auction_id, user_id, bid_amount) VALUES (?, ?, ?)");
$insert_stmt->bind_param("iid", $item_id, $user_id, $bid_amount);

if ($insert_stmt->execute()) {
    // Notify the previous high bidder that they've been outbid
    if ($current_high_bidder && $current_high_bidder != $user_id) {
        $notif_link = 'listing.php?item_id=' . $item_id;
        $notif_title = 'You\'ve been outbid on "' . $auction_title . '"';
        $notif_message = 'Someone placed a higher bid of £' . number_format($bid_amount, 2) . '. Place a new bid to stay in the auction!';
        createNotification($conn, $current_high_bidder, 'outbid', $notif_title, $notif_message, $notif_link);
    }
    
    // Redirect back to listing with success message
    header("Location: listing.php?item_id=$item_id&success=bid_placed&amount=" . number_format($bid_amount, 2));
} else {
    header("Location: listing.php?item_id=$item_id&error=bid_failed");
}

$conn->close();
exit();
?>
