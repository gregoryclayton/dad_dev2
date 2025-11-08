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
      #slideshow-image-wrapper {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
      }
      #slideshow-img {
        width: 100%;
        height: 100%;
        object-fit: contain;
      }


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
    <div id="dotMenu" style="display:none; position:absolute; left:80px; top:-380%; transform:translateX(-50%); background-image: linear-gradient(to bottom right, rgba(226, 121, 121, 0.936), rgba(237, 143, 209, 0.897)); border-radius: 14px; padding: 1em 1em 1em 1em; box-shadow: 0 4px 24px #00000024;">
      <!-- Your menu content here -->
     <!-- Add this play icon to the dot menu container -->
<div id="musicPlayIcon" style="display:none; position:absolute; top:7px; right:41px; background: white; border-radius:50%; padding:2px; font-size:10px; width:16px; height:16px; text-align:center; box-shadow: 0 1px 4px #0003;">
  <span style="color:#e27979;">▶</span>
</div>
      <!-- New buttons for changing color -->
      <div style="position: relative;">
  <button id="musicBtn" style="margin-top:1em; background:white; color:#fff; border:none; border-radius:8px; font-family:monospace; font-size:1em; cursor:pointer; display:block; width:10px;" title="Toggle Music">🎵</button>
  <div id="musicPlayIcon" style="display:none; position:absolute; top:-12px; right:-5px; background: white; border-radius:50%; padding:2px; font-size:10px; width:16px; height:16px; text-align:center; box-shadow:0 1px 4px #0003;">
    <span style="color:#e27979;">▶</span>
  </div>
</div>
      <button id="changeTitleBgBtn" style="margin-top:1em; background:grey; color:#fff; border:none; border-radius:8px; font-family:monospace; font-size:1em; cursor:pointer; display:block; width:10px;" title="Change Background"></button>
      <button id="bwThemeBtn" style="margin-top:0.7em; background:lightgrey; color:#fff; border:none; border-radius:8px; padding:0.6em 1.1em; font-family:monospace; font-size:1em; cursor:pointer; display:block; width:10px;"></button>
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


<form method="POST" style="display:flex; max-width:80vw; justify-content: flex-end; padding:10px; border-bottom: 1px solid #e2e2e2; background: #ffffff00; border-bottom-right-radius:10px; border-top-right-radius:10px;">
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
    <div id="slideshow-image-wrapper">
        <img id="slideshow-img" src="<?php echo count($slideshow_images) ? htmlspecialchars($slideshow_images[0]) : ''; ?>" alt="Artwork Slideshow" />
    </div>
</div>

<!-- Top selected works gallery -->
<div id="selectedWorksGallery" style="width:90vw; margin:2em auto 0 auto; display:flex; gap:40px; overflow-x:auto; padding-bottom:16px;">
<?php foreach ($topWorks as $i => $workPath):
    $work = $workDetails[$workPath];
    ?>
    <div class="selected-work-card" data-idx="<?php echo $i; ?>" style="cursor:pointer; min-width:260px; max-width:320px; flex:0 0 auto; background:#f9f9f9; border-radius:14px; box-shadow:0 4px 14px #00000012;">
        <img src="<?php echo htmlspecialchars($work['path']); ?>" alt="<?php echo htmlspecialchars($work['title']); ?>" style="width:100%; max-width:280px; max-height:220px; object-fit:cover; border-radius:14px;">
        <div style="margin-top:12px;font-size:1.15em;font-weight:bold;"><?php echo htmlspecialchars($work['title']); ?></div>
    </div>
<?php endforeach; ?>
</div>

<!-- Universal Modal for All Works -->
<div id="universalModal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.85); align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:14px; padding:36px 28px; max-width:90vw; max-height:90vh; box-shadow:0 8px 32px #0005; display:flex; flex-direction:column; align-items:center; position:relative;">
    <span id="closeUniversalModal" style="position:absolute; top:16px; right:24px; color:#333; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
    <img id="universalModalImg" src="" alt="" style="max-width:80vw; max-height:60vh; border-radius:8px; margin-bottom:22px;">
    <div id="universalModalInfo" style="text-align:center; width:100%;"></div>
    <a id="universalModalProfileBtn" href="#" style="display:inline-block; margin-top:18px; background:#e8bebe; color:#000; padding:0.6em 1.2em; border-radius:8px; text-decoration:none;">Visit profile</a>
    <div style="position:absolute; bottom:36px; right:28px;">
      <?php if (isset($_SESSION['email'])): ?>
        <input type="radio" name="universalWorkLike" id="universalWorkLikeRadio" style="width:20px; height:20px; accent-color: #e27979; cursor:pointer;">
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
// --- Helper Functions ---
function escapeAttr(s) {
    if (!s && s !== 0) return '';
    return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,"&#39;").replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function safe_name(val) {
    if (!val) return "";
    return val.replace(/[^a-zA-Z0-9_\-\.]/g, '_');
}

