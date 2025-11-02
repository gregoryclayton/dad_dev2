<?php
// Database credentials
include 'connection.php';


// Start session for login/logout
session_start();

// Connect to MySQL
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Helper: create user folder and profile.json
function create_user_profile($first, $last, $email) {
    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $first);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $last);
    $user_dir = "/var/www/html/pusers/" . $safe_first . "_" . $safe_last;
    if (!is_dir($user_dir)) {
        mkdir($user_dir, 0755, true);
    }
    $profile = [
        "first" => $first,
        "last" => $last,
        "email" => $email,
        "created_at" => date("Y-m-d H:i:s"),
        "work" => []
    ];
    file_put_contents($user_dir . "/profile.json", json_encode($profile, JSON_PRETTY_PRINT));
    return $user_dir;
}

// Helper: update profile.json with bio, dob, country
function update_user_profile_extra($first, $last, $bio, $dob, $country) {
    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $first);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $last);
    $user_dir = "/var/www/html/pusers/" . $safe_first . "_" . $safe_last;
    $profile_path = $user_dir . "/profile.json";
    if (file_exists($profile_path)) {
        $profile = json_decode(file_get_contents($profile_path), true);
        $profile['bio'] = $bio;
        $profile['dob'] = $dob;
        $profile['country'] = $country;
        file_put_contents($profile_path, json_encode($profile, JSON_PRETTY_PRINT));
    }
}

// Helper: add work to profile.json
function add_user_work($first, $last, $desc, $date, $image_path) {
    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $first);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $last);
    $user_dir = "/var/www/html/pusers/" . $safe_first . "_" . $safe_last;
    $profile_path = $user_dir . "/profile.json";
    if (file_exists($profile_path)) {
        $profile = json_decode(file_get_contents($profile_path), true);
        if (!isset($profile['work']) || !is_array($profile['work'])) {
            $profile['work'] = [];
        }
        $profile['work'][] = [
            "desc" => $desc,
            "date" => $date,
            "image" => $image_path
        ];
        file_put_contents($profile_path, json_encode($profile, JSON_PRETTY_PRINT));
    }
}


// --- SLIDESHOW IMAGES FROM puserswork ---
$images = [];
$pusersDir = __DIR__ . '/pusers';

if (is_dir($pusersDir)) {
    $userFolders = scandir($pusersDir);
    foreach ($userFolders as $userFolder) {
        if ($userFolder === '.' || $userFolder === '..') continue;
        $workDir = $pusersDir . '/' . $userFolder . '/work';
        if (is_dir($workDir)) {
            $workFiles = scandir($workDir);
            foreach ($workFiles as $file) {
                if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $file)) {
                    $images[] = 'pusers/' . $userFolder . '/work/' . $file;
                }
            }
        }
    }
}
// Randomize the order of images
shuffle($images);


// Handle login
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
            echo "Logged in!";
            header("Location: home.php");
        } else {
            echo "Incorrect password!";
        }
    } else {
        echo "No account found!";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: home.php");
    exit();
}

// Handle registration
if (isset($_POST['register'])) {
    $first = $conn->real_escape_string($_POST['first']);
    $last = $conn->real_escape_string($_POST['last']);
    $email = $conn->real_escape_string($_POST['email']);
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check if email already exists
    $check = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($check->num_rows > 0) {
        echo "Email already registered!";
    } else {
        $conn->query("INSERT INTO users (first,last,email,password) VALUES ('$first','$last','$email','$pass')");
        $user_dir = create_user_profile($first, $last, $email);
        echo "Registration successful! Please log in.";
    }
}

