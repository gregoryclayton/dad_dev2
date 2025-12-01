<?php
session_start();
include 'connection.php'; // Include database connection for login/selection logic

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
    // Redirect to the same page to clear POST data and show logged-in state
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// --- Handle Logout ---
if (isset($_GET['logout'])) {
    session_destroy();
    // Redirect to the same profile page without the logout param
    $redirect_url = strtok($_SERVER["REQUEST_URI"], '?');
    if (isset($_GET['user'])) {
        $redirect_url .= '?user=' . urlencode($_GET['user']);
    }
    header("Location: " . $redirect_url);
    exit();
}

// --- API endpoint for selecting a profile ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'select_profile') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['email'])) {
        echo json_encode(['success' => false, 'message' => 'User not logged in.']);
        exit;
    }

    $profileData = isset($_POST['profile_data']) ? json_decode($_POST['profile_data'], true) : null;
    if (!$profileData || !isset($profileData['uuid'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid profile data provided.']);
        exit;
    }

    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
    $userProfilePath = __DIR__ . '/pusers/' . $safe_first . '_' . $safe_last . '/profile.json';

    if (file_exists($userProfilePath)) {
        $profile = json_decode(file_get_contents($userProfilePath), true);
        if (!isset($profile['selected_profiles']) || !is_array($profile['selected_profiles'])) {
            $profile['selected_profiles'] = [];
        }

        $isAlreadySelected = false;
        foreach ($profile['selected_profiles'] as $selected) {
            if (isset($selected['uuid']) && $selected['uuid'] === $profileData['uuid']) {
                $isAlreadySelected = true;
                break;
            }
        }

        if (!$isAlreadySelected) {
            $profile['selected_profiles'][] = $profileData;
            file_put_contents($userProfilePath, json_encode($profile, JSON_PRETTY_PRINT));
        }
        
        echo json_encode(['success' => true, 'message' => 'Profile selection updated.']);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Logged-in user profile not found.']);
        exit;
    }
}


// --- API endpoint for selecting a work ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'select_work') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['email'])) {
        echo json_encode(['success' => false, 'message' => 'User not logged in.']);
        exit;
    }

    $workData = isset($_POST['work_data']) ? json_decode($_POST['work_data'], true) : null;
    if (!$workData || !isset($workData['path'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid work data provided.']);
        exit;
    }

    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
    $userProfilePath = __DIR__ . '/pusers/' . $safe_first . '_' . $safe_last . '/profile.json';

    if (file_exists($userProfilePath)) {
        $profile = json_decode(file_get_contents($userProfilePath), true);
        if (!isset($profile['selected_works']) || !is_array($profile['selected_works'])) {
            $profile['selected_works'] = [];
        }

        $isAlreadySelected = false;
        foreach ($profile['selected_works'] as $selected) {
            if (isset($selected['path']) && $selected['path'] === $workData['path']) {
                $isAlreadySelected = true;
                break;
            }
        }

        if (!$isAlreadySelected) {
            $profile['selected_works'][] = $workData;
            file_put_contents($userProfilePath, json_encode($profile, JSON_PRETTY_PRINT));
        }
        
        echo json_encode(['success' => true, 'message' => 'Work selection updated.']);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Logged-in user profile not found.']);
        exit;
    }
}


function get_profile_data($username) {
    $profile_json_path = "/var/www/html/pusers/" . $username . "/profile.json";
    if (!file_exists($profile_json_path)) return null;
    return json_decode(file_get_contents($profile_json_path), true);
}

// API mode: return JSON for AJAX
if (isset($_GET['api']) && $_GET['api'] === '1' && isset($_GET['user'])) {
    $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_GET['user']);
    $profile = get_profile_data($username);
    header('Content-Type: application/json');
    echo json_encode($profile ? $profile : []);
    exit;
}

