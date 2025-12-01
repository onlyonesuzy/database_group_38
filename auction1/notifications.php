<?php include_once("header.php")?>
<?php require_once("db_connect.php"); ?>
<?php require_once("auction_helpers.php"); ?>

<div class="container" style="max-width: 800px;">

<h2 class="my-3">Notifications</h2>

<?php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo '<div class="alert alert-danger">Please login to view your notifications.</div>';
    include_once("footer.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Mark all as read if requested
if (isset($_GET['mark_read']) && $_GET['mark_read'] === 'all') {
    $mark_stmt = $conn->prepare("UPDATE Notifications SET is_read = 1 WHERE user_id = ?");
    $mark_stmt->bind_param("i", $user_id);
    $mark_stmt->execute();
    $mark_stmt->close();
    header("Location: notifications.php");
    exit();
}

// Mark single as read
if (isset($_GET['read']) && ctype_digit($_GET['read'])) {
    $notif_id = (int) $_GET['read'];
    $mark_stmt = $conn->prepare("UPDATE Notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $mark_stmt->bind_param("ii", $notif_id, $user_id);
    $mark_stmt->execute();
    $mark_stmt->close();
    
    // Get the link to redirect to
    $link_stmt = $conn->prepare("SELECT link FROM Notifications WHERE notification_id = ?");
    $link_stmt->bind_param("i", $notif_id);
    $link_stmt->execute();
    $link_result = $link_stmt->get_result();
    $notif = $link_result->fetch_assoc();
    $link_stmt->close();
    
    if ($notif && !empty($notif['link'])) {
        header("Location: " . $notif['link']);
        exit();
    }
}

// Fetch notifications
$stmt = $conn->prepare("SELECT * FROM Notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

$unread_count = getUnreadNotificationCount($conn, $user_id);
?>

<?php if ($unread_count > 0): ?>
<div class="mb-3">
    <a href="notifications.php?mark_read=all" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-check"></i> Mark all as read
    </a>
</div>
<?php endif; ?>

<?php if (empty($notifications)): ?>
    <div class="alert alert-info">
        <i class="fa fa-bell-o"></i> You have no notifications yet.
    </div>
<?php else: ?>
    <div class="list-group">
    <?php foreach ($notifications as $notif): 
        $is_unread = !$notif['is_read'];
        $bg_class = $is_unread ? 'bg-light border-primary' : '';
        $created = new DateTime($notif['created_at']);
        
        // Icon based on type
        $icon = 'fa-bell';
        if ($notif['type'] === 'auction_won') $icon = 'fa-trophy text-success';
        elseif ($notif['type'] === 'buy_now') $icon = 'fa-shopping-cart text-success';
        elseif ($notif['type'] === 'item_sold') $icon = 'fa-money text-success';
        elseif ($notif['type'] === 'outbid') $icon = 'fa-exclamation-triangle text-warning';
    ?>
        <a href="notifications.php?read=<?php echo $notif['notification_id']; ?>" 
           class="list-group-item list-group-item-action <?php echo $bg_class; ?>">
            <div class="d-flex w-100 justify-content-between align-items-start">
                <div>
                    <i class="fa <?php echo $icon; ?> mr-2"></i>
                    <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
                    <?php if ($is_unread): ?>
                        <span class="badge badge-primary ml-2">New</span>
                    <?php endif; ?>
                    <?php if (!empty($notif['message'])): ?>
                        <p class="mb-0 mt-1 text-muted"><?php echo htmlspecialchars($notif['message']); ?></p>
                    <?php endif; ?>
                </div>
                <small class="text-muted text-nowrap ml-3"><?php echo $created->format('j M Y H:i'); ?></small>
            </div>
        </a>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

</div>

<?php include_once("footer.php")?>

