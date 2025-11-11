<?php
// --- Session and Database Setup ---
include 'connection.php';
session_start();

// Connect to MySQL
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// --- Handle Login ---
if (isset($_POST['login'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $pass = $_POST['password'];
    $result = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($pass, $row['password'])) {
            $_SESSION['email'] = $email;
            $_SESSION['first'] = $row['first'];
            $_SESSION['last'] = $row['last'];
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// --- Handle Logout ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// --- Data Preparation for Slideshows & Gallery ---

// NEW: Function to gather detailed work data from a directory
function get_slideshow_data($base_dir) {
    $works = [];
    if (!is_dir($base_dir)) return $works;

    foreach (glob($base_dir . '/*', GLOB_ONLYDIR) as $user_dir) {
        $profile_path = $user_dir . '/profile.json';
        if (file_exists($profile_path)) {
            $profile_data = json_decode(file_get_contents($profile_path), true);
            if (is_array($profile_data) && !empty($profile_data['work'])) {
                $artist_name = trim(($profile_data['first'] ?? '') . ' ' . ($profile_data['last'] ?? ''));
                foreach ($profile_data['work'] as $work_item) {
                    if (!empty($work_item['path'])) {
                        $works[] = [
                            'path' => str_replace('/var/www/html/', '', $work_item['path']),
                            'desc' => $work_item['desc'] ?? 'Untitled',
                            'artist' => $artist_name,
                            'date' => $work_item['date'] ?? 'N/A'
                        ];
                    }
                }
            }
        }
    }
    shuffle($works);
    return $works;
}

// Gather detailed data for the two slideshows
$pusers_slideshow_data = get_slideshow_data('/var/www/html/pusers');
$pusers2_slideshow_data = get_slideshow_data('/var/www/html/pusers2');

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
      .slideshow-image-wrapper { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; cursor: pointer; }
      .slideshow-img { width: 100%; height: 100%; object-fit: contain; }
      .slideshow-nav { position: absolute; top: 50%; transform: translateY(-50%); background-color: rgba(0,0,0,0.4); color: white; border: none; font-size: 24px; cursor: pointer; padding: 10px; border-radius: 50%; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; transition: background-color 0.3s; z-index: 10; }
      .slideshow-nav:hover { background-color: rgba(0,0,0,0.7); }
      .prev { left: 15px; }
      .next { right: 15px; }
      
      /* Collection Gallery Styles */
      .collection-gallery { display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; padding-top: 1em; }
      .work-card { background: #fff; border-radius: 12px; box-shadow: 0 6px 20px rgba(0,0,0,0.08); overflow: hidden; width: 280px; display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; }
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

<?php if (!isset($_SESSION['email'])): ?>
    <div style="padding: 10px; text-align: right; background: #fdfdfd; border-bottom: 1px solid #eee;">
        <form method="POST" style="display: inline-block;">
            Email: <input type="email" name="email" required>
            Password: <input type="password" name="password" required>
            <button name="login">Login</button>
        </form>
    </div>
<?php else: ?>
    <div style="padding: 10px; text-align: right; background: #fdfdfd; border-bottom: 1px solid #eee;">
        Welcome, <strong><?php echo htmlspecialchars($_SESSION['first']); ?></strong>!
        <a href="?logout=1" style="margin-left: 10px;">Logout</a>
    </div>
<?php endif; ?>


<div class="gallery-container">

    <!-- Slideshow for pusers -->
    <div class="slideshow-wrapper">
        <h2 class="section-title">Featured Works: Gallery 1</h2>
        <div id="slideshow1" class="slideshow-container">
            <div class="slideshow-image-wrapper" onclick="openCurrentSlideModal('slideshow1')">
                <img class="slideshow-img" src="<?php echo !empty($pusers_slideshow_data) ? htmlspecialchars($pusers_slideshow_data[0]['path']) : ''; ?>" alt="Artwork from Gallery 1" />
            </div>
            <button class="slideshow-nav prev" onclick="event.stopPropagation(); changeSlide('slideshow1', -1)">&#10094;</button>
            <button class="slideshow-nav next" onclick="event.stopPropagation(); changeSlide('slideshow1', 1)">&#10095;</button>
        </div>
    </div>

    <!-- Slideshow for pusers2 -->
    <div class="slideshow-wrapper">
        <h2 class="section-title">Featured Works: Gallery 2</h2>
        <div id="slideshow2" class="slideshow-container">
            <div class="slideshow-image-wrapper" onclick="openCurrentSlideModal('slideshow2')">
                <img class="slideshow-img" src="<?php echo !empty($pusers2_slideshow_data) ? htmlspecialchars($pusers2_slideshow_data[0]['path']) : ''; ?>" alt="Artwork from Gallery 2" />
            </div>
            <button class="slideshow-nav prev" onclick="event.stopPropagation(); changeSlide('slideshow2', -1)">&#10094;</button>
            <button class="slideshow-nav next" onclick="event.stopPropagation(); changeSlide('slideshow2', 1)">&#10095;</button>
        </div>
    </div>

    <!-- Collection Gallery from works.json -->
    <div class="collection-wrapper">
        <h2 class="section-title">Community Collection</h2>
        <div class="collection-gallery">
            <?php if (!empty($works_collection)): ?>
                <?php foreach ($works_collection as $work): ?>
                    <?php
                        $work_data_json = htmlspecialchars(json_encode([
                            'path' => $work['path'] ?? '',
                            'desc' => $work['desc'] ?? 'Untitled',
                            'artist' => $work['artist'] ?? 'Unknown',
                            'date' => $work['date'] ?? 'N/A'
                        ]), ENT_QUOTES, 'UTF-8');
                        
                        $work_path = htmlspecialchars($work['path'] ?? '');
                        if (strpos($work_path, '/var/www/html/') === 0) {
                           $work_path = str_replace('/var/www/html/', '', $work_path);
                        }
                    ?>
                    <div class="work-card" onclick="openWorkModal(<?php echo $work_data_json; ?>)">
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

<!-- Modal for Work Details -->
<div id="workModal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.85); align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:14px; padding:36px 28px; max-width:90vw; max-height:90vh; box-shadow:0 8px 32px #0005; display:flex; flex-direction:column; align-items:center; position:relative;">
    <span id="closeWorkModal" style="position:absolute; top:16px; right:24px; color:#333; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
    <img id="workModalImg" src="" alt="" style="max-width:80vw; max-height:60vh; border-radius:8px; margin-bottom:22px;">
    <div id="workModalInfo" style="text-align:center; width:100%;"></div>
  </div>
</div>

<script>
    const slideshowData = {
        slideshow1: { works: <?php echo json_encode($pusers_slideshow_data, JSON_UNESCAPED_SLASHES); ?>, currentIndex: 0 },
        slideshow2: { works: <?php echo json_encode($pusers2_slideshow_data, JSON_UNESCAPED_SLASHES); ?>, currentIndex: 0 }
    };

    function showSlide(slideshowId) {
        const data = slideshowData[slideshowId];
        if (!data || !data.works || data.works.length === 0) return;
        const slideshowElement = document.getElementById(slideshowId);
        const imgElement = slideshowElement.querySelector('.slideshow-img');
        if (data.currentIndex >= data.works.length) data.currentIndex = 0;
        if (data.currentIndex < 0) data.currentIndex = data.works.length - 1;

        imgElement.src = data.works[data.currentIndex].path;
        imgElement.alt = data.works[data.currentIndex].desc;
    }

    function changeSlide(slideshowId, n) {
        slideshowData[slideshowId].currentIndex += n;
        showSlide(slideshowId);
    }
    
    function openWorkModal(workData) {
        const modal = document.getElementById('workModal');
        const img = document.getElementById('workModalImg');
        const info = document.getElementById('workModalInfo');

        let path = workData.path || '';
        if (path.startsWith('/var/www/html/')) {
            path = path.replace('/var/www/html/', '');
        }

        img.src = path;
        img.alt = workData.desc || 'Artwork';
        info.innerHTML = `
            <div style="font-weight:bold; font-size:1.2em;">${workData.desc || 'Untitled'}</div>
            <div style="color:#555; font-size:1em; margin-top:8px;">By: ${workData.artist || 'Unknown'}</div>
            <div style="color:#888; margin-top:6px; font-size:0.95em;">Created: ${workData.date || 'N/A'}</div>
        `;
        modal.style.display = 'flex';
    }

    function openCurrentSlideModal(slideshowId) {
        const data = slideshowData[slideshowId];
        if (data && data.works.length > 0) {
            const currentWork = data.works[data.currentIndex];
            openWorkModal(currentWork);
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        showSlide('slideshow1');
        showSlide('slideshow2');
        
        const modal = document.getElementById('workModal');
        const closeModalBtn = document.getElementById('closeWorkModal');
        
        closeModalBtn.onclick = () => { modal.style.display = 'none'; };
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        };
    });
</script>

</body>
</html>
