<?php
// Database credentials
include 'connection.php';

session_start();

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// --- API endpoint for selecting a work ---
if (isset($_POST['action']) && $_POST['action'] === 'select_work' && isset($_SESSION['first']) && isset($_SESSION['last'])) {
    $workData = isset($_POST['work_data']) ? json_decode($_POST['work_data'], true) : null;
    if (!$workData || !isset($workData['path'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid work data.']);
        exit;
    }

    // Get logged-in user's profile
    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
    $userProfilePath = __DIR__ . '/pusers/' . $safe_first . '_' . $safe_last . '/profile.json';

    if (file_exists($userProfilePath)) {
        $profile = json_decode(file_get_contents($userProfilePath), true);
        
        if (!isset($profile['selected_works']) || !is_array($profile['selected_works'])) {
            $profile['selected_works'] = [];
        }

        // Check if work is already selected by its path
        $isAlreadySelected = false;
        foreach ($profile['selected_works'] as $selected) {
            if (isset($selected['path']) && $selected['path'] === $workData['path']) {
                $isAlreadySelected = true;
                break;
            }
        }

        if (!$isAlreadySelected) {
            $new_selection = [
                'path' => $workData['path'],
                'title' => $workData['title'] ?? '',
                'date' => $workData['date'] ?? '',
                'artist' => $workData['artist'] ?? '',
                'user_folder' => $workData['user_folder'] ?? '',
                'timestamp' => date('c') // ISO 8601 date
            ];
            $profile['selected_works'][] = $new_selection;
            file_put_contents($userProfilePath, json_encode($profile, JSON_PRETTY_PRINT));
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Work selection updated.']);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'User profile not found.']);
        exit;
    }
}


// ---- User Profile Functions ----
function safe_name($val) { return preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $val); }
function create_user_profile($first, $last, $email) {
    $user_dir = "/var/www/html/pusers/" . safe_name($first) . "_" . safe_name($last);
    if (!is_dir($user_dir)) { mkdir($user_dir, 0755, true); }
    $profile = [
        "first" => $first,
        "last" => $last,
        "email" => $email,
        "created_at" => date("Y-m-d H:i:s"),
        "work" => [],
        "selected_works" => []
    ];
    file_put_contents($user_dir . "/profile.json", json_encode($profile, JSON_PRETTY_PRINT));
    return $user_dir;
}
function update_user_profile_extra($first, $last, $bio, $dob, $country) {
    $user_dir = "/var/www/html/pusers/" . safe_name($first) . "_" . safe_name($last);
    $profile_path = $user_dir . "/profile.json";
    if (file_exists($profile_path)) {
        $profile = json_decode(file_get_contents($profile_path), true);
        $profile['bio'] = $bio;
        $profile['dob'] = $dob;
        $profile['country'] = $country;
        file_put_contents($profile_path, json_encode($profile, JSON_PRETTY_PRINT));
    }
}
function add_user_work($first, $last, $desc, $date, $image_path) {
    $user_dir = "/var/www/html/pusers/" . safe_name($first) . "_" . safe_name($last);
    $profile_path = $user_dir . "/profile.json";
    if (file_exists($profile_path)) {
        $profile = json_decode(file_get_contents($profile_path), true);
        if (!isset($profile['work']) || !is_array($profile['work'])) { $profile['work'] = []; }
        $profile['work'][] = ["desc" => $desc, "date" => $date, "image" => $image_path];
        file_put_contents($profile_path, json_encode($profile, JSON_PRETTY_PRINT));
    }
}

// --- Gather Work Images for Slideshow ---
$slideshow_images = [];
$pusers_dir = __DIR__ . '/pusers';
if (is_dir($pusers_dir)) {
    $user_folders = scandir($pusers_dir);
    foreach ($user_folders as $user_folder) {
        if ($user_folder === '.' || $user_folder === '..') continue;
        $work_dir = $pusers_dir . '/' . $user_folder . '/work';
        if (is_dir($work_dir)) {
            foreach (scandir($work_dir) as $file) {
                if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $file)) {
                    $slideshow_images[] = 'pusers/' . $user_folder . '/work/' . $file;
                }
            }
        }
    }
}
shuffle($slideshow_images);

// --- Login/Logout/Register ---
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
            header("Location: home.php");
        } else {
            echo "Incorrect password!";
        }
    } else {
        echo "No account found!";
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: home.php");
    exit();
}
if (isset($_POST['register'])) {
    $first = $conn->real_escape_string($_POST['first']);
    $last = $conn->real_escape_string($_POST['last']);
    $email = $conn->real_escape_string($_POST['email']);
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $check = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($check->num_rows > 0) {
        echo "Email already registered!";
    } else {
        $conn->query("INSERT INTO users (first,last,email,password) VALUES ('$first','$last','$email','$pass')");
        create_user_profile($first, $last, $email);
        echo "Registration successful! Please log in.";
    }
}

