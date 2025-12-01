<?php include_once("header.php")?>
<?php require_once("db_connect.php"); ?>

<?php
  if (!isset($_SESSION['account_type']) || !in_array($_SESSION['account_type'], ['seller', 'both'])) {
    header('Location: browse.php');
    exit();
  }

  $category_options = [];
  $cat_query = $conn->query("SELECT category_id, category_name FROM Categories ORDER BY category_name ASC");
  if ($cat_query) {
    while ($row = $cat_query->fetch_assoc()) {
      $category_options[] = $row;
    }
  }
?>

<div class="container">

<!-- Create auction form -->
<div style="max-width: 800px; margin: 10px auto">
  <h2 class="my-3">Create new auction</h2>
  <div class="card">
    <div class="card-body">
      <!-- Note: This form does not do any dynamic / client-side / 
      JavaScript-based validation of data. It only performs checking after 
      the form has been submitted, and only allows users to try once. You 
      can make this fancier using JavaScript to alert users of invalid data
      before they try to send it, but that kind of functionality should be
      extremely low-priority / only done after all database functions are
      complete. -->
      <form method="post" action="create_auction_result.php" enctype="multipart/form-data">
        <div class="form-group row">
          <label for="auctionTitle" class="col-sm-2 col-form-label text-right">Title of auction</label>
          <div class="col-sm-10">
            <input type="text" class="form-control" id="auctionTitle" name="auctionTitle" placeholder="e.g. Black mountain bike">
            <small id="titleHelp" class="form-text text-muted"><span class="text-danger">* Required.</span> A short description of the item you're selling, which will display in listings.</small>
          </div>
        </div>
        <div class="form-group row">
          <label for="auctionDetails" class="col-sm-2 col-form-label text-right">Details</label>
          <div class="col-sm-10">
            <textarea class="form-control" id="auctionDetails" name="auctionDetails" rows="4"></textarea>
            <small id="detailsHelp" class="form-text text-muted">Full details of the listing to help bidders decide if it's what they're looking for.</small>
          </div>
        </div>
        <div class="form-group row">
          <label for="auctionImage" class="col-sm-2 col-form-label text-right">Item Image</label>
          <div class="col-sm-10">
            <div class="custom-file">
              <input type="file" class="custom-file-input" id="auctionImage" name="auctionImage" accept="image/*">
              <label class="custom-file-label" for="auctionImage">Choose image...</label>
            </div>
            <small id="imageHelp" class="form-text text-muted">Optional. Upload a photo of your item (JPG, PNG, GIF - max 5MB).</small>
            <div id="imagePreview" class="mt-2" style="display:none;">
              <img src="" alt="Preview" style="max-width: 200px; max-height: 200px;" class="img-thumbnail">
            </div>
          </div>
        </div>
        <div class="form-group row">
          <label for="auctionCategory" class="col-sm-2 col-form-label text-right">Categories</label>
          <div class="col-sm-10">
            <div class="row">
              <div class="col-md-6">
                <div class="border rounded p-3 category-picker">
                  <?php if (empty($category_options)): ?>
                    <div class="text-muted small">No categories found. Please add some in the database.</div>
                  <?php else: ?>
                    <?php foreach ($category_options as $cat): ?>
                      <div class="form-check">
                        <input class="form-check-input category-option" type="checkbox" value="<?php echo $cat['category_id']; ?>" id="cat_<?php echo $cat['category_id']; ?>" data-name="<?php echo htmlspecialchars($cat['category_name']); ?>">
                        <label class="form-check-label" for="cat_<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></label>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
              <div class="col-md-6">
                <p class="small text-muted mb-1">Selected categories & order</p>
                <ul class="list-group" id="selectedCategoryList"></ul>
                <small class="form-text text-muted">Use the up/down buttons to arrange how this listing should appear within its categories.</small>
              </div>
            </div>
            <input type="hidden" name="categoryOrder" id="categoryOrder">
            <small id="categoryHelp" class="form-text text-muted"><span class="text-danger">* Required.</span> Pick one or more categories and arrange them in priority order.</small>
          </div>
        </div>
        <div class="form-group row">
          <label for="auctionStartPrice" class="col-sm-2 col-form-label text-right">Starting price</label>
          <div class="col-sm-10">
	        <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text">£</span>
              </div>
              <input type="number" class="form-control" id="auctionStartPrice" name="auctionStartPrice">
            </div>
            <small id="startBidHelp" class="form-text text-muted"><span class="text-danger">* Required.</span> Initial bid amount.</small>
          </div>
        </div>
        <div class="form-group row">
          <label for="auctionReservePrice" class="col-sm-2 col-form-label text-right">Reserve price</label>
          <div class="col-sm-10">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text">£</span>
              </div>
              <input type="number" class="form-control" id="auctionReservePrice" name="auctionReservePrice">
            </div>
            <small id="reservePriceHelp" class="form-text text-muted">Optional. Auctions that end below this price will not go through. This value is not displayed in the auction listing.</small>
          </div>
        </div>
        <div class="form-group row">
          <label for="auctionBuyNowPrice" class="col-sm-2 col-form-label text-right">Buy Now price</label>
          <div class="col-sm-10">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text">£</span>
              </div>
              <input type="number" step="0.01" class="form-control" id="auctionBuyNowPrice" name="auctionBuyNowPrice">
            </div>
            <small id="buyNowPriceHelp" class="form-text text-muted">Optional. Set a price at which buyers can purchase the item instantly, ending the auction immediately.</small>
          </div>
        </div>
        <div class="form-group row">
          <label for="auctionEndDate" class="col-sm-2 col-form-label text-right">End date</label>
          <div class="col-sm-10">
            <input type="datetime-local" class="form-control" id="auctionEndDate" name="auctionEndDate">
            <small id="endDateHelp" class="form-text text-muted"><span class="text-danger">* Required.</span> Day for the auction to end.</small>
          </div>
        </div>
        <div class="card mb-3">
          <div class="card-body">
            <h5 class="card-title mb-3">3% intermediary fee preview</h5>
            <div class="d-flex justify-content-between">
              <span>Listing price</span>
              <strong id="feeListingPrice">£0.00</strong>
            </div>
            <div class="d-flex justify-content-between">
              <span>Fee (3%)</span>
              <strong id="feeAmount">£0.00</strong>
            </div>
            <div class="d-flex justify-content-between">
              <span>Seller receives</span>
              <strong id="feePayout">£0.00</strong>
            </div>
            <small class="text-muted d-block mt-2">We calculate and show these numbers to buyers as well, so there are no surprises.</small>
          </div>
        </div>
        <button type="submit" class="btn btn-primary form-control">Create Auction</button>
      </form>
    </div>
  </div>
