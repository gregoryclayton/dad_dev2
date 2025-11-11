<?php
// --- Data Preparation for Slideshows & Gallery ---

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
                        // Create a web-accessible path by removing the server root
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

// Gather images for the two slideshows
$pusers_images = get_slideshow_images('/var/www/html/pusers');
$pusers2_images = get_slideshow_images('/var/www/html/pusers2');

// --- Data Preparation for Collection Gallery from works.json ---
$works_collection = [];
$works_json_path = '/var/www/html/pusers/works.json';
if (file_exists($works_json_path)) {
    $json_content = file_get_contents($works_json_path);
    $decoded_json = json_decode($json_content, true);
    if (is_array($decoded_json)) {
        $works_collection = $decoded_json;
    }
}
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
      .slideshow-wrapper, .collection-wrapper { margin-bottom: 3.5em; }
      .section-title { font-size: 1.8em; font-weight: bold; margin-bottom: 0.5em; color: #333; border-bottom: 2px solid #e27979; padding-bottom: 10px; }
      
      /* Slideshow Styles */
      .slideshow-container { position: relative; width: 100%; height: 500px; margin: 1em auto; background-color: #fff; border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); overflow: hidden; }
      .slideshow-image-wrapper { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
      .slideshow-img { width: 100%; height: 100%; object-fit: contain; }
      .slideshow-nav { position: absolute; top: 50%; transform: translateY(-50%); background-color: rgba(0,0,0,0.4); color: white; border: none; font-size: 24px; cursor: pointer; padding: 10px; border-radius: 50%; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; transition: background-color 0.3s; z-index: 10; }
      .slideshow-nav:hover { background-color: rgba(0,0,0,0.7); }
      .prev { left: 15px; }
      .next { right: 15px; }
      
      /* Collection Gallery Styles */
      .collection-gallery { display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; padding-top: 1em; }
      .work-card { background: #fff; border-radius: 12px; box-shadow: 0 6px 20px rgba(0,0,0,0.08); overflow: hidden; width: 280px; display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s; }
      .work-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.12); }
      .work-card-image { width: 100%; height: 200px; object-fit: cover; background-color: #eee; }
      .work-card-info { padding: 15px; }
      .work-card-title { font-weight: 600; font-size: 1.1em; margin: 0 0 8px 0; }
      .work-card-artist { font-size: 0.95em; color: #555; margin: 0 0 12px 0; }
      .work-card-date { font-size: 0.85em; color: #999; }

      .navbar { display: flex; justify-content: center; padding: 10px; background: #333; }
      .navbar a { color: white; text-decoration: none; padding: 0 15px; font-family: monospace; }
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
        <h2 class="section-title">Featured Works: Gallery 1</h2>
        <div id="slideshow1" class="slideshow-container">
            <div class="slideshow-image-wrapper">
                <img class="slideshow-img" src="<?php echo !empty($pusers_images) ? htmlspecialchars($pusers_images[0]) : ''; ?>" alt="Artwork from Gallery 1" />
            </div>
            <button class="slideshow-nav prev" onclick="changeSlide('slideshow1', -1)">&#10094;</button>
            <button class="slideshow-nav next" onclick="changeSlide('slideshow1', 1)">&#10095;</button>
        </div>
    </div>

    <!-- Slideshow for pusers2 -->
    <div class="slideshow-wrapper">
        <h2 class="section-title">Featured Works: Gallery 2</h2>
        <div id="slideshow2" class="slideshow-container">
            <div class="slideshow-image-wrapper">
                <img class="slideshow-img" src="<?php echo !empty($pusers2_images) ? htmlspecialchars($pusers2_images[0]) : ''; ?>" alt="Artwork from Gallery 2" />
            </div>
            <button class="slideshow-nav prev" onclick="changeSlide('slideshow2', -1)">&#10094;</button>
            <button class="slideshow-nav next" onclick="changeSlide('slideshow2', 1)">&#10095;</button>
        </div>
    </div>

    <!-- Collection Gallery from works.json -->
    <div class="collection-wrapper">
        <h2 class="section-title">Community Collection</h2>
        <div class="collection-gallery">
            <?php if (!empty($works_collection)): ?>
                <?php foreach ($works_collection as $work): ?>
                    <div class="work-card">
                        <?php
                            $work_path = htmlspecialchars($work['path'] ?? '');
                            // Correct the path for display if needed
                            if (strpos($work_path, '/var/www/html/') === 0) {
                               $work_path = str_replace('/var/www/html/', '', $work_path);
                            }
                        ?>
                        <img class="work-card-image" src="<?php echo $work_path; ?>" alt="<?php echo htmlspecialchars($work['desc'] ?? 'Artwork'); ?>">
                        <div class="work-card-info">
                            <h3 class="work-card-title"><?php echo htmlspecialchars($work['desc'] ?? 'Untitled'); ?></h3>
                            <p class="work-card-artist">By: <?php echo htmlspecialchars($work['artist'] ?? 'Unknown'); ?></p>
                            <p class="work-card-date">Created: <?php echo htmlspecialchars($work['date'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No works found in the collection.</p>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
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
        if (!data || !data.images || data.images.length === 0) return;

        const slideshowElement = document.getElementById(slideshowId);
        const imgElement = slideshowElement.querySelector('.slideshow-img');
        
        if (data.currentIndex >= data.images.length) data.currentIndex = 0;
        if (data.currentIndex < 0) data.currentIndex = data.images.length - 1;

        imgElement.src = data.images[data.currentIndex];
    }

    function changeSlide(slideshowId, n) {
        slideshowData[slideshowId].currentIndex += n;
        showSlide(slideshowId);
    }

    document.addEventListener("DOMContentLoaded", function() {
        showSlide('slideshow1');
        showSlide('slideshow2');
    });
</script>

</body>
</html>