// Page mode data preparation
$baseDir = "/var/www/html/pusers";
$userProfiles = [];
$profile_images_map = [];
if (is_dir($baseDir)) {
    $dirs = glob($baseDir . '/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $profilePath = $dir . "/profile.json";
        if (file_exists($profilePath)) {
            $profileData = json_decode(file_get_contents($profilePath), true);
            if ($profileData) {
                $userProfiles[] = $profileData;
                // image map logic
                $safe_first = isset($profileData['first']) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profileData['first']) : '';
                $safe_last = isset($profileData['last']) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profileData['last']) : '';
                $user_folder = $safe_first . "_" . $safe_last;
                $work_dir = $baseDir . '/' . $user_folder . '/work';
                $img = "";
                if (is_dir($work_dir)) {
                    $candidates = glob($work_dir . "/profile_image_*.*");
                    if ($candidates && count($candidates) > 0) {
                        usort($candidates, function($a, $b) { return filemtime($b) - filemtime($a); });
                        $img = str_replace("/var/www/html", "", $candidates[0]);
                    } else {
                        $allImgs = glob($work_dir . "/*.{jpg,jpeg,png,gif}", GLOB_BRACE);
                        if ($allImgs && count($allImgs) > 0) {
                            usort($allImgs, function($a, $b) { return filemtime($b) - filemtime($a); });
                            $img = str_replace("/var/www/html", "", $allImgs[0]);
                        }
                    }
                }
                $profile_images_map[$user_folder] = $img ? [$img] : [];
            }
        }
    }
}

