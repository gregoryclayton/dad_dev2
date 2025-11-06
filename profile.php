<?php
// profile.php: Displays a single user's profile info in the mainProfile div, loaded via AJAX

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

// Page mode: render the HTML, JS loads data to #mainProfile
?>
<?php
// Collect all profile data into a PHP array and prepare profile image map
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
            }
        }
    }

    // Build a map username -> latest profile image (if any)
    foreach ($userProfiles as $profile) {
        $safe_first = isset($profile['first']) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profile['first']) : '';
        $safe_last = isset($profile['last']) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profile['last']) : '';
        $user_folder = $safe_first . "_" . $safe_last;
        $work_dir = $baseDir . '/' . $user_folder . '/work';
        $img = "";
        if (is_dir($work_dir)) {
            $candidates = glob($work_dir . "/profile_image_*.*");
            if ($candidates && count($candidates) > 0) {
                usort($candidates, function($a, $b) { return filemtime($b) - filemtime($a); });
                $img = str_replace("/var/www/html", "", $candidates[0]);
            } else {
                // fallback: pick a most recent work image
                $allImgs = glob($work_dir . "/*.{jpg,jpeg,png,gif}", GLOB_BRACE);
                if ($allImgs && count($allImgs) > 0) {
                    usort($allImgs, function($a, $b) { return filemtime($b) - filemtime($a); });
                    $img = str_replace("/var/www/html", "", $allImgs[0]);
                }
            }
        }
        $profile_images_map[$user_folder] = $img ? [$img] : [];
    }
} else {
    echo "User profiles directory not found.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Profile</title>
    <link rel="stylesheet" type="text/css" href="style.css">

    <style>
    /* Main profile layout */
    #mainProfile {
        max-width: 1100px;
        margin: 26px auto;
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 6px 24px rgba(0,0,0,0.08);
        padding: 20px;
        color: #222;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial;
    }
    .main-profile-inner {
        display: flex;
        gap: 22px;
        align-items: flex-start;
        flex-wrap: wrap;
    }
    .profile-left {
        flex: 0 0 240px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .main-profile-image {
        width: 240px;
        height: 240px;
        border-radius: 10px;
        object-fit: cover;
        background: #f4f4f4;
        display: block;
        box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    }
    .profile-placeholder {
        width: 240px;
        height: 240px;
        border-radius: 10px;
        background: linear-gradient(135deg,#f3f3f5,#e9eef6);
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:48px;
        color:#9aa3b2;
        box-shadow: 0 6px 18px rgba(0,0,0,0.06);
    }
    .profile-right {
        flex: 1 1 480px;
        min-width: 260px;
    }
    .profile-header {
        display:flex;
        align-items:baseline;
        gap:12px;
    }
    .profile-name {
        font-size:28px;
        font-weight:700;
        margin:0;
    }
    .profile-meta {
        margin-top:12px;
        color:#444;
        line-height:1.5;
    }
    .profile-meta .label { font-weight:600; color:#333; margin-right:8px; }
    .work-grid {
        display:flex;
        flex-wrap:wrap;
        gap:12px;
        margin-top:14px;
    }
    .work-thumb {
        width: 140px;
        height: 140px;
        border-radius:8px;
        object-fit:cover;
        background:#f6f6f6;
        box-shadow:0 4px 12px rgba(0,0,0,0.06);
        cursor:pointer;
    }
    @media (max-width: 760px) {
        .main-profile-inner { flex-direction: column; align-items: center; }
        .profile-left { flex-basis: auto; }
        .profile-right { width: 100%; }
    }
    </style>

    <script>
    // Expose PHP data to JS
    var profileImagesMap = <?php echo json_encode($profile_images_map, JSON_UNESCAPED_SLASHES); ?>;
    </script>

    <script>
    // Render the main profile area in a sleek two-column layout
    function renderMainProfile(profileData) {
        var details = document.getElementById('mainProfile');
        if (!profileData || !profileData.first) {
            details.innerHTML = "<em style='padding:18px; display:block; text-align:center;'>No profile data.</em>";
            return;
        }
        var safe_first = profileData.first ? profileData.first.replace(/[^a-zA-Z0-9_\-\.]/g, '_') : '';
        var safe_last = profileData.last ? profileData.last.replace(/[^a-zA-Z0-9_\-\.]/g, '_') : '';
        var profile_username = safe_first + "_" + safe_last;
        var user_dir = "/pusers/" + profile_username;

        // Determine profile image (from server-side map) or fallback to first work image
        var imgUrl = "";
        if (profileImagesMap && profileImagesMap[profile_username] && profileImagesMap[profile_username].length) {
            imgUrl = profileImagesMap[profile_username][0];
        } else if (profileData.work && profileData.work.length) {
            // Try the first work image (already stored as absolute or with /var/www/html)
            var firstWork = profileData.work.find(function(w){ return w.image; });
            if (firstWork && firstWork.image) {
                imgUrl = firstWork.image.replace("/var/www/html", "");
            }
        }

        // Build left (image) and right (info) columns
        var leftHtml = "";
        if (imgUrl) {
            leftHtml = '<div class="profile-left">' +
                       '<img src="' + imgUrl + '" alt="Profile image of ' + (profileData.first||'') + '" class="main-profile-image">' +
                       '</div>';
        } else {
            // Placeholder with initials
            var initials = ((profileData.first||'').charAt(0) + (profileData.last||'').charAt(0)).toUpperCase();
            leftHtml = '<div class="profile-left">' +
                       '<div class="profile-placeholder">' + initials + '</div>' +
                       '</div>';
        }

        var rightHtml = '<div class="profile-right">';
        rightHtml += '<div class="profile-header"><h1 class="profile-name">' + (profileData.first ? profileData.first : '') + ' ' + (profileData.last ? profileData.last : '') + '</h1>';
        if (profileData.country) rightHtml += '<div style="margin-left:8px;color:#777;font-size:0.95em;">' + escapeHtml(profileData.country) + '</div>';
        rightHtml += '</div>'; // header

        // contact / meta
        rightHtml += '<div class="profile-meta">';
        if (profileData.bio) rightHtml += '<div><span class="label">Bio:</span> ' + escapeHtml(profileData.bio) + '</div>';
        if (profileData.dob) rightHtml += '<div><span class="label">DOB:</span> ' + escapeHtml(profileData.dob) + '</div>';
        if (profileData.email) rightHtml += '<div><span class="label">Email:</span> <a href="mailto:' + encodeURIComponent(profileData.email) + '">' + escapeHtml(profileData.email) + '</a></div>';
        rightHtml += '</div>';

        // Work grid (thumbnails)
        if (profileData.work && Array.isArray(profileData.work) && profileData.work.length > 0) {
            rightHtml += '<div style="margin-top:14px;"><strong>Work</strong></div>';
            rightHtml += '<div class="work-grid">';
            profileData.work.forEach(function(work_item, i){
                var work_img = work_item.image ? work_item.image.replace("/var/www/html", "") : '';
                var desc = work_item.desc ? escapeHtml(work_item.desc) : '';
                var date = work_item.date ? escapeHtml(work_item.date) : '';
                if (work_img) {
                    rightHtml += '<img src="' + work_img + '" class="work-thumb" data-desc="' + escapeAttr(desc) + '" data-date="' + escapeAttr(date) + '" data-artist="' + escapeAttr((profileData.first||'') + ' ' + (profileData.last||'')) + '" data-path="' + escapeAttr(work_img) + '">';
                } else {
                    rightHtml += '<div style="width:140px;height:140px;background:#f3f3f3;border-radius:8px;"></div>';
                }
            });
            rightHtml += '</div>';
        }

        rightHtml += '</div>'; // profile-right

        details.innerHTML = '<div class="main-profile-inner">' + leftHtml + rightHtml + '</div>';

        // Attach click handler for work thumbs to open modal (reuse selectedWorksModal if present)
        document.querySelectorAll('#mainProfile .work-thumb').forEach(function(img) {
            img.addEventListener('click', function(e) {
                e.stopPropagation();
                var path = img.dataset.path || img.src || '';
                var desc = img.dataset.desc || '';
                var date = img.dataset.date || '';
                var artist = img.dataset.artist || '';
                openSelectedWorkModal({ path: path, title: desc || path.split('/').pop(), date: date, artist: artist, profile: profile_username });
            });
        });
    }

    // Small helpers
    function escapeHtml(s) {
        if (!s && s !== 0) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
    function escapeAttr(s) {
        if (!s && s !== 0) return '';
        return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,"&#39;").replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // Modal opener used by other pages too (selectedWorksModal expected in page)
    function openSelectedWorkModal(work) {
        var modal = document.getElementById('selectedWorksModal');
        if (!modal) {
            // create a lightweight modal if selectedWorksModal does not exist
            createQuickModal();
            modal = document.getElementById('selectedWorksModal');
        }
        var img = document.getElementById('selectedWorksModalImg');
        var info = document.getElementById('selectedWorksModalInfo');
        var profileBtn = document.getElementById('selectedWorksModalProfileBtn');

        img.src = work.path || '';
        img.alt = work.title || '';
        var infoHtml = '<div style="font-weight:bold;font-size:1.1em;">' + escapeHtml(work.title || '') + '</div>' +
                       '<div style="color:#666;margin-top:6px;">' + escapeHtml(work.artist || '') + '</div>' +
                       (work.date ? '<div style="color:#888;margin-top:6px;">' + escapeHtml(work.date) + '</div>' : '') +
                       (work.path ? '<div style="color:#aaa;margin-top:6px;font-size:0.9em;">' + escapeHtml(work.path) + '</div>' : '');
        info.innerHTML = infoHtml;
        if (profileBtn) profileBtn.href = 'profile.php?user=' + encodeURIComponent(work.profile || '');
        modal.style.display = 'flex';
    }

    function createQuickModal() {
        var modal = document.createElement('div');
        modal.id = 'selectedWorksModal';
        modal.style = 'display:flex;position:fixed;z-index:10000;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.85);align-items:center;justify-content:center;';
        modal.innerHTML = '<div style="background:#fff;border-radius:12px;padding:24px;max-width:90vw;max-height:90vh;position:relative;display:flex;flex-direction:column;align-items:center;">' +
                          '<span id="closeSelectedWorksModal" style="position:absolute;top:12px;right:16px;font-size:26px;cursor:pointer;">&times;</span>' +
                          '<img id="selectedWorksModalImg" src="" style="max-width:80vw;max-height:60vh;border-radius:8px;margin-bottom:12px;">' +
                          '<div id="selectedWorksModalInfo" style="text-align:center;"></div>' +
                          '<a id="selectedWorksModalProfileBtn" href="#" style="display:inline-block;margin-top:12px;background:#e8bebe;color:#000;padding:8px 14px;border-radius:8px;text-decoration:none;">Visit profile</a>' +
                          '</div>';
        document.body.appendChild(modal);
        document.getElementById('closeSelectedWorksModal').onclick = function(){ modal.style.display = 'none'; };
        modal.onclick = function(e) { if (e.target === modal) modal.style.display = 'none'; };
    }

    document.addEventListener("DOMContentLoaded", function() {
        // Get user from URL param
        var params = new URLSearchParams(window.location.search);
        var username = params.get('user');
        if (username) {
            fetch("profile.php?api=1&user=" + encodeURIComponent(username))
                .then(res => res.json())
                .then(data => renderMainProfile(data));
        } else {
            document.getElementById('mainProfile').innerHTML = "<em style='padding:18px; display:block; text-align:center;'>No profile selected.</em>";
        }
    });
    </script>

    <script>
    var userProfiles = <?php echo json_encode($userProfiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>;

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

            // Basic profile info
            div.innerHTML = "<strong>" + profileData.first + " " + profileData.last + "</strong><br>" +
                "<span>" + (profileData.email ? profileData.email : "") + "</span><br>";

            // Dropdown for profile info (hidden by default)
            var dropdown = document.createElement('div');
            dropdown.className = "profile-dropdown";
            dropdown.setAttribute("id", "dropdown-" + profile_username);

            // Fill dropdown with all extra info
            var html = "";
            var profile_images_map_js = <?php echo json_encode($profile_images_map, JSON_UNESCAPED_SLASHES); ?>;
            if (profile_images_map_js[profile_username] && profile_images_map_js[profile_username][0]) {
                html += '<div><img src="' + profile_images_map_js[profile_username][0] + '" class="profile-image" alt="Profile Image" style="max-width:120px;border-radius:8px;"></div>';
            }
            html += "<strong>Created At:</strong> " + (profileData.created_at ? profileData.created_at : "") + "<br>";
            if (profileData.bio) html += "<strong>Bio:</strong> " + profileData.bio + "<br>";
            if (profileData.dob) html += "<strong>Date of Birth:</strong> " + profileData.dob + "<br>";
            if (profileData.country) html += "<strong>Country:</strong> " + profileData.country + "<br>";
            // Work images & info
            if (profileData.work && Array.isArray(profileData.work) && profileData.work.length > 0) {
                html += "<strong>Work:</strong><ul style='padding-left:0;'>";
                profileData.work.forEach(function(work_item){
                    html += "<li style='margin-bottom:8px; list-style:none;'>";
                    if (work_item.image) {
                        var work_img_src = work_item.image.replace("/var/www/html", "");
                        html += '<img src="' + work_img_src + '" class="work-image" alt="Work Image" style="max-width:120px;max-height:80px;object-fit:cover;border-radius:8px;margin:6px;cursor:pointer;" data-desc="' + (work_item.desc?work_item.desc:'') + '" data-date="' + (work_item.date?work_item.date:'') + '" data-artist="' + ((profileData.first||'')+' '+(profileData.last||'')) + '" data-path="' + work_img_src + '"><br>';
                    }
                    if (work_item.desc) html += "<strong>Description:</strong> " + work_item.desc + "<br>";
                    if (work_item.date) html += "<strong>Date:</strong> " + work_item.date + "<br>";
                    html += "</li>";
                });
                html += "</ul>";
            }
            // Profile page button
            html += '<button class="profile-btn" onclick="window.location.href=\'profile.php?user=' + profile_username + '\'">Profile Page</button>';

            dropdown.innerHTML = html;
            div.appendChild(dropdown);

            // Toggle dropdown on profile click (not on button click)
            div.onclick = function(e) {
                if (e.target.classList.contains('profile-btn')) return;
                var allDropdowns = document.querySelectorAll('.profile-dropdown');
                allDropdowns.forEach(function(d) { if (d !== dropdown) d.style.display = 'none'; });
                dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
            };

            container.appendChild(div);
        });
        // Click outside to close any open dropdown
        document.addEventListener('click', function(e) {
            if (!e.target.classList.contains('user-profile') && !e.target.classList.contains('profile-btn')) {
                document.querySelectorAll('.profile-dropdown').forEach(function(d) {
                    d.style.display = 'none';
                });
            }
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        renderProfiles(userProfiles);
    });
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

    <!-- User profiles array selection at bottom -->
<div id="user-profiles" style="max-width:1100px;margin:22px auto;"></div>

</body>
</html>