// Collect all profile data into a JSON array
$baseDir = "/var/www/html/pusers";
$userProfiles = [];
if (is_dir($baseDir)) {
    $dirs = glob($baseDir . '/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $profilePath = $dir . "/profile.json";
        if (file_exists($profilePath)) {
            $profileData = json_decode(file_get_contents($profilePath), true);
            if ($profileData) {
                $userProfiles[] = $profileData;
            }
        }
    }
} else {
    echo "User profiles directory not found.";
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
    </script>
</head>
<body>

<div style="display:flex;">
  <div class="title-container" id="mainTitleContainer" style="background-image: linear-gradient(135deg, #e27979 60%, #ed8fd1 100%); transition: background-image 0.7s; ">
    <br>
    <a href="index.php" style="text-decoration:none; color: white;">digital <br>artist <br>database</a>
  </div>
  
   <div id="dotMenuContainer" style="position:relative; align-self:end; margin-bottom:50px; margin-left:-30px;">
    <div id="dot" style="color:black; background: linear-gradient(135deg, #e27979 60%, #ed8fd1 100%); transition: background 0.7s;"></div>
    <div id="dotMenu" style="display:none; position:absolute; left:80px; top:-380%; transform:translateX(-50%); background-image: linear-gradient(to bottom right, rgba(226, 121, 121, 0.936), rgba(237, 143, 209, 1));">
      <!-- Your menu content here -->
      <!-- Add this play icon to the dot menu container -->
      <div id="musicPlayIcon" style="display:none; position:absolute; top:7px; right:41px; background: white; border-radius:50%; padding:2px; font-size:10px; width:16px; height:16px; text-align:center; box-shadow:0 2px 6px #0002;">
        <span style="color:#e27979;">▶</span>
      </div>
      <!-- New buttons for changing color -->
      <div style="position: relative;">
        <button id="musicBtn" style="margin-top:1em; background:white; color:#fff; border:none; border-radius:8px; font-family:monospace; font-size:1em; cursor:pointer; display:block; width:10px;" title="Toggle Music"></button>
        <div id="musicPlayIcon" style="display:none; position:absolute; top:-12px; right:-5px; background: white; border-radius:50%; padding:2px; font-size:10px; width:16px; height:16px; text-align:center;">
          <span style="color:#e27979;">▶</span>
        </div>
        <button id="changeTitleBgBtn" style="margin-top:1em; background:grey; color:#fff; border:none; border-radius:8px; font-family:monospace; font-size:1em; cursor:pointer; display:block; width:10px;"></button>
        <button id="bwThemeBtn" style="margin-top:0.7em; background:lightgrey; color:#fff; border:none; border-radius:8px; padding:0.6em 1.1em; font-family:monospace; font-size:1em; cursor:pointer; display:block;"></button>
      </div>
    </div>
  </div>
  
</div>

<!-- Slideshow of random user work images -->
<?php
// Collect all images from pusers/*/work/*.{jpg,jpeg,png,gif}
$slideshow_images = [];
$pusers_dir = __DIR__ . '/pusers';
if (is_dir($pusers_dir)) {
    $user_folders = scandir($pusers_dir);
    foreach ($user_folders as $user_folder) {
        if ($user_folder === '.' || $user_folder === '..') continue;
        $work_dir = $pusers_dir . '/' . $user_folder . '/work';
        if (is_dir($work_dir)) {
            $work_files = scandir($work_dir);
            foreach ($work_files as $file) {
                if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $file)) {
                    // Path relative to web root
                    $slideshow_images[] = 'pusers/' . $user_folder . '/work/' . $file;
                }
            }
        }
    }
}
shuffle($slideshow_images); // Randomize order
?>

<div id="user-slideshow" style="width:100%; display:flex; justify-content:center; align-items:center; margin: 2em 0;">
    <div style="position:relative;">
        <img id="slideshow-img" src="<?php echo count($slideshow_images) ? htmlspecialchars($slideshow_images[0]) : ''; ?>" alt="Artwork Slideshow" style="max-width:60vw; max-height:300px; border-radius:16px; box-shadow:0 6px 24px #0002; object-fit:contain; background:#f4f4f4;"/>
        <?php if (count($slideshow_images) > 1): ?>
        <button id="prev-btn" style="position:absolute; left:-48px; top:50%; transform:translateY(-50%); background:#fff; border:none; border-radius:50%; width:38px; height:38px; font-size:1.7em; cursor:pointer;">&#8678;</button>
        <button id="next-btn" style="position:absolute; right:-48px; top:50%; transform:translateY(-50%); background:#fff; border:none; border-radius:50%; width:38px; height:38px; font-size:1.7em; cursor:pointer;">&#8680;</button>
        <?php endif; ?>
    </div>
</div>

<?php if (!isset($_SESSION['email'])): ?>
<form method="POST" style="display:flex; max-width:80vw; justify-content: flex-end; padding:10px; border-bottom: 1px solid #e2e2e2; background: #ffffff00; border-bottom-right-radius:10px; border-top-right-radius:10px;">
    <input style="width:50px" type="email" name="email" placeholder="email" required><br>
    <input style="width:50px" type="password" name="password"  placeholder="password" required><br>
    <button name="login">Login</button>
