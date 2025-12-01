<?php
require_once('db_connect.php');

// Find ALL McLaren 720s auctions (case insensitive, various spellings)
// First, get all auctions and filter in PHP for more flexibility
$stmt = $conn->prepare("SELECT auction_id, title FROM Auctions");
$stmt->execute();
$result = $stmt->get_result();

$found_auctions = [];
while ($row = $result->fetch_assoc()) {
    $title_lower = strtolower($row['title']);
    // Check if title contains "mclaren" and "720" (in any case)
    if (strpos($title_lower, 'mclaren') !== false && strpos($title_lower, '720') !== false) {
        $found_auctions[] = $row;
    }
}
$stmt->close();

$deleted_count = 0;
$errors = [];

// Delete all found auctions
foreach ($found_auctions as $auction) {
    $delete_stmt = $conn->prepare("DELETE FROM Auctions WHERE auction_id = ?");
    $delete_stmt->bind_param('i', $auction['auction_id']);
    
    if ($delete_stmt->execute()) {
        $deleted_count++;
    } else {
        $errors[] = "Failed to delete auction ID: " . $auction['auction_id'];
    }
    $delete_stmt->close();
}

$conn->close();

// Display results
include_once('header.php');
?>

<div class="container mt-5">
    <h2>Delete McLaren 720s Auction - Force Delete</h2>
    
    <?php if (count($found_auctions) > 0): ?>
        <div class="alert alert-info">
            <p><strong>Found <?php echo count($found_auctions); ?> auction(s):</strong></p>
            <ul>
                <?php foreach ($found_auctions as $auction): ?>
                    <li>ID: <?php echo $auction['auction_id']; ?> - <?php echo htmlspecialchars($auction['title']); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <?php if ($deleted_count > 0): ?>
            <div class="alert alert-success">
                <p><strong>✓ Successfully deleted <?php echo $deleted_count; ?> auction(s).</strong></p>
                <p>The following related data has also been automatically deleted (due to CASCADE):</p>
                <ul>
                    <li>All bids for these auctions</li>
                    <li>All watchlist entries</li>
                    <li>All category associations</li>
                    <li>All notifications related to these auctions</li>
                </ul>
                <p><strong>Please refresh your browser (Ctrl+F5 or Cmd+Shift+R) to clear cache and see the changes.</strong></p>
                <p><a href="browse.php" class="btn btn-primary">Go to Browse Listings</a></p>
            </div>
        <?php endif; ?>
        
        <?php if (count($errors) > 0): ?>
            <div class="alert alert-danger">
                <p><strong>Errors:</strong></p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-warning">
            <p>No McLaren 720s auctions found in the database.</p>
            <p>If you still see it on the page, try:</p>
            <ul>
                <li>Hard refresh your browser (Ctrl+F5 or Cmd+Shift+R)</li>
                <li>Clear your browser cache</li>
                <li>Check if there are other similar listings with different titles</li>
            </ul>
            <p><a href="browse.php" class="btn btn-primary">Go to Browse Listings</a></p>
        </div>
    <?php endif; ?>
</div>

<?php include_once('footer.php'); ?>

