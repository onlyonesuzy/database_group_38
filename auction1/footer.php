<!-- If you like, you can put a page footer (something that should show up at
     the bottom of every page, such as helpful links, layout, etc.) here. -->



<!-- Bootstrap core JavaScript -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
<script src="js/bootstrap.bundle.min.js"></script>

<script>
// Handle click on listing card - navigate to listing page if not clicking on a link
function handleListingClick(event, url) {
  // Don't navigate if clicking on a link, button, or input
  if (event.target.tagName === 'A' || 
      event.target.tagName === 'BUTTON' || 
      event.target.tagName === 'INPUT' ||
      event.target.closest('a') ||
      event.target.closest('button') ||
      event.target.closest('input')) {
    return;
  }
  
  // Navigate to listing page
  if (url) {
    window.location.href = url;
  }
}
</script>

</body>

</html>