</form>
<?php else: ?>
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['first'] . " " . $_SESSION['last']); ?>!</h2>
    <a href="?logout=1">Logout</a>
<?php endif; ?>

<div class="navbar">
    <div class="navbarbtns">
        <div ><a class="navbtn" href="register.php">[register]</a></div>
         <div ><a class="navbtn" href="studio3.php">[studio]</a></div>
        <div ><a class="navbtn" href="database.php">[database]</a></div>
    </div>
</div>

<div class="container-container-container" style="display:grid; align-items:center; justify-items: center;">
<div class="container-container" style="border: double; border-radius:20px; padding-top:50px; width:90%; align-items:center; justify-items: center; display:grid; background-color: #f2e9e9; box-shadow:0 4px 24px #0002;">

<div style="display:flex; justify-content: center; align-items:center;">
  <div>
    <input type="text" id="artistSearchBar" placeholder="Search artists..." style="width:60vw; padding:0.6em 1em; font-size:1em; border-radius:7px; border:1px solid #ccc;">
  </div>
</div>

<!-- SORT BUTTONS AND SEARCH BAR ROW (MODIFIED) -->
<div style="display:flex; justify-content:center; align-items:center; margin:1em 0 1em 0;">
  <button id="sortAlphaBtn" style="padding:0.7em 1.3em; font-family: monospace; font-size:1em; color: black; background-color: rgba(255, 255, 255, 0); border:none; border-radius:8px; cursor:pointer;">name</button>
  <button id="sortDateBtn" style="padding:0.7em 1.3em; font-family: monospace; font-size:1em; background-color: rgba(255, 255, 255, 0); color:black; border:none; border-radius:8px; cursor:pointer;">date</button>
  <button id="sortCountryBtn" style="padding:0.7em 1.3em; font-family: monospace; font-size:1em; background-color: rgba(255, 255, 255, 0); color:black; border:none; border-radius:8px; cursor:pointer;">country</button>
  <button id="sortGenreBtn" style="padding:0.7em 1.3em; font-family: monospace; font-size:1em; background-color: rgba(255, 255, 255, 0); color:black; border:none; border-radius:8px; cursor:pointer;">genre</button>
</div>

<div id="user-profiles"></div>
<br><br><br><br><br>
</div>
<br><br><br><br><br>
</div>

<?php if (!isset($_SESSION['email'])): ?>
<h2>Register</h2>
<form method="POST">
    First Name: <input type="text" name="first" required><br>
    Last Name: <input type="text" name="last" required><br>
    Email: <input type="email" name="email" required><br>
    Password: <input type="password" name="password" required><br>
    <button name="register">Register</button>
</form>

<?php else: ?>
    <h2>You're already logged in <?php echo htmlspecialchars($_SESSION['first'] . " " . $_SESSION['last']); ?>.</h2>
    <a href="?logout=1">Logout</a>
<?php endif; ?>

<br><br><br><br><br>

<footer style="background:#222; color:#eee; padding:2em 0; text-align:center; font-size:0.95em;">
  <div style="margin-bottom:1em;">
    <nav>
      <a href="/index.php" style="color:#eee; margin:0 15px; text-decoration:none;">home</a>
      <a href="/about.php" style="color:#eee; margin:0 15px; text-decoration:none;">about</a>
      <a href="/signup.php" style="color:#eee; margin:0 15px; text-decoration:none;">sign up</a>
      <a href="/contribute.php" style="color:#eee; margin:0 15px; text-decoration:none;">contribute</a>
      <a href="/database.php" style="color:#eee; margin:0 15px; text-decoration:none;">database</a>
    </nav>
  </div>
  <div style="margin-bottom:1em;">
    <a href="https://discord.com/" target="_blank" rel="noopener" style="margin:0 8px;">
      <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/discord.svg" alt="Twitter" height="22" style="vertical-align:middle; filter:invert(1);">
    </a>
    <a href="https://facebook.com/" target="_blank" rel="noopener" style="margin:0 8px;">
      <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/facebook.svg" alt="Facebook" height="22" style="vertical-align:middle; filter:invert(1);">
    </a>
    <a href="https://instagram.com/" target="_blank" rel="noopener" style="margin:0 8px;">
      <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/instagram.svg" alt="Instagram" height="22" style="vertical-align:middle; filter:invert(1);">
    </a>
    <a href="https://github.com/" target="_blank" rel="noopener" style="margin:0 8px;">
      <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/github.svg" alt="GitHub" height="22" style="vertical-align:middle; filter:invert(1);">
    </a>
  </div>
  <div>
    &copy; 2025 Digital Artist Database. All Rights Reserved.
  </div>
