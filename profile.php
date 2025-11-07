<?php
session_start();

// Database credentials and connection
include 'connection.php';
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
            $new_selection = [
                'path' => $workData['path'],
                'title' => $workData['title'] ?? '',
                'date' => $workData['date'] ?? '',
                'artist' => $workData['artist'] ?? '',
                'user_folder' => $workData['profile'] ?? '',
                'timestamp' => date('c')
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

// --- Page mode data preparation ---
$baseDir = "/var/www/html/pusers";
$userProfiles = [];
$profile_images_map = [];
$loggedInUser_profile = null;

// Check if user is logged in
$is_logged_in = isset($_SESSION['email']);
if ($is_logged_in) {
    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
    $loggedInUser_profile = get_profile_data($safe_first . '_' . $safe_last);
}


if (is_dir($baseDir)) {
    $dirs = glob($baseDir . '/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $profilePath = $dir . "/profile.json";
        if (file_exists($profilePath)) {
            $profileData = json_decode(file_get_contents($profilePath), true);
            if ($profileData) {
                $userProfiles[] = $profileData;
                // --- Build profile image map ---
                $safe_first = isset($profileData['first']) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profileData['first']) : '';
                $safe_last = isset($profileData['last']) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profileData['last']) : '';
                $user_folder = $safe_first . "_" . $safe_last;
                $work_dir = $baseDir . '/' . $user_folder . '/work';
                $img = "";
                if (is_dir($work_dir)) {
                    $candidates = glob($work_dir . "/profile_image_*.*");
                    if ($candidates && count($candidates) > 0) {
                        usort($candidates, fn($a, $b) => filemtime($b) <=> filemtime($a));
                        $img = str_replace("/var/www/html", "", $candidates[0]);
                    } else {
                        $allImgs = glob($work_dir . "/*.{jpg,jpeg,png,gif}", GLOB_BRACE);
                        if ($allImgs && count($allImgs) > 0) {
                            usort($allImgs, fn($a, $b) => filemtime($b) <=> filemtime($a));
                            $img = str_replace("/var/www/html", "", $allImgs[0]);
                        }
                    }
                }
                $profile_images_map[$user_folder] = $img ? [$img] : [];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Profile</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
    #mainProfile{max-width:1100px;margin:26px auto;background:#fff;border-radius:12px;box-shadow:0 6px 24px #00000014;padding:20px;color:#222;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial}.main-profile-inner{display:flex;gap:22px;align-items:flex-start;flex-wrap:wrap}.profile-left{flex:0 0 240px;display:flex;flex-direction:column;align-items:center}.main-profile-image{width:240px;height:240px;border-radius:10px;object-fit:cover;background:#f4f4f4;display:block;box-shadow:0 6px 18px #00000014}.profile-placeholder{width:240px;height:240px;border-radius:10px;background:linear-gradient(135deg,#f3f3f5,#e9eef6);display:flex;align-items:center;justify-content:center;font-size:48px;color:#9aa3b2;box-shadow:0 6px 18px #0000000f}.profile-right{flex:1 1 480px;min-width:260px}.profile-header{display:flex;align-items:baseline;gap:12px}.profile-name{font-size:28px;font-weight:700;margin:0}.profile-meta{margin-top:12px;color:#444;line-height:1.5}.profile-meta .label{font-weight:600;color:#333;margin-right:8px}.horizontal-gallery{display:flex;overflow-x:auto;gap:12px;padding-bottom:15px;margin-top:10px}.work-thumb,.audio-thumb{width:140px;height:140px;border-radius:8px;box-shadow:0 4px 12px #0000000f;cursor:pointer;flex-shrink:0}.work-thumb{object-fit:cover;background:#f6f6f6}.audio-thumb{background:#f0f2f5;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:10px;font-size:14px;color:#555}.audio-thumb::before{content:'🎵';font-size:36px;margin-bottom:8px}.gallery-title{margin-top:20px;font-size:1.1em;font-weight:600}@media (max-width:760px){.main-profile-inner{flex-direction:column;align-items:center}.profile-left{flex-basis:auto}.profile-right{width:100%}}
    </style>
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

<div id="selectedWorksModal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.85); align-items:center; justify-content:center;">
  <div id="selectedWorksModalContent" style="background:#fff; border-radius:14px; padding:36px 28px; max-width:90vw; max-height:90vh; box-shadow:0 8px 32px #0005; display:flex; flex-direction:column; align-items:center; position:relative;">
    <span id="closeSelectedWorksModal" style="position:absolute; top:16px; right:24px; color:#333; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
    <img id="selectedWorksModalImg" src="" alt="" style="max-width:80vw; max-height:60vh; border-radius:8px; margin-bottom:22px; display:none;">
    <audio id="selectedWorksModalAudio" controls src="" style="width: 80%; max-width: 400px; margin-bottom: 22px; display:none;"></audio>
    <div id="selectedWorksModalInfo" style="text-align:center; width:100%;"></div>
    <a id="selectedWorksModalProfileBtn" href="#" style="display:inline-block; margin-top:18px; background:#e8bebe; color:#000; padding:0.6em 1.2em; border-radius:8px; text-decoration:none;">Visit Artist's Profile</a>
    <div class="like-container" style="position:absolute; bottom:36px; right:28px;">
      <?php if ($is_logged_in): ?>
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
    var profileImagesMap = <?php echo json_encode($profile_images_map, JSON_UNESCAPED_SLASHES); ?>;
    var isLoggedIn = <?php echo json_encode($is_logged_in); ?>;
    var loggedInUserProfile = <?php echo json_encode($loggedInUser_profile); ?>;

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
        
        var leftHtml = imgUrl 
            ? `<div class="profile-left"><img src="${escapeAttr(imgUrl)}" alt="Profile image of ${escapeAttr(profileData.first)}" class="main-profile-image"></div>`
            : `<div class="profile-left"><div class="profile-placeholder">${((profileData.first||'').charAt(0) + (profileData.last||'').charAt(0)).toUpperCase()}</div></div>`;

        var rightHtml = '<div class="profile-right">';
        rightHtml += `<div class="profile-header"><h1 class="profile-name">${escapeHtml(profileData.first)} ${escapeHtml(profileData.last)}</h1>`;
        if (profileData.country) rightHtml += `<div style="margin-left:8px;color:#777;font-size:0.95em;">${escapeHtml(profileData.country)}</div>`;
        rightHtml += '</div><div class="profile-meta">';
        if (profileData.bio) rightHtml += `<div><span class="label">Bio:</span> ${escapeHtml(profileData.bio)}</div>`;
        if (profileData.dob) rightHtml += `<div><span class="label">DOB:</span> ${escapeHtml(profileData.dob)}</div>`;
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
                    galleryHtml += (type === 'audio')
                        ? `<div class="audio-thumb" ${dataAttrs}>${escapeHtml(title)}</div>`
                        : `<img src="${escapeAttr(path)}" class="work-thumb" ${dataAttrs}>`;
                }
            });
            return galleryHtml + '</div>';
        };

        rightHtml += buildGallery('My Work', profileData.work);
        rightHtml += buildGallery('Collection', profileData.selected_works);
        rightHtml += '</div>';

        details.innerHTML = `<div class="main-profile-inner">${leftHtml}${rightHtml}</div>`;
        document.querySelectorAll('#mainProfile .work-thumb, #mainProfile .audio-thumb').forEach(thumb => {
            thumb.addEventListener('click', () => openSelectedWorkModal(thumb.dataset));
        });
    }

    function escapeHtml(s) { return String(s || '').replace(/[&<>"']/g, m => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'})[m]); }
    function escapeAttr(s) { return escapeHtml(s); }

    function selectWork(workData) {
        if (!isLoggedIn) { return; }
        const formData = new FormData();
        formData.append('action', 'select_work');
        formData.append('work_data', JSON.stringify(workData));

        fetch('profile.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                console.log("Work selected!");
                if (loggedInUserProfile && loggedInUserProfile.selected_works && !loggedInUserProfile.selected_works.find(w => w.path === workData.path)) {
                    loggedInUserProfile.selected_works.push(workData);
                }
            } else {
                console.error("Failed to select work:", data.message);
            }
        })
        .catch(err => console.error("Error selecting work:", err));
    }

    function openSelectedWorkModal(work) {
        var modal = document.getElementById('selectedWorksModal');
        var imgEl = document.getElementById('selectedWorksModalImg');
        var audioEl = document.getElementById('selectedWorksModalAudio');
        var infoEl = document.getElementById('selectedWorksModalInfo');
        var profileBtn = document.getElementById('selectedWorksModalProfileBtn');
        var radio = document.getElementById('selectedWorkLikeRadio');

        imgEl.style.display = 'none'; audioEl.style.display = 'none';

        if (work.type === 'audio') {
            audioEl.src = work.path || ''; audioEl.style.display = 'block';
        } else {
            imgEl.src = work.path || ''; imgEl.alt = work.title || 'Artwork'; imgEl.style.display = 'block';
        }
        
        infoEl.innerHTML = `<div style="font-weight:bold;font-size:1.1em;">${escapeHtml(work.title)}</div><div style="color:#666;margin-top:6px;">by ${escapeHtml(work.artist)}</div>${work.date ? `<div style="color:#888;margin-top:6px;">${escapeHtml(work.date)}</div>` : ''}`;
        
        if (profileBtn) {
            work.profile ? (profileBtn.href = 'profile.php?user=' + encodeURIComponent(work.profile), profileBtn.style.display = 'inline-block') : profileBtn.style.display = 'none';
        }

        if (isLoggedIn && radio) {
            radio.checked = loggedInUserProfile.selected_works?.some(w => w.path === work.path) || false;
            radio.onclick = () => selectWork(work);
        }
        modal.style.display = 'flex';
    }

    document.addEventListener("DOMContentLoaded", function() {
        var params = new URLSearchParams(window.location.search);
        var username = params.get('user');
        if (username) {
            fetch(`profile.php?api=1&user=${encodeURIComponent(username)}`).then(res => res.json()).then(renderMainProfile).catch(err => {
                console.error("Failed to load profile:", err);
                document.getElementById('mainProfile').innerHTML = "<em style='padding:18px; display:block; text-align:center;'>Error loading profile.</em>";
            });
        } else {
            document.getElementById('mainProfile').innerHTML = "<em style='padding:18px; display:block; text-align:center;'>No profile selected.</em>";
        }
        
        var modal = document.getElementById('selectedWorksModal');
        var closeBtn = document.getElementById('closeSelectedWorksModal');
        if(closeBtn) closeBtn.onclick = () => { modal.style.display = 'none'; audioEl.pause(); };
        if(modal) modal.onclick = (e) => { if (e.target === modal) { modal.style.display = 'none'; document.getElementById('selectedWorksModalAudio').pause(); } };
    });
</script>

</body>
</html>