// --- Gather User Profiles (for Listing/Search) ---
$baseDir = "/var/www/html/pusers";
$userProfiles = [];
$loggedInUser_profile = null;
if (is_dir($baseDir)) {
    foreach (glob($baseDir . '/*', GLOB_ONLYDIR) as $dir) {
        $profilePath = $dir . "/profile.json";
        if (file_exists($profilePath)) {
            $profileData = json_decode(file_get_contents($profilePath), true);
            if ($profileData) {
              $userProfiles[] = $profileData;
              // If this profile belongs to the logged-in user, store it separately
              if (isset($_SESSION['first']) && isset($_SESSION['last']) &&
                  isset($profileData['first']) && isset($profileData['last']) &&
                  $_SESSION['first'] === $profileData['first'] && $_SESSION['last'] === $profileData['last']) {
                  $loggedInUser_profile = $profileData;
              }
            }
        }
    }
}
?>
<?php
// This PHP script finds the top 10 most frequently selected works
// across all user profiles and displays them in a horizontally scrolling flexbox gallery
// with modal pop-outs when cards are clicked and detailed info in the modal only.

$baseDir = __DIR__ . '/pusers';
$workCounts = [];
$workDetails = [];

// Get all subdirectories in "pusers"
$subfolders = glob($baseDir . '/*', GLOB_ONLYDIR);

foreach ($subfolders as $subfolder) {
    $profilePath = $subfolder . '/profile.json';
    if (file_exists($profilePath)) {
        $jsonData = file_get_contents($profilePath);
        $profile = json_decode($jsonData, true);

        if (isset($profile['selected_works']) && is_array($profile['selected_works'])) {
            foreach ($profile['selected_works'] as $work) {
                $workKey = $work['path'];
                if (!isset($workCounts[$workKey])) {
                    $workCounts[$workKey] = 0;
                    $workDetails[$workKey] = $work;
                    $workDetails[$workKey]['user_folder'] = basename($subfolder);
                }
                $workCounts[$workKey]++;
            }
        }
    }
}

// Sort works by count descending
arsort($workCounts);

// Get top 10 works
$topWorks = array_slice(array_keys($workCounts), 0, 10);

