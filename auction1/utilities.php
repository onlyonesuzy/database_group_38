<?php

// display_time_remaining:
// Helper function to help figure out what time to display
if (!function_exists('display_time_remaining')) {
  function display_time_remaining($interval) {
    if ($interval->days == 0 && $interval->h == 0) {
      // Less than one hour remaining: print mins + seconds:
      $time_remaining = $interval->format('%im %Ss');
    }
    else if ($interval->days == 0) {
      // Less than one day remaining: print hrs + mins:
      $time_remaining = $interval->format('%hh %im');
    }
    else {
      // At least one day remaining: print days + hrs:
      $time_remaining = $interval->format('%ad %hh');
    }

    return $time_remaining;
  }
}

/**
 * Format a price consistently.
 */
if (!function_exists('format_price')) {
  function format_price($value) {
    if ($value === null || $value === '') {
      return '0.00';
    }
    return number_format((float) $value, 2);
  }
}

/**
 * Render the badge HTML for category pills.
 */
if (!function_exists('render_category_badges')) {
  function render_category_badges($categories) {
    if (empty($categories)) {
      return '';
    }

    $badges = array_map(function ($category) {
      $name = htmlspecialchars($category['name']);
      return '<span class="badge badge-pill badge-secondary mr-1 mb-1">' . $name . '</span>';
    }, $categories);

    return '<div class="mt-2">' . implode('', $badges) . '</div>';
  }
}

/**
 * Load ordered categories for a list of auctions.
 *
 * @return array<int, array<int, array{id:int,name:string}>>
 */
if (!function_exists('fetch_categories_for_auctions')) {
  function fetch_categories_for_auctions(mysqli $conn, array $auction_ids) {
    if (empty($auction_ids)) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($auction_ids), '?'));
    $types = str_repeat('i', count($auction_ids));

    $stmt = $conn->prepare("
      SELECT ac.auction_id, ac.position, c.category_id, c.category_name
      FROM AuctionCategories ac
      JOIN Categories c ON ac.category_id = c.category_id
      WHERE ac.auction_id IN ($placeholders)
      ORDER BY ac.auction_id ASC, ac.position ASC, c.category_name ASC
    ");

    if (!$stmt) {
      return [];
    }

    $stmt->bind_param($types, ...$auction_ids);
    $stmt->execute();
    $result = $stmt->get_result();

    $map = [];
    while ($row = $result->fetch_assoc()) {
      $auctionId = (int) $row['auction_id'];
      if (!isset($map[$auctionId])) {
        $map[$auctionId] = [];
      }
      $map[$auctionId][] = [
        'id' => (int) $row['category_id'],
        'name' => $row['category_name'],
      ];
    }

    $stmt->close();
    return $map;
  }
}

// print_listing_li:
// This function prints an HTML <li> element containing an auction listing
if (!function_exists('print_listing_li')) {
  function print_listing_li($item_id, $title, $desc, $price, $num_bids, $end_time, $meta = [])
  {
    // Truncate long descriptions
    if (strlen($desc) > 250) {
      $desc_shortened = substr($desc, 0, 250) . '...';
    }
    else {
      $desc_shortened = $desc;
    }
    $desc_shortened = nl2br(htmlspecialchars($desc_shortened));
    
    // Fix language of bid vs. bids
    if ($num_bids == 1) {
      $bid = ' bid';
    }
    else {
      $bid = ' bids';
    }
    
    // Calculate time to auction end
    $now = new DateTime();
    if ($now > $end_time) {
      $time_remaining = 'This auction has ended';
    }
    else {
      // Get interval:
      $time_to_end = date_diff($now, $end_time);
      $time_remaining = display_time_remaining($time_to_end) . ' remaining';
    }
    
    $seller = $meta['seller'] ?? null;
    $seller_id = $meta['seller_id'] ?? null;
    $seller_rating = $meta['seller_rating'] ?? null;
    $created_at = $meta['created_at'] ?? null;
    if ($created_at && !($created_at instanceof DateTime)) {
      $created_at = new DateTime($created_at);
    }
    $categories = $meta['categories'] ?? [];
    $image_path = $meta['image_path'] ?? null;

    // Build seller info with rating
    $seller_html = '';
    if ($seller) {
      $seller_link = $seller_id ? '<a href="seller_profile.php?id=' . $seller_id . '" onclick="event.stopPropagation()">' . htmlspecialchars($seller) . '</a>' : '<strong>' . htmlspecialchars($seller) . '</strong>';
      $seller_html = $seller_link;
      
      if ($seller_rating !== null && $seller_rating['total'] > 0) {
        $avg = $seller_rating['average'];
        $total = $seller_rating['total'];
        $stars_html = '';
        for ($i = 1; $i <= 5; $i++) {
          if ($avg >= $i) {
            $stars_html .= '<i class="fa fa-star text-warning"></i>';
          } elseif ($avg >= $i - 0.5) {
            $stars_html .= '<i class="fa fa-star-half-o text-warning"></i>';
          } else {
            $stars_html .= '<i class="fa fa-star-o text-muted"></i>';
          }
        }
        $seller_html .= ' <span class="ml-1">' . $stars_html . '</span>';
        $seller_html .= ' <span class="text-muted small">(' . $avg . ')</span>';
      }
    }

    $meta_fragments = [];
    if ($seller_html) {
      $meta_fragments[] = $seller_html;
    }
    if ($created_at) {
      $meta_fragments[] = 'on ' . $created_at->format('j M Y H:i');
    }
    $meta_line = '';
    if (!empty($meta_fragments)) {
      $meta_line = '<div class="text-muted small mt-2">' . implode(' ', $meta_fragments) . '</div>';
    }

    $badge_html = render_category_badges($categories);

    $data_attrs = sprintf(
      'data-price=\"%s\" data-bids=\"%d\" data-end=\"%s\" data-created=\"%s\"',
      htmlspecialchars($price),
      (int) $num_bids,
      htmlspecialchars($end_time->format(DateTime::ATOM)),
      $created_at ? htmlspecialchars($created_at->format(DateTime::ATOM)) : ''
    );

    // Image thumbnail HTML
    $image_html = '';
    if ($image_path) {
      $image_html = '<div class="mr-3 mb-2 mb-md-0" style="flex-shrink: 0;">
        <a href="listing.php?item_id=' . $item_id . '" onclick="event.stopPropagation()">
          <img src="' . htmlspecialchars($image_path) . '" alt="' . htmlspecialchars($title) . '" 
               class="img-thumbnail" style="width: 80px; height: 80px; object-fit: cover;">
        </a>
      </div>';
    }

    // Print HTML
    echo('
      <li class="list-group-item d-flex justify-content-between align-items-center flex-column flex-md-row auction-listing clickable-listing" 
          ' . $data_attrs . ' 
          data-listing-url="listing.php?item_id=' . $item_id . '"
          onclick="handleListingClick(event, \'listing.php?item_id=' . $item_id . '\')">
        <div class="d-flex align-items-start p-2 mr-md-5 w-100">
          ' . $image_html . '
          <div class="flex-grow-1">
            <h5><a href="listing.php?item_id=' . $item_id . '" onclick="event.stopPropagation()">' . $title . '</a></h5>
            <div class="auction-description">' . $desc_shortened . '</div>
            ' . $meta_line . '
            ' . $badge_html . '
          </div>
        </div>
        <div class="text-center text-nowrap px-2">
          <span class="d-block" style="font-size: 1.5em">£' . format_price($price) . '</span>
          <span class="d-block">' . $num_bids . $bid . '</span>
          <span class="d-block">' . $time_remaining . '</span>
        </div>
      </li>'
    );
  }
}

?>
