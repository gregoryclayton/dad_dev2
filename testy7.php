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
        width:42px;
        height:42px;
        object-fit:cover;
        border-radius:6px;
        margin-right:10px;
        vertical-align:middle;
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
      .user-name { font-weight:600; }
    </style>
</head>
<body>

<!-- Header/Navigation -->
<div style="display:flex;">
    <div class="title-container" id="mainTitleContainer" style="background-image: linear-gradient(135deg, #e27979 60%, #ed8fd1 100%);">
        <br>
        <a href="index.php" style="text-decoration:none; color: white;">digital <br>artist <br>database</a>
    </div>
</div>
<div class="navbar">
    <div class="navbarbtns">
        <div><a class="navbtn" href="register.php">[register]</a></div>
        <div><a class="navbtn" href="studio3.php">[studio]</a></div>
        <div><a class="navbtn" href="database.php">[database]</a></div>
    </div>
</div>

<?php if (!isset($_SESSION['email'])): ?>
<form method="POST" style="display:flex; max-width:80vw; justify-content: flex-end; padding:10px;">
    <input style="width:80px" type="email" name="email" placeholder="email" required>
    <input style="width:80px" type="password" name="password"  placeholder="password" required>
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
            <button id="next-btn" style="position:absolute; right:-48px; top:50%; transform:translateY(-50%); background:#fff; border:none; border-radius:50%; width:38px; height:38px; font-size:1.7em; cursor:pointer;">&#8680;</button>
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
// --- Basic Gallery/Profiles Render and Search/Sort ---
function renderProfiles(profiles) {
    var container = document.getElementById('user-profiles');
    container.innerHTML = '';
    profiles.forEach(function(profileData, idx) {
        var safe_first = profileData.first ? profileData.first.replace(/[^a-zA-Z0-9_\-\.]/g, '_') : '';
        var safe_last = profileData.last ? profileData.last.replace(/[^a-zA-Z0-9_\-\.]/g, '_') : '';
        var profile_username = safe_first + "_" + safe_last;

        // Determine mini-profile image:
        // prefer a profile_image in work entries, otherwise first work image, otherwise empty
        var miniSrc = "";
        if (Array.isArray(profileData.work) && profileData.work.length > 0) {
            // try to find a work image that looks like a profile image first
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
        var imgHTML = '';
        if (miniSrc) {
            var img = document.createElement('img');
            img.className = 'mini-profile';
            img.src = miniSrc;
            img.alt = profile_username + ' photo';
            row.appendChild(img);
        } else {
            // placeholder blank box for alignment (keeps rows aligned)
            var placeholder = document.createElement('div');
            placeholder.style.width = '42px';
            placeholder.style.height = '42px';
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
        var html = '';
        if (profileData.bio) html += "<div><strong>Bio:</strong> " + profileData.bio + "</div>";
        if (profileData.dob) html += "<div><strong>DOB:</strong> " + profileData.dob + "</div>";
        if (profileData.country) html += "<div><strong>Country:</strong> " + profileData.country + "</div>";
        if (profileData.genre) html += "<div><strong>Genre:</strong> " + profileData.genre + "</div>";
        if (profileData.work && Array.isArray(profileData.work) && profileData.work.length > 0) {
            html += "<div style='margin-top:6px;'><strong>Work:</strong></div><ul style='margin:6px 0 0 16px; padding:0;'>";
            profileData.work.forEach(function(work_item){
                html += "<li style='margin-bottom:6px; list-style:disc;'>";
                if (work_item.desc) html += "<div style='font-weight:600;'>" + work_item.desc + "</div>";
                if (work_item.date) html += "<div style='color:#666; font-size:0.9em;'>"+work_item.date+"</div>";
                html += "</li>";
            });
            html += "</ul>";
        }
        html += '<div style="margin-top:8px;"><button class="profile-btn" onclick="window.location.href=\'profile.php?user=' + profile_username + '\'">Profile Page</button></div>';

        details.innerHTML = html;
        nameDiv.appendChild(details);

        // toggle on row click (except when clicking the profile button)
        row.addEventListener('click', function(e){
            if (e.target && e.target.classList && e.target.classList.contains('profile-btn')) return;
            details.style.display = details.style.display === 'block' ? 'none' : 'block';
        });

        row.appendChild(nameDiv);
        container.appendChild(row);
    });
}
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
</script>
</body>
</html>
