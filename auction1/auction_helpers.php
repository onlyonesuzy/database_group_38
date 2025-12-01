<?php
require_once(__DIR__ . '/utilities.php');

if (!function_exists('createNotification')) {
  function createNotification(mysqli $conn, int $user_id, string $type, string $title, string $message = '', string $link = ''): bool {
    $stmt = $conn->prepare("INSERT INTO Notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param("issss", $user_id, $type, $title, $message, $link);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
  }
}

if (!function_exists('getUnreadNotificationCount')) {
  function getUnreadNotificationCount(mysqli $conn, int $user_id): int {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Notifications WHERE user_id = ? AND is_read = 0");
    if (!$stmt) {
      return 0;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return (int) ($row['count'] ?? 0);
  }
}

if (!function_exists('sendWinnerEmail')) {
  function sendWinnerEmail(string $to, string $subject, string $message): bool {
    $headers = "From: no-reply@auction.local\r\n";
    $sent = @mail($to, $subject, $message, $headers);
    if ($sent) {
      return true;
    }

    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
      @mkdir($logDir, 0777, true);
    }
    $logEntry = sprintf("[%s] To: %s\nSubject: %s\n%s\n\n", date('c'), $to, $subject, $message);
    @file_put_contents($logDir . '/email.log', $logEntry, FILE_APPEND);
    return false;
  }
}

if (!function_exists('finalizeAuctionIfNeeded')) {
  function finalizeAuctionIfNeeded(mysqli $conn, array $auctionRow): ?array {
    if (empty($auctionRow['auction_id']) || empty($auctionRow['end_time'])) {
      return null;
    }

    $now = new DateTime();
    $end_time = new DateTime($auctionRow['end_time']);

    if ($now <= $end_time) {
      return null;
    }

    if (!empty($auctionRow['winner_notification_sent'])) {
      return null;
    }

    $winner_stmt = $conn->prepare("SELECT b.user_id, u.username, u.email, b.bid_amount\n                                       FROM Bids b\n                                       JOIN Users u ON u.user_id = b.user_id\n                                       WHERE b.auction_id = ?\n                                       ORDER BY b.bid_amount DESC, b.bid_time ASC\n                                       LIMIT 1");
    if (!$winner_stmt) {
      return null;
    }
    $winner_stmt->bind_param('i', $auctionRow['auction_id']);
    $winner_stmt->execute();
    $winner_result = $winner_stmt->get_result();
    $winner_data = $winner_result->fetch_assoc();
    $winner_stmt->close();

    if (!$winner_data) {
      $update = $conn->prepare("UPDATE Auctions SET winner_notification_sent = 1, winner_user_id = NULL, winner_notified_at = NOW() WHERE auction_id = ?");
      $update->bind_param('i', $auctionRow['auction_id']);
      $update->execute();
      $update->close();
      return null;
    }

    $winner_id = (int) $winner_data['user_id'];
    $update = $conn->prepare("UPDATE Auctions SET winner_notification_sent = 1, winner_user_id = ?, winner_notified_at = NOW() WHERE auction_id = ?");
    $update->bind_param('ii', $winner_id, $auctionRow['auction_id']);
    $update->execute();
    $update->close();

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $link = sprintf('%s://%s%s/listing.php?item_id=%d',
      $scheme,
      $host,
      $basePath === '' ? '' : $basePath,
      $auctionRow['auction_id']
    );
    $notif_link = 'listing.php?item_id=' . $auctionRow['auction_id'];
    
    // Email and notification to WINNER
    $subject = 'You won the auction for "' . $auctionRow['title'] . '"';
    $message = "Hi {$winner_data['username']},\n\nCongratulations! You won the auction for {$auctionRow['title']}.\nYour winning bid: £" . number_format($winner_data['bid_amount'], 2) . "\n\nPlease log in to your account to complete the transaction.\n\nLink: {$link}\n\nThank you for using our marketplace.";
    $email_sent = sendWinnerEmail($winner_data['email'], $subject, $message);
    
    // Create in-app notification for winner
    $notif_title = 'Congratulations! You won "' . $auctionRow['title'] . '"';
    $notif_message = 'Your winning bid of £' . number_format($winner_data['bid_amount'], 2) . ' won this auction.';
    createNotification($conn, $winner_id, 'auction_won', $notif_title, $notif_message, $notif_link);
    
    // Email and notification to SELLER
    $seller_id = (int) $auctionRow['user_id'];
    $seller_stmt = $conn->prepare("SELECT username, email FROM Users WHERE user_id = ?");
    $seller_stmt->bind_param('i', $seller_id);
    $seller_stmt->execute();
    $seller_result = $seller_stmt->get_result();
    $seller_data = $seller_result->fetch_assoc();
    $seller_stmt->close();
    
    if ($seller_data) {
      $seller_subject = 'Your auction "' . $auctionRow['title'] . '" has ended - Item Sold!';
      $seller_message = "Hi {$seller_data['username']},\n\nGreat news! Your auction for {$auctionRow['title']} has ended.\n\nWinner: {$winner_data['username']}\nFinal Price: £" . number_format($winner_data['bid_amount'], 2) . "\n\nPlease log in to your account to arrange delivery.\n\nLink: {$link}\n\nThank you for using our marketplace.";
      sendWinnerEmail($seller_data['email'], $seller_subject, $seller_message);
      
      // Create in-app notification for seller
      $seller_notif_title = 'Your auction "' . $auctionRow['title'] . '" has sold!';
      $seller_notif_message = 'Sold to ' . $winner_data['username'] . ' for £' . number_format($winner_data['bid_amount'], 2) . '.';
      createNotification($conn, $seller_id, 'item_sold', $seller_notif_title, $seller_notif_message, $notif_link);
    }

    return [
      'winner_user_id' => $winner_id,
      'username' => $winner_data['username'],
      'email' => $winner_data['email'],
      'amount' => $winner_data['bid_amount'],
      'email_sent' => $email_sent,
    ];
  }
}

if (!function_exists('fetchWinnerInfo')) {
  function fetchWinnerInfo(mysqli $conn, int $auction_id): ?array {
    $stmt = $conn->prepare("SELECT b.user_id, u.username, b.bid_amount, b.bid_time\n                              FROM Bids b\n                              JOIN Users u ON u.user_id = b.user_id\n                              WHERE b.auction_id = ?\n                              ORDER BY b.bid_amount DESC, b.bid_time ASC\n                              LIMIT 1");
    if (!$stmt) {
      return null;
    }
    $stmt->bind_param('i', $auction_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $winner = $result->fetch_assoc();
    $stmt->close();
    return $winner ?: null;
  }
}

if (!function_exists('fetchAuctionCardData')) {
  function fetchAuctionCardData(mysqli $conn, string $sql, string $types, array $params): array {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      return [];
    }

    if ($types !== '') {
      $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    $ids = [];
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
      $ids[] = (int) $row['auction_id'];
    }
    $stmt->close();

    if (empty($ids)) {
      return [];
    }

    $categories_map = fetch_categories_for_auctions($conn, $ids);
    foreach ($rows as &$row) {
      $row['categories'] = $categories_map[$row['auction_id']] ?? [];
    }

    return $rows;
  }
}

if (!function_exists('fetchAlsoBiddingAuctions')) {
  function fetchAlsoBiddingAuctions(mysqli $conn, int $auction_id): array {
    $sql = "SELECT a.auction_id, a.title, a.description, a.end_time, a.created_at, u.username AS seller_name,\n                 COALESCE(agg.max_bid, a.start_price) AS current_price,\n                 COALESCE(agg.bid_count, 0) AS bid_count,\n                 COUNT(DISTINCT pivot.user_id) AS overlap_count\n          FROM Bids pivot\n          JOIN Bids other ON pivot.user_id = other.user_id\n          JOIN Auctions a ON a.auction_id = other.auction_id\n          JOIN Users u ON u.user_id = a.user_id\n          LEFT JOIN (\n              SELECT auction_id, MAX(bid_amount) AS max_bid, COUNT(*) AS bid_count\n              FROM Bids\n              GROUP BY auction_id\n          ) agg ON agg.auction_id = a.auction_id\n          WHERE pivot.auction_id = ?\n            AND other.auction_id != ?\n            AND a.end_time > NOW()\n          GROUP BY a.auction_id\n          ORDER BY overlap_count DESC, a.end_time ASC\n          LIMIT 4";
    return fetchAuctionCardData($conn, $sql, 'ii', [$auction_id, $auction_id]);
  }
}

if (!function_exists('fetchSimilarCategoryAuctions')) {
  function fetchSimilarCategoryAuctions(mysqli $conn, int $auction_id, array $category_ids): array {
    $category_ids = array_values(array_unique(array_map('intval', $category_ids)));
    if (empty($category_ids)) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($category_ids), '?'));
    $sql = "SELECT DISTINCT a.auction_id, a.title, a.description, a.end_time, a.created_at, u.username AS seller_name,
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
            AND a.auction_id != ?
            AND a.end_time > NOW()
          GROUP BY a.auction_id
          ORDER BY a.end_time ASC
          LIMIT 4";
    $types = str_repeat('i', count($category_ids)) . 'i';
    $params = array_merge($category_ids, [$auction_id]);
    return fetchAuctionCardData($conn, $sql, $types, $params);
  }
}

