<?php
// Database credentials
include 'connection.php';

session_start();

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

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
        "work" => []
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
if (is_dir($baseDir)) {
    foreach (glob($baseDir . '/*', GLOB_ONLYDIR) as $dir) {
        $profilePath = $dir . "/profile.json";
        if (file_exists($profilePath)) {
            $profileData = json_decode(file_get_contents($profilePath), true);
            if ($profileData) $userProfiles[] = $profileData;
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
    </script>
    <style>
      /* small inline style for mini profile image used in the list */
      .mini-profile {
        width:30px;
        height:30px;
        object-fit:cover;
        border-radius:6px;
        margin-right:10px;
        vertical-align:top;
        box-shadow:0 2px 8px rgba(0,0,0,0.12);
      }
      .user-row {
        display:flex;
        align-items:center;
        gap:10px;
        padding:8px 10px;
        border-bottom:1px solid #eee;
        cursor:pointer;
      }
      .user-row:hover { background:#fff; }
      .user-name { font-size: 14px; font-family: monospace; }
      .profile-dropdown { margin-top:8px; display:none; }
      .work-image {
        max-width:120px;
        max-height:80px;
        object-fit:cover;
        border-radius:8px;
        margin:6px;
        cursor:pointer;
        box-shadow:0 2px 8px rgba(0,0,0,0.08);
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
    <div id="dotMenu" style="display:none; position:absolute; left:80px; top:-380%; transform:translateX(-50%); background-image: linear-gradient(to bottom right, rgba(226, 121, 121, 0.936), rgba(237,[...]);">
      <!-- menu omitted for brevity -->
    </div>
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
<form method="POST" style="display:flex; max-width:80vw; justify-content: flex-end; padding:10px;">
    <input style="width:80px; margin-right:20px;" type="email" name="email" placeholder="email" required>
    <input style="width:80px; margin-right:20px;" type="password" name="password"  placeholder="password" required>
    <button name="login">Login</button>
</form>
<?php else: ?>
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['first'] . " " . $_SESSION['last']); ?>!</h2>
    <a href="?logout=1">Logout</a>
<?php endif; ?>

<!-- Slideshow -->
<div id="user-slideshow" style="width:100%; display:flex; justify-content:center; align-items:center; margin: 2em 0;">
    <div style="position:relative;">
        <img id="slideshow-img" src="<?php echo count($slideshow_images) ? htmlspecialchars($slideshow_images[0]) : ''; ?>" 
             alt="Artwork Slideshow" style="max-width:60vw; max-height:300px; border-radius:16px; box-shadow:0 6px 24px #0002; object-fit:contain; background:#f4f4f4; cursor:pointer;"/>
        <?php if (count($slideshow_images) > 1): ?>
            <button id="prev-btn" style="position:absolute; left:-48px; top:50%; transform:translateY(-50%); background:#fff; border:none; border-radius:50%; width:38px; height:38px; font-size:1.7em; cursor:pointer;">&#8678;</button>
            <button id='next-btn' style="position:absolute; right:-48px; top:50%; transform:translateY(-50%); background:#fff; border:none; border-radius:50%; width:38px; height:38px; font-size:1.7em; cursor:pointer;">&#8680;</button>
        <?php endif; ?>
    </div>
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
  </div>
</div>

<!-- Top selected works gallery -->
<div id="selectedWorksGallery" style="width:90vw; margin:2em auto 0 auto; display:flex; gap:40px; overflow-x:auto; padding-bottom:16px;">
<?php foreach ($topWorks as $i => $workPath):
    $work = $workDetails[$workPath];
    ?>
    <div class="selected-work-card" data-idx="<?php echo $i; ?>" style="cursor:pointer; min-width:260px; max-width:320px; flex:0 0 auto; background:#f9f9f9; border-radius:14px; box-shadow:0 4px 14px #0001; padding:20px; text-align:center; display:flex; flex-direction:column; align-items:center;">
        <img src="<?php echo htmlspecialchars($work['path']); ?>" alt="<?php echo htmlspecialchars($work['title']); ?>" style="width:100%; max-width:280px; max-height:220px; object-fit:cover; border-radius:12px;">
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

        // Determine mini-profile image:
        var miniSrc = "";
        if (Array.isArray(profileData.work) && profileData.work.length > 0) {
            var found = profileData.work.find(function(w){
                if (!w.image) return false;
                return /profile_image_/i.test(w.image);
            });
            if (!found) found = profileData.work[0];
            if (found && found.image) miniSrc = found.image.replace("/var/www/html", "");
        }

        var row = document.createElement('div');
        row.className = 'user-row';

        // mini image element
        if (miniSrc) {
            var img = document.createElement('img');
            img.className = 'mini-profile';
            img.src = miniSrc;
            img.alt = profile_username + ' photo';
            row.appendChild(img);
        } else {
            var placeholder = document.createElement('div');
            placeholder.style.width = '30px';
            placeholder.style.height = '30px';
            placeholder.style.borderRadius = '6px';
            placeholder.style.background = '#f0f0f0';
            placeholder.style.marginRight = '10px';
            row.appendChild(placeholder);
        }

        // name + dropdown container
        var nameDiv = document.createElement('div');
        nameDiv.style.flex = '1';
        nameDiv.innerHTML = '<div class="user-name">' + (profileData.first || '') + ' ' + (profileData.last || '') + '</div>';

        // hidden dropdown content
        var details = document.createElement('div');
        details.className = 'profile-dropdown';
        details.style.display = 'none';
        details.style.marginTop = '8px';
        details.style.fontSize = '0.95em';

        // Build details content programmatically to attach data attributes on images
        if (profileData.bio) {
            var bioDiv = document.createElement('div');
            bioDiv.innerHTML = '<strong>Bio:</strong> ' + (profileData.bio || '');
            details.appendChild(bioDiv);
        }
        if (profileData.dob) {
            var dobDiv = document.createElement('div');
            dobDiv.innerHTML = '<strong>DOB:</strong> ' + (profileData.dob || '');
            details.appendChild(dobDiv);
        }
        if (profileData.country) {
            var countryDiv = document.createElement('div');
            countryDiv.innerHTML = '<strong>Country:</strong> ' + (profileData.country || '');
            details.appendChild(countryDiv);
        }
        if (profileData.genre) {
            var genreDiv = document.createElement('div');
            genreDiv.innerHTML = '<strong>Genre:</strong> ' + (profileData.genre || '');
            details.appendChild(genreDiv);
        }

        // Work thumbnails: create a container and append thumbnail images with dataset attributes
        if (profileData.work && Array.isArray(profileData.work) && profileData.work.length > 0) {
            var workWrapTitle = document.createElement('div');
            workWrapTitle.style.marginTop = '8px';
            workWrapTitle.innerHTML = '<strong>Work:</strong>';
            details.appendChild(workWrapTitle);

            var workList = document.createElement('div');
            workList.style.display = 'flex';
            workList.style.flexWrap = 'wrap';
            workList.style.marginTop = '6px';

            profileData.work.forEach(function(work_item){
                var workBox = document.createElement('div');
                workBox.style.display = 'flex';
                workBox.style.flexDirection = 'column';
                workBox.style.alignItems = 'center';
                workBox.style.marginRight = '8px';
                workBox.style.marginBottom = '8px';

                if (work_item.image) {
                    var workImgSrc = work_item.image.replace("/var/www/html", "");
                    var wImg = document.createElement('img');
                    wImg.className = 'work-image';
                    wImg.src = workImgSrc;
                    wImg.alt = work_item.desc ? work_item.desc : 'work';
                    // attach data-* attributes so modal can read them
                    wImg.dataset.desc = work_item.desc || '';
                    wImg.dataset.date = work_item.date || '';
                    wImg.dataset.artist = (profileData.first || '') + ' ' + (profileData.last || '');
                    wImg.dataset.profile = profile_username;
                    wImg.dataset.path = workImgSrc;
                    // clicking a thumbnail will open the selectedWorksModal (handled by global click listener below)
                    workBox.appendChild(wImg);
                }
                if (work_item.desc) {
                    var caption = document.createElement('div');
                    caption.style.fontSize = '0.9em';
                    caption.style.color = '#333';
                    caption.style.marginTop = '6px';
                    caption.textContent = work_item.desc;
                    workBox.appendChild(caption);
                }
                workList.appendChild(workBox);
            });
            details.appendChild(workList);
        }

        // profile page button
        var profileBtnWrap = document.createElement('div');
        profileBtnWrap.style.marginTop = '8px';
        var profileBtn = document.createElement('button');
        profileBtn.className = 'profile-btn';
        profileBtn.textContent = 'Profile Page';
        profileBtn.addEventListener('click', function(e){
            e.stopPropagation();
            window.location.href = 'profile.php?user=' + encodeURIComponent(profile_username);
        });
        profileBtnWrap.appendChild(profileBtn);
        details.appendChild(profileBtnWrap);

        nameDiv.appendChild(details);

        // toggle on row click (except when clicking the profile button or thumbnails)
        row.addEventListener('click', function(e){
            if (e.target && e.target.classList && (e.target.classList.contains('profile-btn') || e.target.classList.contains('work-image'))) return;
            details.style.display = details.style.display === 'block' ? 'none' : 'block';
        });

        row.appendChild(nameDiv);
        container.appendChild(row);
    });
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
if (document.getElementById('next-btn')) document.getElementById('next-btn').onclick = function(){nextSS();startSSAuto();};
if (document.getElementById('prev-btn')) document.getElementById('prev-btn').onclick = function(){prevSS();startSSAuto();};
if (ssImgElem) ssImgElem.onclick = function() { openModalForSlideshow(ssIdx); };
showSS(0); startSSAuto();

// -- Modal Logic (Simple/Single Source of Truth) --
function getProfileForImage(path) {
    var m = path.match(/^pusers\/([^\/]+)\/work\//); if (!m) return null;
    var folder = m[1], parts = folder.split('_');
    for (var i=0;i<userProfiles.length;++i) {
        var pf = userProfiles[i].first ? userProfiles[i].first.replace(/[^a-zA-Z0-9_\-\.]/g, '_'):"", pl = userProfiles[i].last ? userProfiles[i].last.replace(/[^a-zA-Z0-9_\-\.]/g, '_'):"";
        if (pf===parts[0] && pl===parts.slice(1).join('_')) return userProfiles[i];
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
    var path = ssImgs[slideIdx];
    if (modalImg) modalImg.src = path || '';
    var profile = getProfileForImage(path);
    var desc = "", date = "";
    if (profile) {
        modalArtist.textContent = (profile.first || '') + " " + (profile.last || '');
        if (Array.isArray(profile.work)) {
            var imgfile = path.split('/').pop();
            var w = profile.work.find(function(w) { if (!w.image) return false; return w.image.endsWith(imgfile) || w.image.indexOf(imgfile)!==-1; });
            desc = w && w.desc ? w.desc : "";
            date = w && w.date ? w.date : "";
        }
        var safe_first = profile.first ? profile.first.replace(/[^a-zA-Z0-9_\-\.]/g, '_'):"";
        var safe_last = profile.last ? profile.last.replace(/[^a-zA-Z0-9_\-\.]/g, '_'):"";
        var profile_username = safe_first + "_" + safe_last;
        if (visitProfileBtn) visitProfileBtn.onclick = function() { window.location.href = "profile.php?user="+encodeURIComponent(profile_username);};
    } else {
        if (modalArtist) modalArtist.textContent = "";
        if (visitProfileBtn) visitProfileBtn.onclick = function() { };
    }
    if (modalTitle) modalTitle.textContent = desc || 'Artwork';
    if (modalDate) modalDate.textContent = date ? 'Date: ' + date : '';
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
            <div style="color:#aaa; font-size:0.92em; margin-top:8px;">${work.user_folder}</div>
            <div style="color:#aaa; font-size:0.92em; margin-top:8px;">${work.path}</div>
        `;
        document.getElementById('selectedWorksModalInfo').innerHTML = infoHtml;
        document.getElementById('selectedWorksModalProfileBtn').href = 'profile.php?artist=' + encodeURIComponent(work.user_folder);
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
// When a .work-image inside a profile dropdown is clicked, open the selectedWorksModal and populate it
document.addEventListener('click', function(e) {
    var t = e.target;
    if (t && t.classList && t.classList.contains('work-image')) {
        e.stopPropagation();
        var path = t.dataset.path || t.src || '';
        var desc = t.dataset.desc || '';
        var date = t.dataset.date || '';
        var artist = t.dataset.artist || '';
        var profile = t.dataset.profile || '';

        // Build info HTML similar to selectedWorksModal format
        var infoHtml = `
            <div style="font-weight:bold; font-size:1.2em;">${desc ? escapeAttr(desc) : (path.split('/').pop().replace(/\.[^/.]+$/,'').replace(/_/g,' '))}</div>
            <div style="color:#555; font-size:1em; margin-top:8px;">${escapeAttr(artist)}</div>
            <div style="color:#888; margin-top:6px; font-size:0.95em;">${escapeAttr(date)}</div>
            <div style="color:#aaa; font-size:0.92em; margin-top:8px;">${escapeAttr(profile)}</div>
            <div style="color:#aaa; font-size:0.82em; margin-top:8px;">${escapeAttr(path)}</div>
        `;
        var modal = document.getElementById('selectedWorksModal');
        if (!modal) return;
        document.getElementById('selectedWorksModalImg').src = path;
        document.getElementById('selectedWorksModalImg').alt = desc || '';
        document.getElementById('selectedWorksModalInfo').innerHTML = infoHtml;
        document.getElementById('selectedWorksModalProfileBtn').href = 'profile.php?user=' + encodeURIComponent(profile);
        modal.style.display = 'flex';
    }
}, true);

// small helper used above to escape values inserted into innerHTML
function escapeAttr(s) {
    if (!s && s !== 0) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

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
    
</body>
</html>