</footer>

<!-- Slide Modal (work card reveal) -->
<div id="slideModal" style="display:none; position:fixed; top:0; left:0;right:0;bottom:0; z-index:9999; background:rgba(0,0,0,0.7); align-items:center; justify-content:center;">
  <div id="slideCard" style="background:white; border-radius:14px; padding:24px 28px; max-width:90vw; max-height:90vh; box-shadow:0 8px 32px #0005; display:flex; flex-direction:column; align-items:center; position:relative;">
    <button id="closeSlideModal" style="position:absolute; top:12px; right:18px; font-size:1.3em; background:none; border:none; color:#333; cursor:pointer;">×</button>
    <img id="modalImg" src="" alt="Image" style="max-width:80vw; max-height:60vh; border-radius:8px; margin-bottom:22px;">
    <div id="modalInfo" style="text-align:center; width:100%;">
      <h2 id="modalTitle" style="color:black; margin-bottom:8px; font-size:24px;"></h2>
      <p id="modalDate" style="color:black; margin-bottom:12px; font-size:16px;"></p>
      <p id="modalArtist" style="color:black; font-weight:bold; font-size:18px;"></p>
    </div>
    <button id="visitProfileBtn" style="margin-top:18px; background:#e8bebe; border:none; border-radius:7px; padding:0.7em 2em; font-family:monospace; font-size:1em; cursor:pointer;">visit profile</button>
  </div>
</div>

<!-- Add your workModal, fullscreenImage, and other modal HTML here as before... -->

<script>
// --- RENDER PROFILES FUNCTION (for sorting/search/initial render) ---
function renderProfiles(profiles) {
    var container = document.getElementById('user-profiles');
    container.innerHTML = '';
    profiles.forEach(function(profileData, idx) {
        var safe_first = profileData.first ? profileData.first.replace(/[^a-zA-Z0-9_\-\.]/g, '_') : '';
        var safe_last = profileData.last ? profileData.last.replace(/[^a-zA-Z0-9_\-\.]/g, '_') : '';
        var profile_username = safe_first + "_" + safe_last;
        var div = document.createElement('div');
        div.className = "user-profile";
        div.setAttribute("data-username", profile_username);
        div.setAttribute("data-idx", idx);

        div.innerHTML = "<strong>" + profileData.first + " " + profileData.last + "</strong><br>";

        // Dropdown for profile info (hidden by default)
        var dropdown = document.createElement('div');
        dropdown.className = "profile-dropdown";
        dropdown.setAttribute("id", "dropdown-" + profile_username);

        var html = "";
        if (profileData.bio) html += "<strong>Bio:</strong> " + profileData.bio + "<br>";
        if (profileData.dob) html += "<strong>Date of Birth:</strong> " + profileData.dob + "<br>";
        if (profileData.country) html += "<strong>Country:</strong> " + profileData.country + "<br>";
        if (profileData.work && Array.isArray(profileData.work) && profileData.work.length > 0) {
            html += "<strong>Work:</strong><ul class='workList'>";
            profileData.work.forEach(function(work_item){
                html += "<li style='margin-bottom:8px;'>";
                if (work_item.image) {
                    var work_img_src = work_item.image.replace("/var/www/html", "");
                    html += '<img src="' + work_img_src + '" class="work-image" alt="Work Image">';
                }
                if (work_item.desc) html += "<br><strong>Description:</strong> " + work_item.desc;
                if (work_item.date) html += "<br><strong>Date:</strong> " + work_item.date;
                html += "</li>";
            });
            html += "</ul>";
        }
        html += '<button class="profile-btn" onclick="window.location.href=\'profile.php?user=' + profile_username + '\'">Profile Page</button>';

        dropdown.innerHTML = html;
        div.appendChild(dropdown);

        div.onclick = function(e) {
            if (e.target.classList.contains('profile-btn')) return;
            var allDropdowns = document.querySelectorAll('.profile-dropdown');
            allDropdowns.forEach(function(d) { if (d !== dropdown) d.style.display = 'none'; });
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        };

        container.appendChild(div);
    });
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('user-profile') && !e.target.classList.contains('profile-btn')) {
            document.querySelectorAll('.profile-dropdown').forEach(function(d) {
                d.style.display = 'none';
            });
        }
    });
}