// --- Universal Modal Logic ---
function openUniversalModal(workData) {
    const modal = document.getElementById('universalModal');
    if (!modal) return;

    document.getElementById('universalModalImg').src = workData.path;
    document.getElementById('universalModalImg').alt = workData.title;

    let infoHtml = `<div style="font-weight:bold; font-size:1.2em;">${workData.title || 'Artwork'}</div>
                    <div style="color:#555; font-size:1em; margin-top:8px;">${workData.artist}</div>
                    <div style="color:#888; margin-top:6px; font-size:0.95em;">${workData.date}</div>`;
    if(workData.selected_count) {
        infoHtml += `<div style="color:#e27979; margin-top:6px;">Selected ${workData.selected_count} times</div>`;
    }
    document.getElementById('universalModalInfo').innerHTML = infoHtml;
    
    document.getElementById('universalModalProfileBtn').href = 'profile.php?user=' + encodeURIComponent(workData.user_folder);
    
    const radio = document.getElementById('universalWorkLikeRadio');
    if (radio && loggedInUser_profile) {
        radio.checked = loggedInUser_profile.selected_works?.some(w => w.path === workData.path) || false;
        radio.onclick = () => selectWork(workData);
    }
    modal.style.display = 'flex';
}

function selectWork(workData) {
    if (!loggedInUser_profile) return;
    if (loggedInUser_profile.selected_works?.some(w => w.path === workData.path)) return;

    const formData = new FormData();
    formData.append('action', 'select_work');
    formData.append('work_data', JSON.stringify(workData));

    fetch('testy8.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            console.log("Work selected successfully!");
            if (!loggedInUser_profile.selected_works) loggedInUser_profile.selected_works = [];
            loggedInUser_profile.selected_works.push(workData);
        } else {
            console.error("Failed to select work:", data.message);
        }
    })
    .catch(err => console.error("Error selecting work:", err));
}

// --- Slideshow Logic ---
var ssImgs = slideshowImages || [], ssIdx = 0, ssInt = null;
function showSS(idx) {
    if (!ssImgs.length) return;
    ssIdx = (idx + ssImgs.length) % ssImgs.length;
    document.getElementById('slideshow-img').src = ssImgs[ssIdx];
}
function startSSAuto() { 
    if (ssInt) clearInterval(ssInt); 
    ssInt = setInterval(() => showSS(ssIdx + 1), 7000);
}

