document.addEventListener('DOMContentLoaded', function() {
  const dot = document.getElementById('dot');
  const dotMenu = document.getElementById('dotMenu');
  let menuOpen = false;

  dot.addEventListener('click', function(event) {
    event.stopPropagation();
    menuOpen = !menuOpen;
    
    if (menuOpen) {
      // Expand dot and show menu
      dot.style.transform = 'scale(3)';
      dot.style.borderRadius = '50%';
      setTimeout(() => {
        dotMenu.style.display = 'block';
      }, 150); // Show menu after dot starts expanding
    } else {
      // Hide menu and shrink dot
      dotMenu.style.display = 'none';
      dot.style.transform = 'scale(1)';
      dot.style.borderRadius = '0';
    }
  });

  // Close menu if clicking outside
  document.addEventListener('click', function(event) {
    if (menuOpen && !dotMenu.contains(event.target)) {
      menuOpen = false;
      dotMenu.style.display = 'none';
      dot.style.transform = 'scale(1)';
      dot.style.borderRadius = '0';
    }
  });
});