// --- SORTING BUTTONS ---
document.addEventListener("DOMContentLoaded", function() {
    renderProfiles(userProfiles);

    var sortAlphaBtn = document.getElementById('sortAlphaBtn');
    if (sortAlphaBtn) {
        sortAlphaBtn.addEventListener('click', function() {
            var sorted = userProfiles.slice().sort(function(a, b) {
                var nameA = ((a.first || "") + " " + (a.last || "")).toLowerCase();
                var nameB = ((b.first || "") + " " + (b.last || "")).toLowerCase();
                if (nameA < nameB) return -1;
                if (nameA > nameB) return 1;
                return 0;
            });
            renderProfiles(sorted);
        });
    }

    var sortDateBtn = document.getElementById('sortDateBtn');
    if (sortDateBtn) {
        sortDateBtn.addEventListener('click', function() {
            var sorted = userProfiles.slice().sort(function(a, b) {
                var dobA = a.dob ? new Date(a.dob) : null;
                var dobB = b.dob ? new Date(b.dob) : null;
                if (!dobA && !dobB) return 0;
                if (!dobA) return 1;
                if (!dobB) return -1;
                return dobA - dobB;
            });
            renderProfiles(sorted);
        });
    }

    var sortCountryBtn = document.getElementById('sortCountryBtn');
    if (sortCountryBtn) {
        sortCountryBtn.addEventListener('click', function() {
            var sorted = userProfiles.slice().sort(function(a, b) {
                var countryA = (a.country || "").toLowerCase();
                var countryB = (b.country || "").toLowerCase();
                if (!countryA && !countryB) return 0;
                if (!countryA) return 1;
                if (!countryB) return -1;
                if (countryA < countryB) return -1;
                if (countryA > countryB) return 1;
                return 0;
            });
            renderProfiles(sorted);
        });
    }

    var sortGenreBtn = document.getElementById('sortGenreBtn');
    if (sortGenreBtn) {
        sortGenreBtn.addEventListener('click', function() {
            var sorted = userProfiles.slice().sort(function(a, b) {
                var genreA = (a.genre || "").toLowerCase();
                var genreB = (b.genre || "").toLowerCase();
                if (!genreA && !genreB) return 0;
                if (!genreA) return 1;
                if (!genreB) return -1;
                if (genreA < genreB) return -1;
                if (genreA > genreB) return 1;
                return 0;
            });
            renderProfiles(sorted);
        });
    }

    // --- SEARCH BAR FUNCTIONALITY ---
    var searchBar = document.getElementById('artistSearchBar');
    if (searchBar) {
        searchBar.addEventListener('input', function() {
            var search = (searchBar.value || "").toLowerCase().trim();
            if (!search) {
                renderProfiles(userProfiles);
                return;
            }
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
        });
    }
});

