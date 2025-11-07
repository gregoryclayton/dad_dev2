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
     
    </style>
</head>
<body>

<div style="display:flex;">
  <div class="title-container" id="mainTitleContainer">
    <br>
    <a href="index.php">digital <br>artist <br>database</a>
  </div>
  
   <div id="dotMenuContainer">
    <div id="dot"></div>
    <div id="dotMenu">
      <!-- Your menu content here -->
     <!-- Add this play icon to the dot menu container -->
      <div id="musicPlayIcon">
        <span>▶</span>
      </div>
      <!-- New buttons for changing color -->
      <div id="musicBtnContainer">
          <button id="musicBtn" title="Toggle Music"></button>
          <div id="musicPlayIcon">
            <span>▶</span>
          </div>
      </div>
      <button id="changeTitleBgBtn"></button>
      <button id="bwThemeBtn"></button>
    </div>
  </div>
  
</div>


<!-- Pop-out menu for quick nav, hidden by default -->
<div id="titleMenuPopout">
  <div class="title-menu-links">
    <a href="v4.5.php">home</a>
    <a href="v4.5.php">about</a>
       <a href="studio.php">studio</a>
    <a href="signup.php">register</a>
    <a href="database.php">database</a>
   
   
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


<form method="POST">
    <input type="email" name="email" placeholder="email" required><br>
    <input type="password" name="password"  placeholder="password" required><br>
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
<div id="slideModal">
  <div>
    <button id="closeSlideModal">×</button>
    <img id="modalImage" src="" alt="Artwork">
    <div>
        <div id="modalArtist"></div>
        <div id="modalTitle"></div>
        <div id="modalDate"></div>
        <button id="visitProfileBtn">visit profile</button>
    </div>
    <div class="like-container">
      <?php if (isset($_SESSION['email'])): ?>
        <input type="radio" name="slideModalLike" id="slideModalLikeRadio">
      <?php else: ?>
        <div class="login-to-select">
          <input type="radio" disabled>
          <span>login to select</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Top selected works gallery -->
<div id="selectedWorksGallery">
<?php foreach ($topWorks as $i => $workPath):
    $work = $workDetails[$workPath];
    ?>
    <div class="selected-work-card" data-idx="<?php echo $i; ?>">
        <img src="<?php echo htmlspecialchars($work['path']); ?>" alt="<?php echo htmlspecialchars($work['title']); ?>">
        <div><?php echo htmlspecialchars($work['title']); ?></div>
    </div>
<?php endforeach; ?>
</div>

<!-- Modal for selected works gallery -->
<div id="selectedWorksModal">
  <div id="selectedWorksModalContent">
    <span id="closeSelectedWorksModal">&times;</span>
    <img id="selectedWorksModalImg" src="" alt="">
    <div id="selectedWorksModalInfo"></div>
    <a id="selectedWorksModalProfileBtn" href="#">Visit profile</a>
    <div class="like-container">
      <?php if (isset($_SESSION['email'])): ?>
        <input type="radio" name="selectedWorkLike" id="selectedWorkLikeRadio">
      <?php else: ?>
        <div class="login-to-select">
          <input type="radio" disabled>
          <span>login to select</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Content Section (Search, Sort, Profiles) -->
<div class="container-container-container">
<div class="container-container">
  <div class="search-container">
    <div>
      <input type="text" id="artistSearchBar" placeholder="Search artists...">
    </div>
  </div>
  <div class="sort-container">
    <button id="sortAlphaBtn">name</button>
    <button id="sortDateBtn">date</button>
    <button id="sortCountryBtn">country</button>
    <button id="sortGenreBtn">genre</button>
  </div>
  <div id="user-profiles"></div>
</div>
</div>

<footer>
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
                    <button class="profile-btn" style="margin-top:10px;" onclick="event.stopPropagation(); window.location.href='profile.php?user=${encodeURIComponent(profile_username)}'">Visit Full P[...]
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
var slideshowContainer = document.getElementById('slideshow-container');
if (slideshowContainer) {
    slideshowContainer.addEventListener('click', function(e) {
        var rect = e.target.getBoundingClientRect();
        var x = e.clientX - rect.left;
        
        // If the click is in the middle 60% of the container, open modal
        if (x > rect.width * 0.2 && x < rect.width * 0.8) {
            openModalForSlideshow(ssIdx);
        }
    });
}
if (document.getElementById('slideshow-next-zone')) {
    document.getElementById('slideshow-next-zone').onclick = function(){ nextSS(); startSSAuto(); };
}
if (document.getElementById('slideshow-prev-zone')) {
    document.getElementById('slideshow-prev-zone').onclick = function(){ prevSS(); startSSAuto(); };
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
            var w = profile.work.find(function(w) { if (!w.image) return false; return w.image.endsWith(imgfile) || w.image.indexOf(imgfile)!==-1; });
            workData.title = w && w.desc ? w.desc : "";
            workData.date = w && w.date ? w.date : "";
        }
        modalArtist.textContent = workData.artist;
        if (visitProfileBtn) visitProfileBtn.onclick = function() { window.location.href = "profile.php?user="+encodeURIComponent(workData.user_folder);};
    } else {
        if (modalArtist) modalArtist.textContent = "";
        if (visitProfileBtn) visitProfileBtn.onclick = function() { };
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