/**
 * Get seller rating statistics (1-5 stars)
 */
if (!function_exists('getSellerRating')) {
  function getSellerRating(mysqli $conn, int $seller_id): array {
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total_ratings,
        AVG(rating) as avg_rating,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
      FROM Ratings WHERE seller_id = ?");
    
    if (!$stmt) {
      return ['total' => 0, 'average' => 0, 'stars' => [0,0,0,0,0]];
    }
    
    $stmt->bind_param('i', $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    $total = (int)($data['total_ratings'] ?? 0);
    $average = $total > 0 ? round((float)($data['avg_rating'] ?? 0), 1) : 0;
    
    $stars = [
      (int)($data['one_star'] ?? 0),
      (int)($data['two_star'] ?? 0),
      (int)($data['three_star'] ?? 0),
      (int)($data['four_star'] ?? 0),
      (int)($data['five_star'] ?? 0)
    ];
    
    return [
      'total' => $total,
      'average' => $average,
      'stars' => $stars
    ];
  }
}

/**
 * Render star icons HTML
 */
if (!function_exists('renderStars')) {
  function renderStars(float $rating, bool $large = false): string {
    $size = $large ? 'fa-lg' : '';
    $html = '<span class="star-rating">';
    for ($i = 1; $i <= 5; $i++) {
      if ($rating >= $i) {
        $html .= '<i class="fa fa-star text-warning ' . $size . '"></i>';
      } elseif ($rating >= $i - 0.5) {
        $html .= '<i class="fa fa-star-half-o text-warning ' . $size . '"></i>';
      } else {
        $html .= '<i class="fa fa-star-o text-muted ' . $size . '"></i>';
      }
    }
    $html .= '</span>';
    return $html;
  }
}

/**
 * Check if buyer can rate a seller for an auction
 */
if (!function_exists('canRateSeller')) {
  function canRateSeller(mysqli $conn, int $auction_id, int $buyer_id): bool {
    // Check if this buyer won the auction
    $stmt = $conn->prepare("SELECT winner_user_id FROM Auctions WHERE auction_id = ? AND end_time < NOW()");
    $stmt->bind_param('i', $auction_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $auction = $result->fetch_assoc();
    $stmt->close();
    
    if (!$auction || (int)$auction['winner_user_id'] !== $buyer_id) {
      return false;
    }
    
    // Check if already rated
    $check_stmt = $conn->prepare("SELECT rating_id FROM Ratings WHERE auction_id = ?");
    $check_stmt->bind_param('i', $auction_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $already_rated = $check_result->num_rows > 0;
    $check_stmt->close();
    
    return !$already_rated;
  }
}

/**
 * Submit a rating for a seller (1-5 stars)
 */
if (!function_exists('submitSellerRating')) {
  function submitSellerRating(mysqli $conn, int $auction_id, int $seller_id, int $buyer_id, int $rating, string $comment = ''): bool {
    if ($rating < 1 || $rating > 5) {
      return false;
    }
    
    $stmt = $conn->prepare("INSERT INTO Ratings (auction_id, seller_id, buyer_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
      return false;
    }
    
    $stmt->bind_param('iiiis', $auction_id, $seller_id, $buyer_id, $rating, $comment);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
  }
}

/**
 * Render seller rating badge HTML (with stars)
 */
if (!function_exists('renderSellerRatingBadge')) {
  function renderSellerRatingBadge(array $rating, string $seller_name, int $seller_id): string {
    $total = $rating['total'];
    $average = $rating['average'];
    
    $html = '<div class="seller-rating d-flex align-items-center p-2 rounded" style="background: #262626;">';
    $html .= '<i class="fa fa-user-circle fa-2x mr-3 text-info"></i>';
    $html .= '<div>';
    $html .= '<a href="seller_profile.php?id=' . $seller_id . '" class="font-weight-bold">' . htmlspecialchars($seller_name) . '</a>';
    
    if ($total > 0) {
      $html .= ' <span class="text-muted">(' . number_format($total) . ' ratings)</span>';
      $html .= '<br>' . renderStars($average) . ' <span class="ml-1">' . $average . '</span>';
      $html .= ' · <a href="seller_profile.php?id=' . $seller_id . '">Seller\'s other items</a>';
    } else {
      $html .= '<br><span class="text-muted">New seller · No ratings yet</span>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
  }
}
?>