// --- SLIDESHOW LOGIC & MODAL FIX ---
var slideshowImages = <?php echo json_encode($slideshow_images, JSON_UNESCAPED_SLASHES); ?>;
var userProfiles = <?php echo json_encode($userProfiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>;
var slideshowCurrent = 0;
var slideshowImg = document.getElementById('slideshow-img');
var slideshowTimer = null;
var slideshowInterval = 7000;

// Helper to get user profile by folder name
function getUserProfileFromImagePath(path) {
    var match = path.match(/^pusers\/([^\/]+)\/work\//);
    if (!match) return null;
    var folder = match[1];
    var parts = folder.split('_');
    var safeFirst = parts[0] || "";
    var safeLast = parts.length > 1 ? parts.slice(1).join('_') : "";
    for (var i = 0; i < userProfiles.length; ++i) {
        var up = userProfiles[i];
        var pf = up.first ? up.first.replace(/[^a-zA-Z0-9_\-\.]/g, '_') : "";
        var pl = up.last ? up.last.replace(/[^a-zA-Z0-9_\-\.]/g, '_') : "";
        if (pf === safeFirst && pl === safeLast) {
            return up;
        }
    }
    return null;
}

function showSlideshowModal(idx) {
    var modal = document.getElementById('slideModal');
    var modalImg = document.getElementById('modalImg');
    var modalInfo = document.getElementById('modalInfo');
    var modalTitle = document.getElementById('modalTitle');
    var modalDate = document.getElementById('modalDate');
    var modalArtist = document.getElementById('modalArtist');
    var visitProfileBtn = document.getElementById('visitProfileBtn');
    var imgPath = slideshowImages[idx];

    if (modalImg) modalImg.src = imgPath;
    var userProfile = getUserProfileFromImagePath(imgPath);
    if (userProfile) {
        var safe_first = userProfile.first ? userProfile.first.replace(/[^a-zA-Z0-9_\-\.]/g, '_') : '';
        var safe_last = userProfile.last ? userProfile.last.replace(/[^a-zA-Z0-9_\-\.]/g, '_') : '';
        var profile_username = safe_first + "_" + safe_last;

        var workItem = null;
        if (Array.isArray(userProfile.work)) {
            workItem = userProfile.work.find(function(w) {
                if (!w.image) return false;
                var justFile = imgPath.split('/').pop();
                return w.image.endsWith(justFile) || w.image.indexOf(justFile) !== -1;
            });
        }

        if (modalTitle) modalTitle.textContent = workItem && workItem.desc ? workItem.desc : 'Artwork';
        if (modalDate) modalDate.textContent = workItem && workItem.date ? 'Date: ' + workItem.date : '';
        if (modalArtist) modalArtist.textContent = (userProfile.first || '') + " " + (userProfile.last || '');

        if (visitProfileBtn) {
            visitProfileBtn.onclick = function() {
                window.location.href = 'profile.php?user=' + encodeURIComponent(profile_username);
            };
        }
        if (modalInfo && !modalTitle && !modalDate && !modalArtist) {
            modalInfo.innerHTML = '<strong>' + (userProfile.first || '') + ' ' + (userProfile.last || '') + '</strong>';
        }
    } else {
        if (modalTitle) modalTitle.textContent = 'Artwork';
        if (modalDate) modalDate.textContent = '';
        if (modalArtist) modalArtist.textContent = '';
        if (visitProfileBtn) visitProfileBtn.onclick = null;
        if (modalInfo) modalInfo.innerHTML = "<strong>Unknown artist</strong>";
    }

    if (modal) modal.style.display = 'flex';
}

function showSlideshowImage(idx) {
    if (!slideshowImages.length) {
        slideshowImg.src = '';
        slideshowImg.alt = 'No artwork found';
        return;
    }
    slideshowCurrent = (idx + slideshowImages.length) % slideshowImages.length;
    slideshowImg.src = slideshowImages[slideshowCurrent];
}
function nextSlideshowImage() { showSlideshowImage(slideshowCurrent + 1); }
function prevSlideshowImage() { showSlideshowImage(slideshowCurrent - 1); }
function startSlideshowTimer() {
    if (slideshowTimer) clearInterval(slideshowTimer);
    slideshowTimer = setInterval(function() {
        nextSlideshowImage();
    }, slideshowInterval);
}

if (document.getElementById('next-btn')) {
    document.getElementById('next-btn').onclick = function() {
        nextSlideshowImage(); showSlideshowModal(slideshowCurrent); startSlideshowTimer();
    };
}
if (document.getElementById('prev-btn')) {
    document.getElementById('prev-btn').onclick = function() {
        prevSlideshowImage(); showSlideshowModal(slideshowCurrent); startSlideshowTimer();
    };
}
if (slideshowImg) {
    slideshowImg.onclick = function() {
        showSlideshowModal(slideshowCurrent);
    };
}
showSlideshowImage(0);
startSlideshowTimer();

// Modal close logic
var closeBtn = document.getElementById('closeSlideModal');
var modal = document.getElementById('slideModal');
if (closeBtn && modal) {
    closeBtn.onclick = function() {
        modal.style.display = 'none';
    };
    modal.onclick = function(e) {
        if (e.target === modal) modal.style.display = 'none';
    };
}
</script>