// Get logged-in user's profile for the selection feature
$loggedInUserProfile = null;
if (isset($_SESSION['first']) && isset($_SESSION['last'])) {
    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
    $loggedInUserProfile = get_profile_data($safe_first . '_' . $safe_last);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>User Profile</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
    * { box-sizing: border-box; }
    #mainProfile{max-width:1100px;margin:26px auto;background:#fff;border-radius:12px;box-shadow:0 6px 24px #00000014;padding:20px;color:#222;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial; position: relative;}.main-profile-inner{display:flex;gap:22px;align-items:flex-start;flex-wrap:wrap}.profile-left{flex:0 0 240px;display:flex;flex-direction:column;align-items:center}.main-profile-image{width:240px;height:240px;border-radius:10px;object-fit:cover;background:#f4f4f4;display:block;box-shadow:0 6px 18px #00000014}.profile-placeholder{width:240px;height:240px;border-radius:10px;background:linear-gradient(135deg,#f3f3f5,#e9eef6);display:flex;align-items:center;justify-content:center;font-size:48px;color:#9aa3b2;box-shadow:0 6px 18px #0000000f}.profile-right{flex:1 1 480px;min-width:500px}.profile-header{display:flex;align-items:baseline;gap:12px; flex-wrap: wrap;}.profile-name{font-size:28px;font-weight:700;margin:0}.profile-meta{margin-top:12px;color:#444;line-height:1.5}.profile-meta .label{font-weight:600;color:#333;margin-right:8px}.horizontal-gallery{display:flex;overflow-x:auto;gap:12px;padding-bottom:15px;margin-top:10px}.work-thumb,.audio-thumb{width:140px;height:140px;border-radius:8px;box-shadow:0 4px 12px #0000000f;cursor:pointer;flex-shrink:0}.work-thumb{object-fit:cover;background:#f6f6f6}.audio-thumb{background:#f0f2f5;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:10px;font-size:14px;color:#555}.audio-thumb::before{content:'🎵';font-size:36px;margin-bottom:8px}.gallery-title{margin-top:20px;font-size:1.1em;font-weight:600}
    .signin-bar-container{max-width:1100px;margin:-10px auto 10px auto;padding:10px 20px;background:#fff;border-radius:0 0 12px 12px;box-shadow:0 6px 14px #0000000a;display:flex;justify-content:flex-end;align-items:center;font-size:14px;}
    .signin-bar-container form{display:flex;gap:8px;align-items:center;}
    .signin-bar-container input{padding:6px;border:1px solid #ccc;border-radius:6px;}
    .signin-bar-container button{padding:6px 12px;border:none;background:#e27979;color:white;border-radius:6px;cursor:pointer;}
    .signin-bar-container .welcome-msg a{color:#c0392b;text-decoration:none;margin-left:12px;}
    .user-row{display:flex;flex-direction:column;align-items:flex-start;padding:10px 0;border-bottom:1px solid #eee;cursor:pointer}.user-row:hover{background:#f9f9f9}.user-row-main{display:flex;width:100%;align-items:center;padding:0 10px}.mini-profile{width:40px;height:40px;object-fit:cover;border-radius:8px;margin-right:10px;box-shadow:0 2px 8px #0000001f;flex-shrink:0}.user-name{font-size:14px;font-family:monospace}.user-submeta{color:#666;font-size:.9em;margin-top:4px}.profile-dropdown{display:none;width:100%;padding:15px 10px 0}.dropdown-inner{display:flex;flex-direction:column;gap:15px}.dropdown-header{display:flex;gap:15px;align-items:center}.dropdown-main-image{width:80px;height:80px;border-radius:10px;object-fit:cover;background:#f4f4f4;flex-shrink:0}.dropdown-name{font-size:1.4em;font-weight:700;margin:0}.dropdown-meta{margin-top:8px;color:#555;line-height:1.5;font-size:.9em}.dropdown-gallery-title{margin-top:15px;font-weight:600;font-size:1em}.dropdown-work-gallery{display:flex;overflow-x:auto;gap:10px;padding:5px 0 10px}.dropdown-work-item{display:flex;flex-direction:column;flex-shrink:0;width:120px}.work-image{width:120px;height:120px;object-fit:cover;border-radius:8px;cursor:pointer;box-shadow:0 2px 8px #00000014}.work-info{font-size:.85em;padding-top:6px}.work-info .desc{font-weight:600;color:#333}.work-info .date{color:#777}.dropdown-body{overflow:hidden}
    @media (max-width:760px){
        .main-profile-inner{flex-direction:column;align-items:center}
        .profile-left{flex-basis:auto}
        .profile-right{width:100%}
        .signin-bar-container { flex-direction: column; align-items: stretch; gap: 10px; }
        .signin-bar-container form { flex-direction: column; align-items: stretch; }
        .signin-bar-container .welcome-msg { text-align: center; }
        .container-container { padding-left: 15px; padding-right: 15px; }
        #artistSearchBar { width: 100%; }
    }
    @media (min-width: 600px) { .dropdown-inner { flex-direction: row; } .dropdown-header { flex-basis: 220px; flex-shrink: 0; flex-direction: column; align-items: flex-start; gap: 0; } .dropdown-main-image { width: 120px; height: 120px; } .dropdown-body { min-width: 0; flex-grow: 1; } }
    </style>

    <script>
    var profileImagesMap = <?php echo json_encode($profile_images_map, JSON_UNESCAPED_SLASHES); ?>;
    var isLoggedIn = <?php echo json_encode(isset($_SESSION['email'])); ?>;
    var loggedInUserProfile = <?php echo json_encode($loggedInUserProfile); ?>;
    var userProfiles = <?php echo json_encode($userProfiles, JSON_UNESCAPED_SLASHES); ?>;
    </script>
</head>
<body>

<div id="mainProfile"></div>
    
<div class="navbar">
    <div class="navbarbtns">
         <div class="navbtn"><a href="home.php">home</a></div>
        <div class="navbtn"><a href="register.php">register</a></div>
         <div class="navbtn"><a href="studio3.php">studio</a></div>
        <div class="navbtn"><a href="database.php">database</a></div>
    </div>
</div>

<div class="signin-bar-container">
    <?php if (!isset($_SESSION['email'])): ?>
        <form method="POST">
            <input type="email" name="email" placeholder="email" required>
            <input type="password" name="password"  placeholder="password" required>
            <button name="login">Login</button>
        </form>
    <?php else: ?>
        <div class="welcome-msg">
            Welcome, <strong><?php echo htmlspecialchars($_SESSION['first']); ?></strong>!
            <a href="?logout=1&user=<?php echo urlencode($_GET['user'] ?? ''); ?>">Logout</a>
        </div>
    <?php endif; ?>
</div>



<!-- Content Section (Search, Sort, Profiles) -->
<div class="container-container-container" style="display:grid; align-items:center; justify-items: center; margin-top: 30px;">
<div class="container-container" style="border: double; border-radius:20px; padding: 20px 50px 50px; width:90%; display:grid; background-color: #f2e9e9;">
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


<div id="selectedWorksModal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.85); align-items:center; justify-content:center;">
  <div id="selectedWorksModalContent" style="background:#fff; border-radius:14px; padding:36px 28px; max-width:90vw; max-height:90vh; box-shadow:0 8px 32px #0005; display:flex; flex-direction:column; align-items:center; position:relative;">
    <span id="closeSelectedWorksModal" style="position:absolute; top:16px; right:24px; color:#333; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
    <img id="selectedWorksModalImg" src="" alt="" style="max-width:80vw; max-height:60vh; border-radius:8px; margin-bottom:22px; display:none;">
    <audio id="selectedWorksModalAudio" controls src="" style="width: 80%; max-width: 400px; margin-bottom: 22px; display:none;"></audio>
    <div id="selectedWorksModalInfo" style="text-align:center; width:100%;"></div>
    <a id="selectedWorksModalProfileBtn" href="#" style="display:inline-block; margin-top:18px; background:#e8bebe; color:#000; padding:0.6em 1.2em; border-radius:8px; text-decoration:none;">Visit Artist's Profile</a>
    <div class="like-container" style="position:absolute; bottom:36px; right:28px;">
      <?php if (isset($_SESSION['email'])): ?>
        <input type="radio" name="selectedWorkLike" id="selectedWorkLikeRadio" style="width:20px; height:20px; accent-color:#e27979; cursor:pointer;">
      <?php else: ?>
        <div class="login-to-select" style="display:flex; flex-direction:column; align-items:center; opacity:0.6;">
          <input type="radio" style="width:20px; height:20px; cursor:not-allowed;" disabled>
          <span style="font-size:9px; color:#888; margin-top:4px;">login to select</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div> 

<script>
    function renderMainProfile(profileData) {
        var details = document.getElementById('mainProfile');
        if (!profileData || !profileData.first) {
            details.innerHTML = "<em style='padding:18px; display:block; text-align:center;'>No profile data found.</em>";
            return;
        }

        var safe_first = (profileData.first || '').replace(/[^a-zA-Z0-9_\-\.]/g, '_');
        var safe_last = (profileData.last || '').replace(/[^a-zA-Z0-9_\-\.]/g, '_');
        var profile_username = `${safe_first}_${safe_last}`;
        
        var imgUrl = (profileImagesMap[profile_username] && profileImagesMap[profile_username][0]) || '';
        
        let selectProfileHtml = '';
        if (isLoggedIn) {
            const profileToSelect = {
                uuid: profileData.uuid || '',
                first: profileData.first || '',
                last: profileData.last || ''
            };
            const isSelected = loggedInUserProfile && loggedInUserProfile.selected_profiles && loggedInUserProfile.selected_profiles.some(p => p.uuid === profileData.uuid);
            selectProfileHtml = `<div style="position: absolute; top: 20px; right: 20px;">
                <input type="radio" id="selectProfileRadio" name="select_profile" ${isSelected ? 'checked' : ''} onclick='selectProfile(${JSON.stringify(profileToSelect)})' style="width:20px; height:20px; accent-color:#e27979; cursor:pointer;">
            </div>`;
        }

        var leftHtml = '';
        if (imgUrl) {
            leftHtml = `<div class="profile-left"><img src="${escapeAttr(imgUrl)}" alt="Profile image of ${escapeAttr(profileData.first)}" class="main-profile-image"></div>`;
        } else {
            var initials = ((profileData.first||'').charAt(0) + (profileData.last||'').charAt(0)).toUpperCase();
            leftHtml = `<div class="profile-left"><div class="profile-placeholder">${initials}</div></div>`;
        }

        var rightHtml = '<div class="profile-right">';
        rightHtml += '<div class="profile-header">';
        rightHtml += `<h1 class="profile-name">${escapeHtml(profileData.first)} ${escapeHtml(profileData.last)}</h1>`;
        if (profileData.nickname) rightHtml += `<div style="color:#777;font-size:1.1em; align-self: baseline;">"${escapeHtml(profileData.nickname)}"</div>`;
        
        let locationParts = [];
        if (profileData.city) locationParts.push(escapeHtml(profileData.city));
        if (profileData.country) locationParts.push(escapeHtml(profileData.country));
        if (locationParts.length > 0) rightHtml += `<div style="margin-left:8px;color:#777;font-size:0.95em; flex-basis: 100%;">${locationParts.join(', ')}</div>`;
        
        rightHtml += '</div>';
        rightHtml += '<div class="profile-meta">';
        if (profileData.genre) rightHtml += `<div><span class="label">Genre:</span> ${escapeHtml(profileData.genre)}</div>`;
        if (profileData.subgenre) rightHtml += `<div><span class="label">Subgenre:</span> ${escapeHtml(profileData.subgenre)}</div>`;
        if (profileData.bio) rightHtml += `<div><span class="label">Bio:</span> ${escapeHtml(profileData.bio)}</div>`;
        if (profileData.bio2) rightHtml += `<div><span class="label">Bio 2:</span> ${escapeHtml(profileData.bio2)}</div>`;
        if (profileData.dob) rightHtml += `<div><span class="label">DOB:</span> ${escapeHtml(profileData.dob)}</div>`;

        if (profileData.fact1) rightHtml += `<div><span class="label">Fact 1:</span> ${escapeHtml(profileData.fact1)}</div>`;
        if (profileData.fact2) rightHtml += `<div><span class="label">Fact 2:</span> ${escapeHtml(profileData.fact2)}</div>`;
        if (profileData.fact3) rightHtml += `<div><span class="label">Fact 3:</span> ${escapeHtml(profileData.fact3)}</div>`;

        if (profileData.email) rightHtml += `<div><span class="label">Email:</span> <a href="mailto:${encodeURIComponent(profileData.email)}">${escapeHtml(profileData.email)}</a></div>`;
        rightHtml += '</div>';

        const buildGallery = (title, works) => {
            if (!works || !Array.isArray(works) || works.length === 0) return '';
            let galleryHtml = `<div class="gallery-title">${escapeHtml(title)}</div><div class="horizontal-gallery">`;
            works.forEach(item => {
                const path = item.path || (item.image ? item.image.replace("/var/www/html", "") : '');
                const type = item.type || 'image';
                const title = item.title || item.desc || '';
                const date = item.date || '';
                const artist = item.artist || `${profileData.first} ${profileData.last}`;
                const userFolder = item.user_folder || profile_username;
                
                if (path) {
                    const dataAttrs = `data-path="${escapeAttr(path)}" data-type="${escapeAttr(type)}" data-title="${escapeAttr(title)}" data-date="${escapeAttr(date)}" data-artist="${escapeAttr(artist)}" data-profile="${escapeAttr(userFolder)}"`;
                    if (type === 'audio') {
                        galleryHtml += `<div class="audio-thumb" ${dataAttrs}>${escapeHtml(title)}</div>`;
                    } else {
                        galleryHtml += `<img src="${escapeAttr(path)}" class="work-thumb" ${dataAttrs}>`;
                    }
                }
            });
            return galleryHtml + '</div>';
        };

        rightHtml += buildGallery('My Work', profileData.work);
        rightHtml += buildGallery('Collection', profileData.selected_works);
        rightHtml += '</div>';

        details.innerHTML = `<div class="main-profile-inner">${leftHtml}${rightHtml}</div>` + selectProfileHtml;

        document.querySelectorAll('#mainProfile .work-thumb, #mainProfile .audio-thumb').forEach(thumb => {
            thumb.addEventListener('click', () => openSelectedWorkModal(thumb.dataset));
        });
    }

    function escapeHtml(s) { return String(s || '').replace(/[&<>"']/g, m => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'})[m]); }
    function escapeAttr(s) { return escapeHtml(s); }

    function selectProfile(profileData) {
        if (!isLoggedIn) return;
        const formData = new FormData();
        formData.append('action', 'select_profile');
        formData.append('profile_data', JSON.stringify(profileData));

        fetch('profile.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (loggedInUserProfile.selected_profiles && !loggedInUserProfile.selected_profiles.some(p => p.uuid === profileData.uuid)) {
                    loggedInUserProfile.selected_profiles.push(profileData);
                } else if (!loggedInUserProfile.selected_profiles) {
                    loggedInUserProfile.selected_profiles = [profileData];
                }
            }
        });
    }


    function selectWork(workData) {
        if (!isLoggedIn) return;
        const formData = new FormData();
        formData.append('action', 'select_work');
        formData.append('work_data', JSON.stringify(workData));

        fetch('profile.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (loggedInUserProfile.selected_works && !loggedInUserProfile.selected_works.some(w => w.path === workData.path)) {
                    loggedInUserProfile.selected_works.push(workData);
                }
            }
        });
    }

    function openSelectedWorkModal(workDataset) {
        const modal = document.getElementById('selectedWorksModal');
        const imgEl = document.getElementById('selectedWorksModalImg');
        const audioEl = document.getElementById('selectedWorksModalAudio');
        const infoEl = document.getElementById('selectedWorksModalInfo');
        const profileBtn = document.getElementById('selectedWorksModalProfileBtn');
        const radio = document.getElementById('selectedWorkLikeRadio');

        imgEl.style.display = 'none'; audioEl.style.display = 'none';
        audioEl.pause(); audioEl.src = '';
        
        if (workDataset.type === 'audio') {
            audioEl.src = workDataset.path || ''; audioEl.style.display = 'block';
        } else {
            imgEl.src = workDataset.path || ''; imgEl.alt = workDataset.title || 'Artwork'; imgEl.style.display = 'block';
        }
        
        infoEl.innerHTML = `<div style="font-weight:bold;font-size:1.1em;">${escapeHtml(workDataset.title)}</div><div style="color:#666;margin-top:6px;">by ${escapeHtml(workDataset.artist)}</div>${workDataset.date ? `<div style="color:#888;margin-top:6px;">${escapeHtml(workDataset.date)}</div>` : ''}`;
        
        if (profileBtn && workDataset.profile) {
            profileBtn.href = 'profile.php?user=' + encodeURIComponent(workDataset.profile);
            profileBtn.style.display = 'inline-block';
        } else {
            profileBtn.style.display = 'none';
        }

        if (radio && isLoggedIn && loggedInUserProfile) {
            radio.checked = loggedInUserProfile.selected_works?.some(sw => sw.path === workDataset.path) || false;
            radio.onclick = () => selectWork(workDataset);
        }

        modal.style.display = 'flex';
    }

    function buildDropdownContent(container, profileData, profile_username, imgSrc) {
        let bioHtml = profileData.bio ? `<div><strong>Bio:</strong> ${escapeHtml(profileData.bio)}</div>` : '';
        let workHtml = '';
        if (profileData.work && Array.isArray(profileData.work) && profileData.work.length > 0) {
            workHtml += '<div class="dropdown-gallery-title">Work</div><div class="dropdown-work-gallery">';
            profileData.work.forEach(function(work_item) {
                var workImgSrc = work_item.image ? work_item.image.replace("/var/www/html", "") : '';
                if(workImgSrc) {
                    const dataAttrs = `data-path="${escapeAttr(workImgSrc)}" data-type="image" data-title="${escapeAttr(work_item.desc || '')}" data-date="${escapeAttr(work_item.date || '')}" data-artist="${escapeAttr((profileData.first || '') + ' ' + (profileData.last || ''))}" data-profile="${escapeAttr(profile_username)}"`;
                    workHtml += `<div class="dropdown-work-item">
                                    <img src="${escapeAttr(workImgSrc)}" class="work-image" ${dataAttrs}>
                                    <div class="work-info">
                                        <div class="desc">${escapeHtml(work_item.desc || '')}</div>
                                        <div class="date">${escapeHtml(work_item.date || '')}</div>
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
                    <div class="dropdown-name">${escapeHtml(profileData.first)} ${escapeHtml(profileData.last)}</div>
                    <button class="profile-btn" style="margin-top:10px;" onclick="event.stopPropagation(); window.location.href='profile.php?user=${encodeURIComponent(profile_username)}'">Visit Full Profile</button>
                </div>
            </div>
            <div class="dropdown-body">
                <div class="dropdown-meta">${bioHtml}</div>
                ${workHtml}
            </div>
        </div>`;
    }

    function renderProfiles(profiles) {
        var container = document.getElementById('user-profiles');
        container.innerHTML = '';
        profiles.forEach(function(profileData) {
            var safe_first = (profileData.first || '').replace(/[^a-zA-Z0-9_\-\.]/g, '_');
            var safe_last = (profileData.last || '').replace(/[^a-zA-Z0-9_\-\.]/g, '_');
            var profile_username = `${safe_first}_${safe_last}`;
            var miniSrc = (profileImagesMap[profile_username] && profileImagesMap[profile_username][0]) || '';
            var submetaParts = [];
            if (profileData.dob) submetaParts.push(`Born: ${profileData.dob.substring(0,4)}`);
            if (profileData.country) submetaParts.push(escapeAttr(profileData.country));
            if (profileData.genre) submetaParts.push(escapeAttr(profileData.genre));

            var row = document.createElement('div');
            row.className = 'user-row';
            row.innerHTML = `<div class="user-row-main">
                ${miniSrc ? `<img src="${escapeAttr(miniSrc)}" alt="${escapeAttr(profile_username)} photo" class="mini-profile">` : '<div class="mini-profile" style="background:#e9eef6;"></div>'}
                <div>
                    <div class="user-name">${escapeHtml(profileData.first)} ${escapeHtml(profileData.last)}</div>
                    <div class="user-submeta">${submetaParts.join(' &bull; ')}</div>
                </div>
            </div>
            <div class="profile-dropdown"></div>`;

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

    function searchProfiles() {
        var search = (document.getElementById('artistSearchBar').value || "").toLowerCase();
        if (!search) { renderProfiles(userProfiles); return; }
        var filtered = userProfiles.filter(p => 
            (p.first && p.first.toLowerCase().includes(search)) ||
            (p.last && p.last.toLowerCase().includes(search)) ||
            (p.country && p.country.toLowerCase().includes(search)) ||
            (p.genre && p.genre.toLowerCase().includes(search))
        );
        renderProfiles(filtered);
    }
    
    // --- Main DOMContentLoaded ---
    document.addEventListener("DOMContentLoaded", function() {
        var params = new URLSearchParams(window.location.search);
        var username = params.get('user');
        if (username) {
            fetch(`profile.php?api=1&user=${encodeURIComponent(username)}`)
                .then(res => res.json())
                .then(renderMainProfile)
                .catch(err => {
                    console.error("Failed to load main profile:", err);
                    document.getElementById('mainProfile').innerHTML = "<em style='padding:18px; display:block; text-align:center;'>Error loading profile.</em>";
                });
        } else {
             document.getElementById('mainProfile').style.display = 'none';
        }
        
        var modal = document.getElementById('selectedWorksModal');
        var closeBtn = document.getElementById('closeSelectedWorksModal');
        if(closeBtn) closeBtn.onclick = () => { modal.style.display = 'none'; document.getElementById('selectedWorksModalAudio').pause(); };
        if(modal) modal.onclick = (e) => { if (e.target === modal) { modal.style.display = 'none'; document.getElementById('selectedWorksModalAudio').pause(); } };
        
        // --- Init Profile List ---
        renderProfiles(userProfiles);
        document.getElementById('artistSearchBar').addEventListener('input', searchProfiles);
        document.getElementById('sortAlphaBtn').addEventListener('click', function() {
            var sorted = userProfiles.slice().sort(function(a, b) {
                var nameA = ((a.first || "") + " " + (a.last || "")).toLowerCase();
                var nameB = ((b.first || "") + " " + (b.last || "")).toLowerCase();
                return nameA.localeCompare(nameB);
            });
            renderProfiles(sorted);
        });
        document.getElementById('sortDateBtn').addEventListener('click', function() {
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
        document.getElementById('sortCountryBtn').addEventListener('click', function() {
            var sorted = userProfiles.slice().sort(function(a, b) {
                var countryA = (a.country || "").toLowerCase();
                var countryB = (b.country || "").toLowerCase();
                if (!countryA && !countryB) return 0;
                if (!countryA) return 1;
                if (!countryB) return -1;
                return countryA.localeCompare(countryB);
            });
            renderProfiles(sorted);
        });
        document.getElementById('sortGenreBtn').addEventListener('click', function() {
            var sorted = userProfiles.slice().sort(function(a, b) {
                var genreA = (a.genre || "").toLowerCase();
                var genreB = (b.genre || "").toLowerCase();
                if (!genreA && !genreB) return 0;
                if (!genreA) return 1;
                if (!genreB) return -1;
                return genreA.localeCompare(genreB);
            });
            renderProfiles(sorted);
        });
        // Event delegation for work images inside dropdowns
        document.getElementById('user-profiles').addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('work-image')) {
                openSelectedWorkModal(e.target.dataset);
            }
        });
    });
</script>

</body>
</html>