</div>

</div>


<?php include_once("footer.php")?>

<script>
(function() {
  const feeRate = 0.03;
  const startInput = document.getElementById('auctionStartPrice');
  const listingEl = document.getElementById('feeListingPrice');
  const feeEl = document.getElementById('feeAmount');
  const payoutEl = document.getElementById('feePayout');

  function formatCurrency(value) {
    return '£' + (value || 0).toFixed(2);
  }

  function updateFeeCard() {
    const listing = parseFloat(startInput.value) || 0;
    const fee = listing * feeRate;
    const payout = Math.max(listing - fee, 0);
    listingEl.textContent = formatCurrency(listing);
    feeEl.textContent = formatCurrency(fee);
    payoutEl.textContent = formatCurrency(payout);
  }

  startInput.addEventListener('input', updateFeeCard);
  updateFeeCard();

  const categoryOrderInput = document.getElementById('categoryOrder');
  const selectedList = document.getElementById('selectedCategoryList');
  const selected = [];

  function renderSelected() {
    selectedList.innerHTML = '';
    selected.forEach((cat, index) => {
      const li = document.createElement('li');
      li.className = 'list-group-item d-flex justify-content-between align-items-center';
      li.innerHTML = '<span>' + cat.name + '</span>';
      const btnGroup = document.createElement('div');
      btnGroup.className = 'btn-group btn-group-sm';

      const upBtn = document.createElement('button');
      upBtn.type = 'button';
      upBtn.className = 'btn btn-outline-secondary';
      upBtn.textContent = '↑';
      upBtn.disabled = index === 0;
      upBtn.addEventListener('click', () => {
        if (index === 0) return;
        const temp = selected[index - 1];
        selected[index - 1] = selected[index];
        selected[index] = temp;
        renderSelected();
      });

      const downBtn = document.createElement('button');
      downBtn.type = 'button';
      downBtn.className = 'btn btn-outline-secondary';
      downBtn.textContent = '↓';
      downBtn.disabled = index === selected.length - 1;
      downBtn.addEventListener('click', () => {
        if (index === selected.length - 1) return;
        const temp = selected[index + 1];
        selected[index + 1] = selected[index];
        selected[index] = temp;
        renderSelected();
      });

      btnGroup.appendChild(upBtn);
      btnGroup.appendChild(downBtn);
      li.appendChild(btnGroup);
      selectedList.appendChild(li);
    });
    categoryOrderInput.value = selected.map(cat => cat.id).join(',');
  }

  document.querySelectorAll('.category-option').forEach(option => {
    option.addEventListener('change', () => {
      const id = option.value;
      const existingIndex = selected.findIndex(cat => cat.id === id);
      if (option.checked && existingIndex === -1) {
        selected.push({ id, name: option.dataset.name });
      } else if (!option.checked && existingIndex !== -1) {
        selected.splice(existingIndex, 1);
      }
      renderSelected();
    });
  });

  const form = document.querySelector('form');
  form.addEventListener('submit', (event) => {
    if (!categoryOrderInput.value) {
      event.preventDefault();
      alert('Please select at least one category for your listing.');
    }
  });

  // Image upload preview
  const imageInput = document.getElementById('auctionImage');
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