<?php
require_once('db_connect.php');

// Find the McLaren 720s auction
$stmt = $conn->prepare("SELECT auction_id, title FROM Auctions WHERE title LIKE ?");
$search = '%McLaren%720%';
$stmt->bind_param('s', $search);
$stmt->execute();
$result = $stmt->get_result();

$deleted_count = 0;
$found_auctions = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $found_auctions[] = $row;
    }
}

$stmt->close();

// Delete the auctions
foreach ($found_auctions as $auction) {
    $delete_stmt = $conn->prepare("DELETE FROM Auctions WHERE auction_id = ?");
    $delete_stmt->bind_param('i', $auction['auction_id']);
    
    if ($delete_stmt->execute()) {
        $deleted_count++;
    }
    $delete_stmt->close();
}

$conn->close();

// Display results
include_once('header.php');
?>

<div class="container mt-5">
    <h2>Delete McLaren 720s Auction</h2>
    
    <?php if (count($found_auctions) > 0): ?>
        <div class="alert alert-info">
            <p>Found <?php echo count($found_auctions); ?> auction(s) matching "McLaren 720s":</p>
            <ul>
                <?php foreach ($found_auctions as $auction): ?>
                    <li>ID: <?php echo $auction['auction_id']; ?> - <?php echo htmlspecialchars($auction['title']); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <?php if ($deleted_count > 0): ?>
            <div class="alert alert-success">
                <p>✓ Successfully deleted <?php echo $deleted_count; ?> auction(s).</p>
                <p><a href="browse.php">Return to Browse Listings</a></p>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <p>✗ Failed to delete auctions.</p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-warning">
            <p>No McLaren 720s auctions found in the database.</p>
            <p><a href="browse.php">Return to Browse Listings</a></p>
        </div>
    <?php endif; ?>
</div>

<?php include_once('footer.php'); ?>