function getProfileForImage(path) {
    if (!path) return null;
    const match = path.match(/^pusers\/([^\/]+)\/work\//);
    if (!match) return null;
    const folder = match[1];
    return userProfiles.find(profile => {
        const safe_first = safe_name(profile.first);
        const safe_last = safe_name(profile.last);
        return `${safe_first}_${safe_last}` === folder;
    });
}

// --- Profile List Logic ---
function renderProfiles(profiles) {
    const container = document.getElementById('user-profiles');
    container.innerHTML = '';
    profiles.forEach(function(profileData) {
        const safe_first = safe_name(profileData.first);
        const safe_last = safe_name(profileData.last);
        const profile_username = `${safe_first}_${safe_last}`;

        let miniSrc = "";
        if (profileData.work && profileData.work.length > 0) {
            const found = profileData.work.find(w => /profile_image_/i.test(w.image)) || profileData.work[0];
            if (found && found.image) miniSrc = found.image.replace("/var/www/html", "");
        }

        const submetaParts = [];
        if (profileData.dob) submetaParts.push(`Born: ${profileData.dob.substring(0,4)}`);
        if (profileData.country) submetaParts.push(escapeAttr(profileData.country));
        if (profileData.genre) submetaParts.push(escapeAttr(profileData.genre));

        const row = document.createElement('div');
        row.className = 'user-row';
        row.innerHTML = `<div class="user-row-main">
                ${miniSrc ? `<img src="${escapeAttr(miniSrc)}" alt="${escapeAttr(profile_username)} photo" class="mini-profile">` : '<div class="mini-profile" style="background:#e9eef6;"></div>'}
                <div>
                    <div class="user-name">${escapeAttr(profileData.first || '')} ${escapeAttr(profileData.last || '')}</div>
                    <div class="user-submeta">${submetaParts.join(' &bull; ')}</div>
                </div>
            </div>
            <div class="profile-dropdown"></div>`;

        const dropdownContainer = row.querySelector('.profile-dropdown');
        row.querySelector('.user-row-main').addEventListener('click', (e) => {
            e.stopPropagation();
            if (dropdownContainer.style.display === 'block') {
                dropdownContainer.style.display = 'none';
                dropdownContainer.innerHTML = '';
            } else {
                document.querySelectorAll('.profile-dropdown').forEach(d => {
                    d.style.display = 'none';
                    d.innerHTML = '';
                });
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
                const workData = {
                    path: workImgSrc,
                    title: work_item.desc || '',
                    date: work_item.date || '',
                    artist: `${profileData.first || ''} ${profileData.last || ''}`,
                    user_folder: profile_username
                };
                workHtml += `<div class="dropdown-work-item">
                                <img src="${escapeAttr(workImgSrc)}" class="work-image" onclick='openUniversalModal(${JSON.stringify(workData)})'>
                                <div class="work-info">
                                    <div class="desc">${escapeAttr(work_item.desc || '')}</div>
                                    <div class="date">${escapeAttr(work_item.date || '')}</div>
                                </div>
                             </div>`;
            }
        });
        workHtml += '</div>';
    }
    
    container.innerHTML = `<div class="dropdown-inner">
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
        </div>`;
}

// --- DOMContentLoaded: Attach all event listeners ---
document.addEventListener("DOMContentLoaded", function() {
    // Slideshow listeners
    const slideshowWrapper = document.getElementById('slideshow-image-wrapper');
    if (slideshowWrapper) {
        slideshowWrapper.addEventListener('click', function(e) {
            const rect = e.currentTarget.getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const width = rect.width;

            if (clickX < width * 0.2) { showSS(ssIdx - 1); startSSAuto(); } 
            else if (clickX > width * 0.8) { showSS(ssIdx + 1); startSSAuto(); } 
            else {
                const path = ssImgs[ssIdx];
                const profile = getProfileForImage(path);
                const workData = { path: path, title: '', date: '', artist: 'Unknown Artist', user_folder: '' };
                if (profile) {
                    workData.artist = `${profile.first || ''} ${profile.last || ''}`;
                    workData.user_folder = `${safe_name(profile.first)}_${safe_name(profile.last)}`;
                    const imgfile = path.split('/').pop();
                    const w = profile.work.find(w => w.image && w.image.endsWith(imgfile));
                    if (w) {
                        workData.title = w.desc || "";
                        workData.date = w.date || "";
                    }
                }
                openUniversalModal(workData);
            }
        });
    }
    showSS(0); 
    startSSAuto();

    // Top selected works gallery listeners
    const topWorksData = <?php echo json_encode($topWorksData); ?>;
    document.querySelectorAll('.selected-work-card').forEach(card => {
        card.addEventListener('click', function() {
            const idx = parseInt(card.getAttribute('data-idx'));
            const work = topWorksData[idx];
            openUniversalModal(work);
        });
    });

    // Universal modal close listeners
    document.getElementById('closeUniversalModal').onclick = () => { document.getElementById('universalModal').style.display = 'none'; };
    document.getElementById('universalModal').onclick = (e) => { if (e.target === e.currentTarget) e.currentTarget.style.display = 'none'; };

    // Profile list, search, and sort listeners
    renderProfiles(userProfiles);
    const searchBar = document.getElementById('artistSearchBar');
    searchBar.addEventListener('input', () => {
        const search = searchBar.value.toLowerCase();
        if (!search) { renderProfiles(userProfiles); return; }
        const filtered = userProfiles.filter(p =>
            (p.first && p.first.toLowerCase().includes(search)) ||
            (p.last && p.last.toLowerCase().includes(search)) ||
            (p.dob && p.dob.toLowerCase().includes(search)) ||
            (p.country && p.country.toLowerCase().includes(search)) ||
            (p.genre && p.genre.toLowerCase().includes(search))
        );
        renderProfiles(filtered);
    });

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
});

</script>
    
</body>
</html>
