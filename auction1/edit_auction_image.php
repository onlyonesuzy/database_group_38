<?php include_once("header.php")?>
<?php require_once("db_connect.php")?>

<div class="container my-5">
<?php
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['account_type'], ['seller', 'both'])) {
    echo '<div class="alert alert-danger">Only sellers can edit auction images.</div>';
    include_once("footer.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$auction_id = $_GET['auction_id'] ?? null;

// If no auction_id provided, show list of seller's auctions
if (!$auction_id) {
    $stmt = $conn->prepare("SELECT auction_id, title, image_path, end_time FROM Auctions WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo '<h2 class="my-3">Edit Auction Image</h2>';
    echo '<p class="text-muted">Select an auction to change its image:</p>';
    
    if ($result->num_rows == 0) {
        echo '<div class="alert alert-info">You haven\'t created any auctions yet.</div>';
        echo '<a href="create_auction.php" class="btn btn-primary">Create New Auction</a>';
    } else {
        echo '<div class="list-group">';
        while ($row = $result->fetch_assoc()) {
            $end_time = new DateTime($row['end_time']);
            $now = new DateTime();
            $is_active = $end_time > $now;
            
            echo '<a href="?auction_id=' . $row['auction_id'] . '" class="list-group-item list-group-item-action">';
            echo '<div class="d-flex justify-content-between align-items-center">';
            echo '<div class="d-flex align-items-center">';
            if ($row['image_path']) {
                echo '<img src="' . htmlspecialchars($row['image_path']) . '" alt="' . htmlspecialchars($row['title']) . '" class="mr-3" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">';
            } else {
                echo '<div class="mr-3 bg-light d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; border-radius: 8px;"><i class="fa fa-image text-muted"></i></div>';
            }
            echo '<div>';
            echo '<h5 class="mb-1">' . htmlspecialchars($row['title']) . '</h5>';
            echo '<small class="text-muted">Auction ID: ' . $row['auction_id'] . '</small>';
            if ($is_active) {
                echo ' <span class="badge badge-success">Active</span>';
            } else {
                echo ' <span class="badge badge-secondary">Ended</span>';
            }
            echo '</div>';
            echo '</div>';
            echo '<i class="fa fa-chevron-right text-muted"></i>';
            echo '</a>';
        }
        echo '</div>';
    }
    $stmt->close();
    include_once("footer.php");
    exit();
}

// Verify that the auction belongs to the current user
$stmt = $conn->prepare("SELECT auction_id, title, image_path FROM Auctions WHERE auction_id = ? AND user_id = ?");
$stmt->bind_param('ii', $auction_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo '<div class="alert alert-danger">Auction not found or you don\'t have permission to edit it.</div>';
    echo '<a href="edit_auction_image.php" class="btn btn-outline-secondary">Back to My Auctions</a>';
    include_once("footer.php");
    exit();
}

$auction = $result->fetch_assoc();
$stmt->close();

// Handle image upload
$upload_success = false;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['newImage']) && $_FILES['newImage']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['newImage'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        $error_message = 'Invalid image type. Please upload JPG, PNG, GIF, or WebP.';
    } elseif ($file['size'] > $max_size) {
        $error_message = 'Image file is too large. Maximum size is 5MB.';
    } else {
        // Get the old image path
        $old_image_path = $auction['image_path'];
        
        if ($old_image_path && file_exists(__DIR__ . '/' . $old_image_path)) {
            // Delete old image file
            unlink(__DIR__ . '/' . $old_image_path);
        }
        
        // Generate new filename (keep same path structure)
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('auction_') . '_' . time() . '.' . $extension;
        $upload_dir = __DIR__ . '/uploads/';
        $upload_path = $upload_dir . $filename;
        
        // Create uploads directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // If there's an old image, replace it with the new one using the same filename
            // This way we don't need to update the database
            if ($old_image_path && file_exists(__DIR__ . '/' . $old_image_path)) {
                // Delete old image file
                unlink(__DIR__ . '/' . $old_image_path);
                
                // Move new file to old file's location (same path)
                if (rename($upload_path, __DIR__ . '/' . $old_image_path)) {
                    $upload_success = true;
                    // No database update needed - path stays the same
                } else {
                    // If rename fails, use the new file and update DB path
                    $new_image_path = 'uploads/' . $filename;
                    $update_stmt = $conn->prepare("UPDATE Auctions SET image_path = ? WHERE auction_id = ? AND user_id = ?");
                    $update_stmt->bind_param('sii', $new_image_path, $auction_id, $user_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                    $upload_success = true;
                    $auction['image_path'] = $new_image_path;
                }
            } else {
                // No old image, save new one and update DB with path
                $new_image_path = 'uploads/' . $filename;
                $update_stmt = $conn->prepare("UPDATE Auctions SET image_path = ? WHERE auction_id = ? AND user_id = ?");
                $update_stmt->bind_param('sii', $new_image_path, $auction_id, $user_id);
                $update_stmt->execute();
                $update_stmt->close();
                $upload_success = true;
                $auction['image_path'] = $new_image_path;
            }
        } else {
            $error_message = 'Failed to upload image. Please try again.';
        }
    }
}
?>

<h2 class="my-3">Edit Auction Image</h2>

<?php if ($upload_success): ?>
    <div class="alert alert-success">
        <i class="fa fa-check-circle"></i> Image updated successfully!
        <a href="listing.php?item_id=<?php echo $auction_id; ?>" class="btn btn-sm btn-primary ml-2">View Listing</a>
        <a href="edit_auction_image.php" class="btn btn-sm btn-outline-secondary ml-2">Edit Another</a>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger">
        <i class="fa fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <h4 class="card-title"><?php echo htmlspecialchars($auction['title']); ?></h4>
        <p class="text-muted">Auction ID: <?php echo $auction_id; ?></p>
        
        <div class="row">
            <div class="col-md-6">
                <h5>Current Image</h5>
                <?php if ($auction['image_path']): ?>
                    <img src="<?php echo htmlspecialchars($auction['image_path']); ?>" 
                         alt="<?php echo htmlspecialchars($auction['title']); ?>" 
                         class="img-thumbnail" 
                         style="max-width: 100%; max-height: 400px; object-fit: contain;">
                <?php else: ?>
                    <div class="alert alert-info">No image currently set for this auction.</div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-6">
                <h5>Upload New Image</h5>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="newImage">Choose new image:</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="newImage" name="newImage" accept="image/*" required>
                            <label class="custom-file-label" for="newImage">Choose image...</label>
                        </div>
                        <small class="form-text text-muted">JPG, PNG, GIF, or WebP - Maximum 5MB</small>
                    </div>
                    
                    <div id="imagePreview" class="mt-3" style="display:none;">
                        <h6>Preview:</h6>
                        <img src="" alt="Preview" class="img-thumbnail" style="max-width: 100%; max-height: 300px; object-fit: contain;">
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Update Image</button>
                        <a href="edit_auction_image.php" class="btn btn-outline-secondary">Cancel</a>
                        <a href="listing.php?item_id=<?php echo $auction_id; ?>" class="btn btn-outline-info">View Listing</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

</div>

<?php include_once("footer.php")?>

<script>
(function() {
  const imageInput = document.getElementById('newImage');
  const imageLabel = document.querySelector('.custom-file-label');
  const imagePreview = document.getElementById('imagePreview');
  const previewImg = imagePreview.querySelector('img');

  imageInput.addEventListener('change', function() {
    if (this.files && this.files[0]) {
      const file = this.files[0];
      imageLabel.textContent = file.name;
      
      // Check file size (5MB max)
      if (file.size > 5 * 1024 * 1024) {
        alert('Image file is too large. Maximum size is 5MB.');
        this.value = '';
        imageLabel.textContent = 'Choose image...';
        imagePreview.style.display = 'none';
        return;
      }
      
      // Show preview
      const reader = new FileReader();
      reader.onload = function(e) {
        previewImg.src = e.target.result;
        imagePreview.style.display = 'block';
      };
      reader.readAsDataURL(file);
    } else {
      imageLabel.textContent = 'Choose image...';
      imagePreview.style.display = 'none';
    }
  });
})();
</script>