// Prepare works data for JS modal
$topWorksData = [];
foreach ($topWorks as $workPath) {
    $work = $workDetails[$workPath];
    $work['selected_count'] = $workCounts[$workPath];
    $topWorksData[] = $work;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>digital artist database</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="style.css">
    <script>
    var userProfiles = <?php echo json_encode($userProfiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>;
    var slideshowImages = <?php echo json_encode($slideshow_images, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>;
    var loggedInUser_profile = <?php echo json_encode($loggedInUser_profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <style>
      * { box-sizing: border-box; }
      .user-row {
        display:flex;
        flex-direction: column;
        align-items:flex-start;
        padding: 10px 0;
        border-bottom:1px solid #eee;
        cursor:pointer;
      }
      .user-row:hover { background:#f9f9f9; }
      .user-row-main { 
        display:flex; 
        width:100%; 
        align-items:center; 
        padding: 0 10px;
      }
      .mini-profile {
        width:40px;
        height:40px;
        object-fit:cover;
        border-radius:8px;
        margin-right:10px;
        box-shadow:0 2px 8px rgba(0,0,0,0.12);
        flex-shrink: 0;
      }
      .user-name { font-size: 14px; font-family: monospace; }
      .user-submeta { color:#666; font-size:0.9em; margin-top:4px; }
      
      /* Dropdown Styles */
      .profile-dropdown { 
        display:none;
        width: 100%;
        padding: 15px 10px 0 10px;
      }
      .dropdown-inner {
        display: flex;
        flex-direction: column; /* Mobile-first: stack columns */
        gap: 15px;
      }
      .dropdown-header { display:flex; gap:15px; align-items:center; }
      .dropdown-main-image {
        width: 80px; height: 80px; border-radius: 10px; object-fit: cover; background: #f4f4f4; flex-shrink: 0;
      }
      .dropdown-name { font-size:1.4em; font-weight:700; margin:0; }
      .dropdown-meta { margin-top:8px; color:#555; line-height:1.5; font-size:0.9em; }
      
      .dropdown-gallery-title { margin-top:15px; font-weight:600; font-size:1em; }
      .dropdown-work-gallery { display: flex; overflow-x: auto; gap: 10px; padding: 5px 0 10px 0; }
      
      .dropdown-work-item { display:flex; flex-direction:column; flex-shrink:0; width:120px; }
      .work-image {
        width:120px;
        height:120px;
        object-fit:cover;
        border-radius:8px;
        cursor:pointer;
        box-shadow:0 2px 8px rgba(0,0,0,0.08);
      }
      .work-info { font-size:0.85em; padding-top:6px; }
      .work-info .desc { font-weight:600; color:#333; }
      .work-info .date { color:#777; }
      .dropdown-body { overflow: hidden; }

      /* Slideshow Styles */
      #slideshow-container {
        position: relative;
        width: 80vw;
        height: 450px;
        max-width: 900px;
        margin: 2em auto;
        background-color: #f4f4f4;
        border-radius: 16px;
        box-shadow: 0 6px 24px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
      }
      #slideshow-img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        cursor: pointer;
        position: relative;
        z-index: 1; 
      }
      .slideshow-nav {
        position: absolute;
        top: 0;
        width: 20%;
        height: 100%;
        z-index: 5; 
        cursor: pointer;
        -webkit-tap-highlight-color: transparent; /* Remove tap highlight on mobile */
      }
      #slideshow-prev-zone { left: 0; }
      #slideshow-next-zone { right: 0; }

      /* Responsive changes for wider screens */
      @media (min-width: 600px) {
        .dropdown-inner { flex-direction: row; }
        .dropdown-header { flex-direction: column; align-items:flex-start; gap:0; }
        .dropdown-main-image { width: 120px; height: 120px; }
      }

    </style>
</head>
<body>

<div style="display:flex;">
  <div class="title-container" id="mainTitleContainer" style="background-image: linear-gradient(135deg, #e27979 60%, #ed8fd1 100%); transition: background-image 0.7s; ">
    <br>
    <a href="index.php" style="text-decoration:none; color: white;">digital <br>artist <br>database</a>
  </div>
  
   <div id="dotMenuContainer" style="position:relative; align-self:end; margin-bottom:50px; margin-left:-30px;">
    <div id="dot" style="color:black; background: linear-gradient(135deg, #e27979 60%, #ed8fd1 100%); transition: background 0.7s;"></div>
    <div id="dotMenu" style="display:none; position:absolute; left:80px; top:-380%; transform:translateX(-50%); background-image: linear-gradient(to bottom right, rgba(226, 121, 121, 0.936), rgba(237, 143, 209, 0.933)); border-radius:16px; box-shadow:0 4px 24px #0003; padding:1.2em 1.7em; z-index:10001; min-width:80px; display:none;">
      <!-- Your menu content here -->
     <!-- Add this play icon to the dot menu container -->
<div id="musicPlayIcon" style="display:none; position:absolute; top:7px; right:41px; background: white; border-radius:50%; padding:2px; font-size:10px; width:16px; height:16px; text-align:center; box-shadow: 0 1px 4px #0003; cursor:pointer;">
  <span style="color:#e27979;">▶</span>
</div>
      <!-- New buttons for changing color -->
      <div style="position: relative;">
  <button id="musicBtn" style="margin-top:1em; background:white; color:#fff; border:none; border-radius:8px; font-family:monospace; font-size:1em; cursor:pointer; display:block; width:10px;" title="Toggle Music"></button>
  <div id="musicPlayIcon" style="display:none; position:absolute; top:-12px; right:-5px; background: white; border-radius:50%; padding:2px; font-size:10px; width:16px; height:16px; text-align:center; box-shadow: 0 1px 4px #0003; cursor:pointer;">
    <span style="color:#e27979;">▶</span>
  </div>
</div>
      <button id="changeTitleBgBtn" style="margin-top:1em; background:grey; color:#fff; border:none; border-radius:8px; font-family:monospace; font-size:1em; cursor:pointer; display:block; width:10px; height:10px;" title="Change background"></button>
      <button id="bwThemeBtn" style="margin-top:0.7em; background:lightgrey; color:#fff; border:none; border-radius:8px; padding:0.6em 1.1em; font-family:monospace; font-size:1em; cursor:pointer; display:block; width:10px; height:10px;" title="B&W Theme"></button>
    </div>
  </div>
  
</div>


<!-- Pop-out menu for quick nav, hidden by default -->
<div id="titleMenuPopout" style="display:none; position:fixed; z-index:10000; top:65px; left:40px; background: white; border-radius:14px; box-shadow:0 4px 24px #0002; padding:1.4em 2em; min-width:50px;">
  <div style="display:flex; flex-direction:column; gap:0.5em;">
    <a href="v4.5.php" style="color:#777; text-decoration:none; font-size:1.1em;">home</a>
    <a href="v4.5.php" style="color:#777; text-decoration:none; font-size:1.1em;">about</a>
       <a href="studio.php" style="color:#777; text-decoration:none; font-size:1.1em;">studio</a>
    <a href="signup.php" style="color:#b44; text-decoration:none; font-size:1.1em;">register</a>
    <a href="database.php" style="color:#555; text-decoration:none; font-size:1.1em;">database</a>
   
   
  </div>
</div>

<div class="navbar">
    <div class="navbarbtns">
        <div><a class="navbtn" href="home.php">[home]</a></div>
        <div><a class="navbtn" href="studio3.php">[studio]</a></div>
        <div><a class="navbtn" href="register.php">[register]</a></div>
        <div><a class="navbtn" href="database.php">[database]</a></div>
    </div>
</div>

<?php if (!isset($_SESSION['email'])): ?>


<form method="POST" style="display:flex; max-width:80vw; justify-content: flex-end; padding:10px; border-bottom: 1px solid #e2e2e2; background: #ffffff00; border-bottom-right-radius:10px; border-top-right-radius:10px; margin: 0 0 0 auto;">
    <input style="width:80px; margin-right:20px;" type="email" name="email" placeholder="email" required><br>
    <input style="width:80px; margin-right:20px;" type="password" name="password"  placeholder="password" required><br>
    <button name="login">Login</button>
</form>
<?php else: ?>
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['first'] . " " . $_SESSION['last']); ?>!</h2>
    <a href="?logout=1">Logout</a>
    
    
<?php endif; ?>

<!-- Slideshow -->
<div id="slideshow-container">
    <img id="slideshow-img" src="<?php echo count($slideshow_images) ? htmlspecialchars($slideshow_images[0]) : ''; ?>" alt="Artwork Slideshow" />
    <?php if (count($slideshow_images) > 1): ?>
        <div id="slideshow-prev-zone" class="slideshow-nav"></div>
        <div id="slideshow-next-zone" class="slideshow-nav"></div>
    <?php endif; ?>
</div>


<!-- Slideshow Modal (Simple/Fixed) -->
<div id="slideModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:10001; background:rgba(0,0,0,0.72); align-items:center; justify-content:center;">
  <div style="background:white; border-radius:16px; padding:24px 32px; max-width:90vw; max-height:90vh; box-shadow:0 6px 32px #000a; position:relative; display:flex; flex-direction:column; align-items:center;">
    <button id="closeSlideModal" style="position:absolute; top:10px; right:15px; font-size:1.3em; background:none; border:none; color:#333; cursor:pointer;">×</button>
    <img id="modalImage" src="" alt="Artwork" style="max-width:65vw; max-height:55vh; border-radius:10px; background:#f6f6f6; margin-bottom:18px;">
    <div>
        <div id="modalArtist" style="font-size:1.13em; font-weight:bold;"></div>
        <div id="modalTitle" style="margin:8px 0 0 0; color:#666;"></div>
        <div id="modalDate" style="margin:5px 0 0 0; color:#888; font-size:0.98em;"></div>
        <button id="visitProfileBtn" style="margin-top:14px; background:#e8bebe; border:none; border-radius:7px; padding:0.6em 1.5em; font-family:monospace; font-size:1em; cursor:pointer;">visit profile</button>
    </div>
    <div style="position:absolute; bottom:24px; right:32px;">
      <?php if (isset($_SESSION['email'])): ?>
        <input type="radio" name="slideModalLike" id="slideModalLikeRadio" style="width:20px; height:20px; accent-color: #e27979; cursor:pointer;">
      <?php else: ?>
        <div style="display: flex; flex-direction: column; align-items: center; opacity:0.6;">
          <input type="radio" style="width:20px; height:20px; cursor:not-allowed;" disabled>
          <span style="font-size:9px; color:#888; margin-top:4px;">login to select</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Top selected works gallery -->
<div id="selectedWorksGallery" style="width:90vw; margin:2em auto 0 auto; display:flex; gap:40px; overflow-x:auto; padding-bottom:16px;">
<?php foreach ($topWorks as $i => $workPath):
    $work = $workDetails[$workPath];
    ?>
    <div class="selected-work-card" data-idx="<?php echo $i; ?>" style="cursor:pointer; min-width:260px; max-width:320px; flex:0 0 auto; background:#f9f9f9; border-radius:14px; box-shadow:0 4px 14px #0001; padding:12px; display:flex; flex-direction:column; align-items:center;">
        <img src="<?php echo htmlspecialchars($work['path']); ?>" alt="<?php echo htmlspecialchars($work['title']); ?>" style="width:100%; max-width:280px; max-height:220px; object-fit:cover; border-radius:8px;">
        <div style="margin-top:12px;font-size:1.15em;font-weight:bold;"><?php echo htmlspecialchars($work['title']); ?></div>
    </div>
<?php endforeach; ?>
</div>

<!-- Modal for selected works gallery -->
<div id="selectedWorksModal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.85); align-items:center; justify-content:center;">
  <div id="selectedWorksModalContent" style="background:#fff; border-radius:14px; padding:36px 28px; max-width:90vw; max-height:90vh; box-shadow:0 8px 32px #0005; display:flex; flex-direction:column; align-items:center; position:relative;">
    <span id="closeSelectedWorksModal" style="position:absolute; top:16px; right:24px; color:#333; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
    <img id="selectedWorksModalImg" src="" alt="" style="max-width:80vw; max-height:60vh; border-radius:8px; margin-bottom:22px;">
    <div id="selectedWorksModalInfo" style="text-align:center; width:100%;"></div>
    <a id="selectedWorksModalProfileBtn" href="#" style="display:inline-block; margin-top:18px; background:#e8bebe; color:#000; padding:0.6em 1.2em; border-radius:8px; text-decoration:none;">Visit profile</a>
    <div style="position:absolute; bottom:36px; right:28px;">
      <?php if (isset($_SESSION['email'])): ?>
        <input type="radio" name="selectedWorkLike" id="selectedWorkLikeRadio" style="width:20px; height:20px; accent-color: #e27979; cursor:pointer;">
      <?php else: ?>
        <div style="display: flex; flex-direction: column; align-items: center; opacity:0.6;">
          <input type="radio" style="width:20px; height:20px; cursor:not-allowed;" disabled>
          <span style="font-size:9px; color:#888; margin-top:4px;">login to select</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Content Section (Search, Sort, Profiles) -->
<div class="container-container-container" style="display:grid; align-items:center; justify-items: center;">
<div class="container-container" style="border: double; border-radius:20px; padding-top:50px; width:90%; align-items:center; justify-items: center; display:grid; background-color: #f2e9e9;">
  <div style="display:flex; justify-content: center; align-items:center;">
    <div>
      <input type="text" id="artistSearchBar" placeholder="Search artists..." style="width:60vw; padding:0.6em 1em; font-size:1em; border-radius:7px; border:1px solid #ccc;">
    </div>
  </div>
  <div style="display:flex; justify-content:center; align-items:center; margin:1em 0 1em 0;">
    <button id="sortAlphaBtn" style="padding:0.7em 1.3em; font-family: monospace;">name</button>
    <button id="sortDateBtn" style="padding:0.7em 1.3em; font-family: monospace;">date</button>
    <button id="sortCountryBtn" style="padding:0.7em 1.3em; font-family: monospace;">country</button>
    <button id="sortGenreBtn" style="padding:0.7em 1.3em; font-family: monospace;">genre</button>
  </div>
  <div id="user-profiles"></div>
</div>
</div>

<footer style="background:#222; color:#eee; padding:2em 0; text-align:center; font-size:0.95em;">
  <div>
    &copy; 2025 Digital Artist Database. All Rights Reserved.
  </div>
</footer>

<script>
// Helper to safely escape attribute values when building HTML strings (if needed)
function escapeAttr(s) {
    if (!s && s !== 0) return '';
    return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,"&#39;").replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// --- Basic Gallery/Profiles Render and Search/Sort ---
function renderProfiles(profiles) {
    var container = document.getElementById('user-profiles');
    container.innerHTML = '';
    profiles.forEach(function(profileData, idx) {
        var safe_first = profileData.first ? profileData.first.replace(/[^a-zA-Z0-9_\-\.]/g, '_') : '';
        var safe_last = profileData.last ? profileData.last.replace(/[^a-zA-Z0-9_\-\.]/g, '_') : '';
        var profile_username = safe_first + "_" + safe_last;

        var miniSrc = "";
        if (Array.isArray(profileData.work) && profileData.work.length > 0) {
            var found = profileData.work.find(w => /profile_image_/i.test(w.image));
            if (!found) found = profileData.work[0];
            if (found && found.image) miniSrc = found.image.replace("/var/www/html", "");
        }

        var submetaParts = [];
        if (profileData.dob) submetaParts.push(`Born: ${profileData.dob.substring(0,4)}`);
        if (profileData.country) submetaParts.push(escapeAttr(profileData.country));
        if (profileData.genre) submetaParts.push(escapeAttr(profileData.genre));

        var row = document.createElement('div');
        row.className = 'user-row';
        row.innerHTML = `
            <div class="user-row-main">
                ${miniSrc ? `<img src="${escapeAttr(miniSrc)}" alt="${escapeAttr(profile_username)} photo" class="mini-profile">` : '<div class="mini-profile" style="background:#e9eef6;"></div>'}
                <div>
                    <div class="user-name">${escapeAttr(profileData.first || '')} ${escapeAttr(profileData.last || '')}</div>
                    <div class="user-submeta">${submetaParts.join(' &bull; ')}</div>
                </div>
            </div>
            <div class="profile-dropdown"></div>
        `;

        var dropdownContainer = row.querySelector('.profile-dropdown');
        
        row.querySelector('.user-row-main').addEventListener('click', function(e) {
            e.stopPropagation();

            if (dropdownContainer.style.display === 'block') {
                dropdownContainer.style.display = 'none';
                dropdownContainer.innerHTML = '';
            } else {
                buildDropdownContent(dropdownContainer, profileData, profile_username, miniSrc);
                dropdownContainer.style.display = 'block';
            }
        });

        container.appendChild(row);
    });
}

function buildDropdownContent(container, profileData, profile_username, imgSrc) {
    let bioHtml = profileData.bio ? `<div><strong>Bio:</strong> ${escapeAttr(profileData.bio)}</div>` : '';
    
    let workHtml = '';
    if (profileData.work && Array.isArray(profileData.work) && profileData.work.length > 0) {
        workHtml += '<div class="dropdown-gallery-title">Work</div><div class="dropdown-work-gallery">';
        profileData.work.forEach(function(work_item) {
            var workImgSrc = work_item.image ? work_item.image.replace("/var/www/html", "") : '';
            if(workImgSrc) {
                workHtml += `<div class="dropdown-work-item">
                                <img src="${escapeAttr(workImgSrc)}" class="work-image" 
                                    data-desc="${escapeAttr(work_item.desc || '')}" 
                                    data-date="${escapeAttr(work_item.date || '')}" 
                                    data-artist="${escapeAttr((profileData.first || '') + ' ' + (profileData.last || ''))}" 
                                    data-profile="${escapeAttr(profile_username)}" 
                                    data-path="${escapeAttr(workImgSrc)}">
                                <div class="work-info">
                                    <div class="desc">${escapeAttr(work_item.desc || '')}</div>
                                    <div class="date">${escapeAttr(work_item.date || '')}</div>
                                </div>
                             </div>`;
            }
        });
        workHtml += '</div>';
    }
    
    container.innerHTML = `
        <div class="dropdown-inner">
            <div class="dropdown-header">
                <img src="${imgSrc || 'placeholder.png'}" class="dropdown-main-image">
                <div>
                    <div class="dropdown-name">${escapeAttr(profileData.first || '')} ${escapeAttr(profileData.last || '')}</div>
                    <button class="profile-btn" style="margin-top:10px;" onclick="event.stopPropagation(); window.location.href='profile.php?user=${encodeURIComponent(profile_username)}'">Visit Full Profile</button>
                </div>
            </div>
            <div class="dropdown-body">
                <div class="dropdown-meta">${bioHtml}</div>
                ${workHtml}
            </div>
        </div>
    `;
}

// search and sort functions (unchanged logic)
function searchProfiles() {
    var search = (document.getElementById('artistSearchBar').value || "").toLowerCase();
    if (!search) { renderProfiles(userProfiles); return; }
    var filtered = userProfiles.filter(function(profile) {
        return (
            (profile.first && profile.first.toLowerCase().includes(search)) ||
            (profile.last && profile.last.toLowerCase().includes(search)) ||
            (profile.email && profile.email.toLowerCase().includes(search)) ||
            (profile.country && profile.country.toLowerCase().includes(search)) ||
            (profile.genre && profile.genre.toLowerCase().includes(search)) ||
            (profile.bio && profile.bio.toLowerCase().includes(search))
        );
    });
    renderProfiles(filtered);
}
document.getElementById('artistSearchBar').addEventListener('input', searchProfiles);

document.getElementById('sortAlphaBtn').onclick = function() {
    var sorted = userProfiles.slice().sort(function(a, b) {
        var nameA = ((a.first || "") + " " + (a.last || "")).toLowerCase();
        var nameB = ((b.first || "") + " " + (b.last || "")).toLowerCase();
        return nameA.localeCompare(nameB);
    });
    renderProfiles(sorted);
};
document.getElementById('sortDateBtn').onclick = function() {
    var sorted = userProfiles.slice().sort(function(a, b) {
        var dobA = a.dob ? new Date(a.dob) : null;
        var dobB = b.dob ? new Date(b.dob) : null;
        if (!dobA && !dobB) return 0;
        if (!dobA) return 1;
        if (!dobB) return -1;
        return dobA - dobB;
    });
    renderProfiles(sorted);
};
document.getElementById('sortCountryBtn').onclick = function() {
    var sorted = userProfiles.slice().sort(function(a, b) {
        var countryA = (a.country || "").toLowerCase();
        var countryB = (b.country || "").toLowerCase();
        if (!countryA && !countryB) return 0;
        if (!countryA) return 1;
        if (!countryB) return -1;
        return countryA.localeCompare(countryB);
    });
    renderProfiles(sorted);
};
document.getElementById('sortGenreBtn').onclick = function() {
    var sorted = userProfiles.slice().sort(function(a, b) {
        var genreA = (a.genre || "").toLowerCase();
        var genreB = (b.genre || "").toLowerCase();
        if (!genreA && !genreB) return 0;
        if (!genreA) return 1;
        if (!genreB) return -1;
        return genreA.localeCompare(genreB);
    });
    renderProfiles(sorted);
};
renderProfiles(userProfiles);

// --- Slideshow JS & Modal (Simple, Clean) ---
var ssImgs = slideshowImages || [], ssIdx = 0, ssInt = null;
var ssImgElem = document.getElementById('slideshow-img');
function showSS(idx) {
    if (!ssImgs.length) { if (ssImgElem) { ssImgElem.src = ''; ssImgElem.alt = 'No artwork found'; } return;}
    ssIdx = (idx + ssImgs.length)%ssImgs.length;
    if (ssImgElem) ssImgElem.src = ssImgs[ssIdx];
}
function nextSS() { showSS(ssIdx+1);}
function prevSS() { showSS(ssIdx-1);}
function startSSAuto() { if (ssInt) clearInterval(ssInt); ssInt = setInterval(nextSS, 7000);}

// Correctly attach event listeners for slideshow navigation and modal
if (ssImgElem) {
    ssImgElem.onclick = function() {
        openModalForSlideshow(ssIdx);
    };
}
if (document.getElementById('slideshow-next-zone')) {
    document.getElementById('slideshow-next-zone').onclick = function(e){ 
        e.stopPropagation();
        nextSS(); 
        startSSAuto(); 
    };
}
if (document.getElementById('slideshow-prev-zone')) {
    document.getElementById('slideshow-prev-zone').onclick = function(e){
        e.stopPropagation();
        prevSS(); 
        startSSAuto(); 
    };
}

showSS(0); 
startSSAuto();

// --- NEW: Function to handle selecting a work ---
function selectWork(workData) {
    if (!loggedInUser_profile) {
        console.log("Not logged in, cannot select work.");
        return;
    }
    
    // Check if already selected to prevent duplicates
    if (loggedInUser_profile.selected_works && loggedInUser_profile.selected_works.find(w => w.path === workData.path)) {
      console.log("Work already selected.");
      return;
    }

    var formData = new FormData();
    formData.append('action', 'select_work');
    formData.append('work_data', JSON.stringify(workData));

    fetch('testy8.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            console.log("Work selected successfully!");
            // Update local profile data to reflect selection immediately
            if (!loggedInUser_profile.selected_works) loggedInUser_profile.selected_works = [];
            loggedInUser_profile.selected_works.push(workData);
        } else {
            console.error("Failed to select work:", data.message);
        }
    })
    .catch(err => console.error("Error selecting work:", err));
}

// -- Modal Logic (Simple/Single Source of Truth) --
function getProfileForImage(path) {
    if (!path) return null;
    var m = path.match(/^pusers\/([^\/]+)\/work\//); if (!m) return null;
    var folder = m[1];
    for (var i=0;i<userProfiles.length;++i) {
        var profile = userProfiles[i];
        var safe_first = profile.first ? safe_name(profile.first) : "";
        var safe_last = profile.last ? safe_name(profile.last) : "";
        if (`${safe_first}_${safe_last}` === folder) return profile;
    }
    return null;
}
function openModalForSlideshow(slideIdx) {
    var modal = document.getElementById('slideModal');
    if (!modal) return;
    var modalImg = document.getElementById('modalImage');
    var modalArtist = document.getElementById('modalArtist');
    var modalTitle = document.getElementById('modalTitle');
    var modalDate = document.getElementById('modalDate');
    var visitProfileBtn = document.getElementById('visitProfileBtn');
    var radio = document.getElementById('slideModalLikeRadio');

    var path = ssImgs[slideIdx];
    if (modalImg) modalImg.src = path || '';
    var profile = getProfileForImage(path);
    var workData = { path: path, title: '', date: '', artist: '', user_folder: '' };

    if (profile) {
        workData.artist = (profile.first || '') + " " + (profile.last || '');
        workData.user_folder = (profile.first ? safe_name(profile.first) : '') + '_' + (profile.last ? safe_name(profile.last) : '');
        if (Array.isArray(profile.work)) {
            var imgfile = path.split('/').pop();
            var w = profile.work.find(function(w) { if (!w.image) return false; return w.image.endsWith(imgfile); });
            if (w) {
                workData.title = w.desc || "";
                workData.date = w.date || "";
            }
        }
        modalArtist.textContent = workData.artist;
        if (visitProfileBtn) visitProfileBtn.onclick = function() { window.location.href = "profile.php?user="+encodeURIComponent(workData.user_folder);};
    } else {
        if (modalArtist) modalArtist.textContent = "Unknown Artist";
        if (visitProfileBtn) visitProfileBtn.onclick = null;
    }
    if (modalTitle) modalTitle.textContent = workData.title || 'Artwork';
    if (modalDate) modalDate.textContent = workData.date ? 'Date: ' + workData.date : '';
    
    // Check if work is already selected by logged in user
    if (radio) {
        radio.checked = false; // reset
        if (loggedInUser_profile && loggedInUser_profile.selected_works) {
            if (loggedInUser_profile.selected_works.find(w => w.path === path)) {
                radio.checked = true;
            }
        }
        // Attach current work data to radio for selection
        radio.onclick = function() { selectWork(workData); };
    }

    modal.style.display = 'flex';
}
var closeBtn = document.getElementById('closeSlideModal');
if (closeBtn) closeBtn.onclick = function() { document.getElementById('slideModal').style.display = 'none'; };
var slideModal = document.getElementById('slideModal');
if (slideModal) slideModal.onclick = function(e) { if (e.target === this) this.style.display = 'none'; };

// --- Selected works gallery modal logic ---
const selectedWorksData = <?php echo json_encode($topWorksData, JSON_PRETTY_PRINT); ?>;
document.querySelectorAll('.selected-work-card').forEach(card => {
    card.addEventListener('click', function(e) {
        const idx = parseInt(card.getAttribute('data-idx'));
        const work = selectedWorksData[idx];

        document.getElementById('selectedWorksModalImg').src = work.path;
        document.getElementById('selectedWorksModalImg').alt = work.title;

        let infoHtml = `
            <div style="font-weight:bold; font-size:1.2em;">${work.title}</div>
            <div style="color:#e27979; margin-top:6px;">Selected ${work.selected_count} times</div>
            <div style="color:#555; font-size:1em; margin-top:8px;">${work.artist}</div>
            <div style="color:#888; margin-top:2px; font-size:0.95em;">${work.date ? work.date : (work.timestamp ? (new Date(work.timestamp)).toLocaleDateString() : '')}</div>
        `;
        document.getElementById('selectedWorksModalInfo').innerHTML = infoHtml;
        document.getElementById('selectedWorksModalProfileBtn').href = 'profile.php?user=' + encodeURIComponent(work.user_folder);
        
        var radio = document.getElementById('selectedWorkLikeRadio');
        if (radio) {
            radio.checked = false; // reset
            if (loggedInUser_profile && loggedInUser_profile.selected_works && loggedInUser_profile.selected_works.find(w => w.path === work.path)) {
                radio.checked = true;
            }
            radio.onclick = function() { selectWork(work); };
        }

        document.getElementById('selectedWorksModal').style.display = 'flex';
    });
});
document.getElementById('closeSelectedWorksModal').onclick = function() {
    document.getElementById('selectedWorksModal').style.display = 'none';
};
document.getElementById('selectedWorksModal').onclick = function(e) {
    if (e.target === this) this.style.display = 'none';
};

// --- New: attach modal behavior to profile work thumbnails using event delegation ---
document.addEventListener('click', function(e) {
    var t = e.target;
    if (t && t.classList && t.classList.contains('work-image')) {
        e.stopPropagation();
        var workData = {
            path: t.dataset.path || t.src || '',
            title: t.dataset.desc || '',
            date: t.dataset.date || '',
            artist: t.dataset.artist || '',
            user_folder: t.dataset.profile || ''
        };
        
        // Use selectedWorksModal
        var modal = document.getElementById('selectedWorksModal');
        if (!modal) return;
        document.getElementById('selectedWorksModalImg').src = workData.path;
        document.getElementById('selectedWorksModalImg').alt = workData.title || '';
        document.getElementById('selectedWorksModalInfo').innerHTML = `
            <div style="font-weight:bold; font-size:1.2em;">${escapeAttr(workData.title) || 'Artwork'}</div>
            <div style="color:#555; font-size:1em; margin-top:8px;">${escapeAttr(workData.artist)}</div>
            <div style="color:#888; margin-top:6px; font-size:0.95em;">${escapeAttr(workData.date)}</div>
        `;
        document.getElementById('selectedWorksModalProfileBtn').href = 'profile.php?user=' + encodeURIComponent(workData.user_folder);
        
        var radio = document.getElementById('selectedWorkLikeRadio');
        if (radio) {
            radio.checked = false; // reset
            if (loggedInUser_profile && loggedInUser_profile.selected_works && loggedInUser_profile.selected_works.find(w => w.path === workData.path)) {
                radio.checked = true;
            }
            radio.onclick = function() { selectWork(workData); };
        }
        
        modal.style.display = 'flex';
    }
}, true);

</script>

<script>
    // --- Title-container popout menu functionality (unchanged) ---
document.addEventListener('DOMContentLoaded', function() {
  var titleContainer = document.getElementById('mainTitleContainer');
  var menu = document.getElementById('titleMenuPopout');
  var closeBtn = document.getElementById('closeTitleMenu');

  function closeMenu() {
    if (menu) menu.style.display = 'none';
  }

  if (titleContainer && menu) {
    titleContainer.style.cursor = "pointer";
    titleContainer.addEventListener('click', function(e) {
      var rect = titleContainer.getBoundingClientRect();
      menu.style.left = (rect.left + window.scrollX + rect.width + 18) + "px";
      menu.style.top = (rect.top + window.scrollY) + "px";
      menu.style.display = 'block';
    });
  }

  if (closeBtn) {
    closeBtn.onclick = function(e) { closeMenu(); };
  }

  document.addEventListener('mousedown', function(e) {
    if (menu && menu.style.display === 'block' && !menu.contains(e.target) && !titleContainer.contains(e.target)) {
      closeMenu();
    }
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === "Escape") closeMenu();
  });
});
</script>
    
</body>
</html>
