<?php
// --- Data Preparation for Slideshows ---

// Function to gather images from a specified directory
function get_slideshow_images($base_dir) {
    $images = [];
    if (is_dir($base_dir)) {
        $user_folders = scandir($base_dir);
        foreach ($user_folders as $user_folder) {
            if ($user_folder === '.' || $user_folder === '..') continue;
            $work_dir = $base_dir . '/' . $user_folder . '/work';
            if (is_dir($work_dir)) {
                foreach (scandir($work_dir) as $file) {
                    if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $file)) {
                        // Create a web-accessible path
                        $web_path = str_replace('/var/www/html/', '', $work_dir) . '/' . $file;
                        $images[] = $web_path;
                    }
                }
            }
        }
    }
    shuffle($images);
    return $images;
}

// Gather images from 'pusers'
$pusers_images = get_slideshow_images('/var/www/html/pusers');

// Gather images from 'pusers2'
$pusers2_images = get_slideshow_images('/var/www/html/pusers2');
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Work Gallery</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
      * { box-sizing: border-box; }
      body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background-color: #f0f2f5; }
      .gallery-container { width: 90%; max-width: 1200px; margin: 2em auto; }
      .slideshow-wrapper { margin-bottom: 3em; }
      .slideshow-title { font-size: 1.8em; font-weight: bold; margin-bottom: 0.5em; color: #333; border-bottom: 2px solid #e27979; padding-bottom: 10px; }
      .slideshow-container {
          position: relative;
          width: 100%;
          height: 500px;
          margin: 1em auto;
          background-color: #fff;
          border-radius: 16px;
          box-shadow: 0 8px 24px rgba(0,0,0,0.1);
          overflow: hidden;
      }
      .slideshow-image-wrapper {
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: center;
      }
      .slideshow-img {
          width: 100%;
          height: 100%;
          object-fit: contain; /* Use 'contain' to see the whole image */
      }
      .slideshow-nav {
          position: absolute;
          top: 50%;
          transform: translateY(-50%);
          background-color: rgba(0,0,0,0.4);
          color: white;
          border: none;
          font-size: 24px;
          cursor: pointer;
          padding: 10px;
          border-radius: 50%;
          width: 44px;
          height: 44px;
          display: flex;
          align-items: center;
          justify-content: center;
          transition: background-color 0.3s;
      }
      .slideshow-nav:hover { background-color: rgba(0,0,0,0.7); }
      .prev { left: 15px; }
      .next { right: 15px; }
      .navbar { /* Basic navbar styling from testy8.php */
          display: flex;
          justify-content: center;
          padding: 10px;
          background: #333;
      }
      .navbar a { color: white; text-decoration: none; padding: 0 15px; }
    </style>
</head>
<body>

<div class="navbar">
    <a href="home.php">Home</a>
    <a href="studio3.php">Studio</a>
    <a href="database.php">Database</a>
    <a href="gallery.php">Gallery</a>
</div>

<div class="gallery-container">

    <!-- Slideshow for pusers -->
    <div class="slideshow-wrapper">
        <h2 class="slideshow-title">Works from "pusers"</h2>
        <div id="slideshow1" class="slideshow-container">
            <div class="slideshow-image-wrapper">
                <img class="slideshow-img" src="<?php echo !empty($pusers_images) ? htmlspecialchars($pusers_images[0]) : ''; ?>" alt="Artwork from pusers" />
            </div>
            <button class="slideshow-nav prev" onclick="changeSlide('slideshow1', -1)">&#10094;</button>
            <button class="slideshow-nav next" onclick="changeSlide('slideshow1', 1)">&#10095;</button>
        </div>
    </div>

    <!-- Slideshow for pusers2 -->
    <div class="slideshow-wrapper">
        <h2 class="slideshow-title">Works from "pusers2"</h2>
        <div id="slideshow2" class="slideshow-container">
            <div class="slideshow-image-wrapper">
                <img class="slideshow-img" src="<?php echo !empty($pusers2_images) ? htmlspecialchars($pusers2_images[0]) : ''; ?>" alt="Artwork from pusers2" />
            </div>
            <button class="slideshow-nav prev" onclick="changeSlide('slideshow2', -1)">&#10094;</button>
            <button class="slideshow-nav next" onclick="changeSlide('slideshow2', 1)">&#10095;</button>
        </div>
    </div>

</div>

<script>
    // Store image data and current index for each slideshow
    const slideshowData = {
        slideshow1: {
            images: <?php echo json_encode($pusers_images, JSON_UNESCAPED_SLASHES); ?>,
            currentIndex: 0
        },
        slideshow2: {
            images: <?php echo json_encode($pusers2_images, JSON_UNESCAPED_SLASHES); ?>,
            currentIndex: 0
        }
    };

    function showSlide(slideshowId) {
        const data = slideshowData[slideshowId];
        if (!data || data.images.length === 0) return;

        const slideshowElement = document.getElementById(slideshowId);
        const imgElement = slideshowElement.querySelector('.slideshow-img');

        // Handle index wrapping
        if (data.currentIndex >= data.images.length) {
            data.currentIndex = 0;
        }
        if (data.currentIndex < 0) {
            data.currentIndex = data.images.length - 1;
        }

        imgElement.src = data.images[data.currentIndex];
    }

    function changeSlide(slideshowId, n) {
        slideshowData[slideshowId].currentIndex += n;
        showSlide(slideshowId);
    }

    // Initialize both slideshows on page load
    document.addEventListener("DOMContentLoaded", function() {
        showSlide('slideshow1');
        showSlide('slideshow2');
    });
</script>

</body>
</html>
