<?php
session_start();

// --- Handle select_work API (POST) for selecting a work into logged-in user's profile.json ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'select_work') {
    header('Content-Type: application/json');

    if (!isset($_SESSION['first']) || !isset($_SESSION['last'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in.']);
        exit;
    }

    $workData = isset($_POST['work_data']) ? json_decode($_POST['work_data'], true) : null;
    if (!$workData || !isset($workData['path'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid work data.']);
        exit;
    }

    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
    $safe_last  = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
    $userProfilePath = __DIR__ . '/pusers/' . $safe_first . '_' . $safe_last . '/profile.json';

    if (!file_exists($userProfilePath)) {
        echo json_encode(['success' => false, 'message' => 'User profile not found.']);
        exit;
    }

    $profile = json_decode(file_get_contents($userProfilePath), true);
    if (!isset($profile['selected_works']) || !is_array($profile['selected_works'])) {
        $profile['selected_works'] = [];
    }

    $isAlreadySelected = false;
    foreach ($profile['selected_works'] as $sel) {
        if (isset($sel['path']) && $sel['path'] === $workData['path']) {
            $isAlreadySelected = true;
            break;
        }
    }

    if (!$isAlreadySelected) {
        $new = [
            'path' => $workData['path'],
            'title' => $workData['title'] ?? '',
            'date' => $workData['date'] ?? '',
            'artist' => $workData['artist'] ?? '',
            'user_folder' => $workData['profile'] ?? ($workData['user_folder'] ?? ''),
            'timestamp' => date('c')
        ];
        $profile['selected_works'][] = $new;
        file_put_contents($userProfilePath, json_encode($profile, JSON_PRETTY_PRINT));
    }

    echo json_encode(['success' => true, 'message' => 'Work selection updated.']);
    exit;
}

// --- Page rendering mode below ---

function get_profile_data($username) {
    $profile_json_path = __DIR__ . "/pusers/" . $username . "/profile.json";
    if (!file_exists($profile_json_path)) return null;
    return json_decode(file_get_contents($profile_json_path), true);
}

// Build profile images map and gather profiles
$baseDir = __DIR__ . '/pusers';
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
                $safe_first = isset($profileData['first']) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profileData['first']) : '';
                $safe_last  = isset($profileData['last'])  ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profileData['last']) : '';
                $user_folder = $safe_first . '_' . $safe_last;
                $work_dir = $baseDir . '/' . $user_folder . '/work';
                $img = "";
                if (is_dir($work_dir)) {
                    $candidates = glob($work_dir . "/profile_image_*.*");
                    if ($candidates && count($candidates) > 0) {
                        usort($candidates, function($a,$b){ return filemtime($b) - filemtime($a); });
                        $img = str_replace("/var/www/html", "", $candidates[0]);
                    } else {
                        $allImgs = glob($work_dir . "/*.{jpg,jpeg,png,gif}", GLOB_BRACE);
                        if ($allImgs && count($allImgs) > 0) {
                            usort($allImgs, function($a,$b){ return filemtime($b) - filemtime($a); });
                            $img = str_replace("/var/www/html", "", $allImgs[0]);
                        }
                    }
                }
                $profile_images_map[$user_folder] = $img ? [$img] : [];
            }
        }
    }
}

