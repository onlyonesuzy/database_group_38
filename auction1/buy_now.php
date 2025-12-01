<?php
session_start();
require_once('db_connect.php');
require_once('auction_helpers.php');

// Check if user is logged in and is a buyer
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

if (!in_array($_SESSION['account_type'], ['buyer', 'both'])) {
    header('Location: browse.php?error=not_buyer');
    exit();
}

$item_id = $_POST['item_id'] ?? null;
if (!$item_id || !ctype_digit((string)$item_id)) {
    header('Location: browse.php?error=invalid_auction');
    exit();
}

$item_id = (int)$item_id;
$user_id = $_SESSION['user_id'];

// Get auction details
$stmt = $conn->prepare("SELECT * FROM Auctions WHERE auction_id = ?");
$stmt->bind_param('i', $item_id);
$stmt->execute();
$result = $stmt->get_result();
$auction = $result->fetch_assoc();
$stmt->close();

if (!$auction) {
    header('Location: browse.php?error=auction_not_found');
    exit();
}

// Check if auction has ended
$end_time = new DateTime($auction['end_time']);
$now = new DateTime();
if ($now >= $end_time) {
    header("Location: listing.php?item_id=$item_id&error=auction_ended");
    exit();
}

// Check if Buy Now is available
if (empty($auction['buy_now_price']) || $auction['buy_now_price'] <= 0) {
    header("Location: listing.php?item_id=$item_id&error=no_buy_now");
    exit();
}

// Check if already bought
if (!empty($auction['bought_now'])) {
    header("Location: listing.php?item_id=$item_id&error=already_purchased");
    exit();
}

// Check buyer is not the seller
if ($auction['user_id'] == $user_id) {
    header("Location: listing.php?item_id=$item_id&error=own_auction");
    exit();
}

$buy_now_price = $auction['buy_now_price'];

// Process the Buy Now
try {
    $conn->begin_transaction();

    // Place a bid at the Buy Now price
    $bid_stmt = $conn->prepare("INSERT INTO Bids (auction_id, user_id, bid_amount) VALUES (?, ?, ?)");
    $bid_stmt->bind_param('iid', $item_id, $user_id, $buy_now_price);
    $bid_stmt->execute();
    $bid_stmt->close();

    // Mark auction as bought now and set winner
    $update_stmt = $conn->prepare("UPDATE Auctions SET bought_now = 1, winner_user_id = ?, end_time = NOW() WHERE auction_id = ?");
    $update_stmt->bind_param('ii', $user_id, $item_id);
    $update_stmt->execute();
    $update_stmt->close();

    $conn->commit();
    
    // Get buyer and seller info for emails
    $buyer_stmt = $conn->prepare("SELECT username, email FROM Users WHERE user_id = ?");
    $buyer_stmt->bind_param('i', $user_id);
    $buyer_stmt->execute();
    $buyer_data = $buyer_stmt->get_result()->fetch_assoc();
    $buyer_stmt->close();
    
    $seller_stmt = $conn->prepare("SELECT username, email FROM Users WHERE user_id = ?");
    $seller_stmt->bind_param('i', $auction['user_id']);
    $seller_stmt->execute();
    $seller_data = $seller_stmt->get_result()->fetch_assoc();
    $seller_stmt->close();
    
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $link = sprintf('%s://%s%s/listing.php?item_id=%d', $scheme, $host, $basePath, $item_id);
    $notif_link = 'listing.php?item_id=' . $item_id;
    
    // Email and notification for BUYER
    if ($buyer_data) {
        $buyer_subject = 'Purchase Confirmed: "' . $auction['title'] . '"';
        $buyer_message = "Hi {$buyer_data['username']},\n\nYour purchase has been confirmed!\n\nItem: {$auction['title']}\nPrice: £" . number_format($buy_now_price, 2) . "\n\nPlease log in to arrange payment and delivery.\n\nLink: {$link}\n\nThank you for your purchase!";
        sendWinnerEmail($buyer_data['email'], $buyer_subject, $buyer_message);
    }
    $notif_title = 'Purchase Successful: "' . $auction['title'] . '"';
    $notif_message = 'You purchased this item via Buy Now for £' . number_format($buy_now_price, 2) . '.';
    createNotification($conn, $user_id, 'buy_now', $notif_title, $notif_message, $notif_link);
    
    // Email and notification for SELLER
    if ($seller_data) {
        $seller_subject = 'Item Sold: "' . $auction['title'] . '"';
        $seller_message = "Hi {$seller_data['username']},\n\nGreat news! Your item has been sold via Buy Now!\n\nItem: {$auction['title']}\nBuyer: {$buyer_data['username']}\nPrice: £" . number_format($buy_now_price, 2) . "\n\nPlease log in to arrange delivery.\n\nLink: {$link}\n\nThank you for selling with us!";
        sendWinnerEmail($seller_data['email'], $seller_subject, $seller_message);
    }
    $seller_notif_title = 'Item Sold: "' . $auction['title'] . '"';
    $seller_notif_message = 'Your item was purchased via Buy Now for £' . number_format($buy_now_price, 2) . '.';
    createNotification($conn, $auction['user_id'], 'item_sold', $seller_notif_title, $seller_notif_message, $notif_link);

    // Redirect with success message
    header("Location: listing.php?item_id=$item_id&success=buy_now&amount=" . number_format($buy_now_price, 2));

} catch (Exception $e) {
    $conn->rollback();
    header("Location: listing.php?item_id=$item_id&error=purchase_failed");
}

$conn->close();
exit();
?>