// If logged in, load the logged-in user's profile to enable selection checks
$is_logged_in = isset($_SESSION['email']);
$loggedInUserProfile = null;
if ($is_logged_in) {
    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
    $safe_last  = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
    $loggedInUserProfile = get_profile_data($safe_first . '_' . $safe_last);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Profile</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
    /* Styles kept concise to match site */
    #mainProfile{max-width:1100px;margin:26px auto;background:#fff;border-radius:12px;box-shadow:0 6px 24px rgba(0,0,0,0.08);padding:20px;color:#222;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial}.main-profile-inner{display:flex;gap:22px;align-items:flex-start;flex-wrap:wrap}.profile-left{flex:0 0 240px;display:flex;flex-direction:column;align-items:center}.main-profile-image{width:240px;height:240px;border-radius:10px;object-fit:cover;background:#f4f4f4;display:block;box-shadow:0 6px 18px rgba(0,0,0,0.08)}.profile-placeholder{width:240px;height:240px;border-radius:10px;background:linear-gradient(135deg,#f3f3f5,#e9eef6);display:flex;align-items:center;justify-content:center;font-size:48px;color:#9aa3b2;box-shadow:0 6px 18px rgba(0,0,0,0.06)}.profile-right{flex:1 1 480px;min-width:260px}.profile-header{display:flex;align-items:baseline;gap:12px}.profile-name{font-size:28px;font-weight:700;margin:0}.profile-meta{margin-top:12px;color:#444;line-height:1.5}.profile-meta .label{font-weight:600;color:#333;margin-right:8px}.horizontal-gallery{display:flex;overflow-x:auto;gap:12px;padding-bottom:15px;margin-top:10px}.work-thumb,.audio-thumb{width:140px;height:140px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.06);cursor:pointer;flex-shrink:0}.work-thumb{object-fit:cover;background:#f6f6f6}.audio-thumb{background:#f0f2f5;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:10px;font-size:14px;color:#555}.audio-thumb::before{content:'🎵';font-size:36px;margin-bottom:8px}.gallery-title{margin-top:20px;font-size:1.1em;font-weight:600}@media(max-width:760px){.main-profile-inner{flex-direction:column;align-items:center}.profile-left{flex-basis:auto}.profile-right{width:100%}}
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

<!-- Modal with radio selector in bottom-right -->
<div id="selectedWorksModal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.85); align-items:center; justify-content:center;">
  <div id="selectedWorksModalContent" style="background:#fff; border-radius:14px; padding:36px 28px; max-width:90vw; max-height:90vh; box-shadow:0 8px 32px #0005; display:flex; flex-direction:column; align-items:center; position:relative;">
    <span id="closeSelectedWorksModal" style="position:absolute; top:16px; right:24px; color:#333; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
    <img id="selectedWorksModalImg" src="" alt="" style="max-width:80vw; max-height:60vh; border-radius:8px; margin-bottom:22px; display:none;">
    <audio id="selectedWorksModalAudio" controls src="" style="width: 80%; max-width: 400px; margin-bottom: 22px; display:none;"></audio>
    <div id="selectedWorksModalInfo" style="text-align:center; width:100%;"></div>
    <a id="selectedWorksModalProfileBtn" href="#" style="display:inline-block; margin-top:18px; background:#e8bebe; color:#000; padding:0.6em 1.2em; border-radius:8px; text-decoration:none;">Visit Artist's Profile</a>

    <div class="like-container" style="position:absolute; bottom:24px; right:28px;">
      <?php if ($is_logged_in): ?>
        <input type="radio" name="selectedWorkLike" id="selectedWorkLikeRadio" style="width:20px; height:20px; accent-color:#e27979; cursor:pointer;">
      <?php else: ?>
        <div class="login-to-select" style="display:flex; flex-direction:column; align-items:center; opacity:0.6;">
          <input type="radio" disabled style="width:20px; height:20px;">
          <span style="font-size:9px; color:#888; margin-top:4px;">login to select</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
    // Expose server data to JS
    var profileImagesMap = <?php echo json_encode($profile_images_map, JSON_UNESCAPED_SLASHES); ?>;
    var userProfiles = <?php echo json_encode($userProfiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>;
    var isLoggedIn = <?php echo json_encode($is_logged_in ? true : false); ?>;
    var loggedInUserProfile = <?php echo json_encode($loggedInUserProfile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>;

    function escapeHtml(s) {
        if (!s && s !== 0) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }
    function escapeAttr(s) { return escapeHtml(s); }

    function renderMainProfile(profileData) {
        var details = document.getElementById('mainProfile');
        if (!profileData || !profileData.first) {
            details.innerHTML = "<em style='padding:18px; display:block; text-align:center;'>No profile data found.</em>";
            return;
        }

        var safe_first = (profileData.first || '').replace(/[^a-zA-Z0-9_\-\.]/g, '_');
        var safe_last = (profileData.last || '').replace(/[^a-zA-Z0-9_\-\.]/g, '_');
        var profile_username = safe_first + '_' + safe_last;
        var imgUrl = (profileImagesMap[profile_username] && profileImagesMap[profile_username][0]) || '';

        var leftHtml = '';
        if (imgUrl) {
            leftHtml = '<div class="profile-left"><img src="'+escapeAttr(imgUrl)+'" alt="Profile image" class="main-profile-image"></div>';
        } else {
            var initials = ((profileData.first||'').charAt(0) + (profileData.last||'').charAt(0)).toUpperCase();
            leftHtml = '<div class="profile-left"><div class="profile-placeholder">'+escapeHtml(initials)+'</div></div>';
        }

        var rightHtml = '<div class="profile-right">';
        rightHtml += '<div class="profile-header"><h1 class="profile-name">'+escapeHtml(profileData.first)+' '+escapeHtml(profileData.last)+'</h1>';
        if (profileData.country) rightHtml += '<div style="margin-left:8px;color:#777;font-size:0.95em;">'+escapeHtml(profileData.country)+'</div>';
        rightHtml += '</div>';
        rightHtml += '<div class="profile-meta">';
        if (profileData.bio) rightHtml += '<div><span class="label">Bio:</span> '+escapeHtml(profileData.bio)+'</div>';
        if (profileData.dob) rightHtml += '<div><span class="label">DOB:</span> '+escapeHtml(profileData.dob)+'</div>';
        if (profileData.email) rightHtml += '<div><span class="label">Email:</span> <a href="mailto:'+encodeURIComponent(profileData.email)+'">'+escapeHtml(profileData.email)+'</a></div>';
        rightHtml += '</div>';

        // build galleries
        function buildGallery(title, works) {
            if (!works || !Array.isArray(works) || works.length === 0) return '';
            var html = '<div class="gallery-title">'+escapeHtml(title)+'</div><div class="horizontal-gallery">';
            works.forEach(function(item){
                var path = item.path || (item.image ? item.image.replace("/var/www/html","") : '');
                var type = item.type || 'image';
                var t = item.title || item.desc || '';
                var date = item.date || '';
                var artist = item.artist || (profileData.first+' '+profileData.last);
                var user_folder = item.user_folder || profile_username;
                if (path) {
                    var data = ' data-path="'+escapeAttr(path)+'" data-type="'+escapeAttr(type)+'" data-title="'+escapeAttr(t)+'" data-date="'+escapeAttr(date)+'" data-artist="'+escapeAttr(artist)+'" data-profile="'+escapeAttr(user_folder)+'"';
                    if (type === 'audio') {
                        html += '<div class="audio-thumb"'+data+'>'+escapeHtml(t)+'</div>';
                    } else {
                        html += '<img src="'+escapeAttr(path)+'" class="work-thumb"'+data+'>';
                    }
                }
            });
            html += '</div>';
            return html;
        }

        rightHtml += buildGallery('My Work', profileData.work);
        rightHtml += buildGallery('Collection', profileData.selected_works);
        rightHtml += '</div>';

        details.innerHTML = '<div class="main-profile-inner">'+leftHtml+rightHtml+'</div>';

        // attach click handlers
        document.querySelectorAll('#mainProfile .work-thumb, #mainProfile .audio-thumb').forEach(function(el){
            el.addEventListener('click', function(){
                openSelectedWorkModal(el.dataset);
            });
        });
    }

    function openSelectedWorkModal(dataset) {
        var modal = document.getElementById('selectedWorksModal');
        var img = document.getElementById('selectedWorksModalImg');
        var audio = document.getElementById('selectedWorksModalAudio');
        var info = document.getElementById('selectedWorksModalInfo');
        var profileBtn = document.getElementById('selectedWorksModalProfileBtn');
        var radio = document.getElementById('selectedWorkLikeRadio');

        // reset
        img.style.display = 'none';
        audio.style.display = 'none';
        audio.pause(); audio.src = '';

        var work = {
            path: dataset.path || dataset['path'] || '',
            type: dataset.type || 'image',
            title: dataset.title || '',
            date: dataset.date || '',
            artist: dataset.artist || '',
            profile: dataset.profile || ''
        };

        if (work.type === 'audio' || /\.(mp3)$/i.test(work.path)) {
            audio.src = work.path;
            audio.style.display = 'block';
        } else {
            img.src = work.path;
            img.alt = work.title || 'Artwork';
            img.style.display = 'block';
        }

        info.innerHTML = '<div style="font-weight:bold;font-size:1.1em;">'+escapeHtml(work.title)+'</div>'
                       + '<div style="color:#666;margin-top:6px;">'+escapeHtml(work.artist)+'</div>'
                       + (work.date ? '<div style="color:#888;margin-top:6px;">'+escapeHtml(work.date)+'</div>' : '');
        if (profileBtn) {
            if (work.profile) { profileBtn.href = 'profile.php?user=' + encodeURIComponent(work.profile); profileBtn.style.display = 'inline-block'; }
            else { profileBtn.style.display = 'none'; }
        }

        // radio behavior
        if (radio) {
            radio.checked = false;
            radio.onclick = null;
            // set checked state if already selected
            if (isLoggedIn && loggedInUserProfile && Array.isArray(loggedInUserProfile.selected_works)) {
                radio.checked = loggedInUserProfile.selected_works.some(function(s){ return s.path === work.path; });
            }
            // attach handler to select work
            radio.onclick = function() {
                if (!isLoggedIn) return;
                selectWork(work, function(success){
                    if (success) radio.checked = true;
                });
            };
        }

        modal.style.display = 'flex';
    }

    function selectWork(workData, cb) {
        // posts to this file's select_work handler
        var fd = new FormData();
        fd.append('action', 'select_work');
        fd.append('work_data', JSON.stringify({
            path: workData.path,
            title: workData.title,
            date: workData.date,
            artist: workData.artist,
            profile: workData.profile
        }));

        fetch(window.location.pathname, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(function(res){
            if (res && res.success) {
                // update local profile object to reflect selection
                if (!loggedInUserProfile) loggedInUserProfile = { selected_works: [] };
                if (!Array.isArray(loggedInUserProfile.selected_works)) loggedInUserProfile.selected_works = [];
                if (!loggedInUserProfile.selected_works.find(w => w.path === workData.path)) {
                    loggedInUserProfile.selected_works.push({
                        path: workData.path,
                        title: workData.title || '',
                        date: workData.date || '',
                        artist: workData.artist || '',
                        user_folder: workData.profile || ''
                    });
                }
                if (typeof cb === 'function') cb(true);
            } else {
                console.error('Selection failed', res);
                if (typeof cb === 'function') cb(false);
            }
        }).catch(function(err){
            console.error('Error selecting work', err);
            if (typeof cb === 'function') cb(false);
        });
    }

    document.addEventListener('DOMContentLoaded', function(){
        var params = new URLSearchParams(window.location.search);
        var username = params.get('user');
        if (username) {
            fetch('profile.php?api=1&user=' + encodeURIComponent(username))
            .then(r => r.json())
            .then(function(data){ renderMainProfile(data); })
            .catch(function(){ document.getElementById('mainProfile').innerHTML = "<em style='padding:18px; display:block; text-align:center;'>Error loading profile.</em>"; });
        } else {
            document.getElementById('mainProfile').innerHTML = "<em style='padding:18px; display:block; text-align:center;'>No profile selected.</em>";
        }

        var modal = document.getElementById('selectedWorksModal');
        var closeBtn = document.getElementById('closeSelectedWorksModal');
        closeBtn && (closeBtn.onclick = function(){ modal.style.display='none'; document.getElementById('selectedWorksModalAudio').pause(); });
        modal && (modal.onclick = function(e){ if (e.target === modal) { modal.style.display='none'; document.getElementById('selectedWorksModalAudio').pause(); } });
    });
</script>

</body>
</html>
