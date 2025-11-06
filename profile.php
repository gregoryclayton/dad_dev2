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
    <title>User Profile</title>

    <link rel="stylesheet" type="text/css" href="style.css">
   
    <script>
    function renderMainProfile(profileData) {
        var details = document.getElementById('mainProfile');
        if (!profileData || !profileData.first) {
            details.innerHTML = "<em>No profile data.</em>";
            return;
        }
        var safe_first = profileData.first ? profileData.first.replace(/[^a-zA-Z0-9_\-\.]/g, '_') : '';
        var safe_last = profileData.last ? profileData.last.replace(/[^a-zA-Z0-9_\-\.]/g, '_') : '';
        var user_dir = "/pusers/" + safe_first + "_" + safe_last;

        var html = "<h2>" + (profileData.first ? profileData.first : "") + " " + (profileData.last ? profileData.last : "") + "</h2>";
        // if (profileData.email) html += "<strong>Email:</strong> " + profileData.email + "<br>";
        // if (profileData.created_at) html += "<strong>Created At:</strong> " + profileData.created_at + "<br>";
        if (profileData.bio) html += "<strong>Bio:</strong> " + profileData.bio + "<br>";
        if (profileData.dob) html += "<strong>Date of Birth:</strong> " + profileData.dob + "<br>";
        if (profileData.country) html += "<strong>Country:</strong> " + profileData.country + "<br>";

        // Profile image (find latest file)
        var imgHtml = "";
        // You may want to expose image URLs via endpoint, or use a convention
        // For simplicity, try the convention:
        var profileImgUrl = user_dir + "/profile_image_latest.jpg";
        imgHtml += '<div id="profilePicDiv"></div>';
        html += imgHtml;

        // Work section
        if (profileData.work && Array.isArray(profileData.work) && profileData.work.length > 0) {
            html += '<div id="workDiv"><strong>Work:</strong><ul class="workList">';
            profileData.work.forEach(function(work_item){
                html += "<li>";
                if (work_item.image) {
                    var work_img_src = work_item.image.replace("/var/www/html", "");
                    html += '<img src="' + work_img_src + '" class="work-image" alt="Work Image"><br>';
                }
                if (work_item.desc) html += "<strong>Description:</strong> " + work_item.desc + "<br>";
                if (work_item.date) html += "<strong>Date:</strong> " + work_item.date + "<br>";
                html += "</li>";
            });
            html += "</ul></div>";
        }
        details.innerHTML = html;
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
            document.getElementById('mainProfile').innerHTML = "<em>No profile selected.</em>";
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
            // Profile image preview
            var user_dir = "/var/www/html/pusers/" + profile_username + "/work";
            <?php
            // Prepare a PHP map of latest profile image for each user
            $profile_images_map = [];
            foreach ($userProfiles as $profile) {
                $safe_first = isset($profile['first']) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profile['first']) : '';
                $safe_last = isset($profile['last']) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profile['last']) : '';
                $user_dir = "/var/www/html/pusers/" . $safe_first . "_" . $safe_last . "/work";
                $images = [];
                if (is_dir($user_dir)) {
                    $imgs = glob($user_dir . "/profile_image_*.*");
                    if ($imgs && count($imgs) > 0) {
                        usort($imgs, function($a, $b) { return filemtime($b) - filemtime($a); });
                        $images[] = str_replace("/var/www/html", "", $imgs[0]);
                    }
                }
                $profile_images_map[$safe_first . "_" . $safe_last] = $images;
            }
            ?>
            var profile_images_map = <?php echo json_encode($profile_images_map, JSON_UNESCAPED_SLASHES); ?>;
            if (profile_images_map[profile_username] && profile_images_map[profile_username][0]) {
                html += '<div><img src="' + profile_images_map[profile_username][0] + '" class="profile-image" alt="Profile Image"></div>';
            }
            html += "<strong>Created At:</strong> " + (profileData.created_at ? profileData.created_at : "") + "<br>";
            if (profileData.bio) html += "<strong>Bio:</strong> " + profileData.bio + "<br>";
            if (profileData.dob) html += "<strong>Date of Birth:</strong> " + profileData.dob + "<br>";
            if (profileData.country) html += "<strong>Country:</strong> " + profileData.country + "<br>";
            // Work images & info
            if (profileData.work && Array.isArray(profileData.work) && profileData.work.length > 0) {
                html += "<strong>Work:</strong><ul style='padding-left:0;'>";
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
            // Profile page button
            html += '<button class="profile-btn" onclick="window.location.href=\'profile.php?user=' + profile_username + '\'">Profile Page</button>';

            dropdown.innerHTML = html;
            div.appendChild(dropdown);

            // Toggle dropdown on profile click (not on button click)
            div.onclick = function(e) {
                // If the button was clicked, let it work normally
                if (e.target.classList.contains('profile-btn')) return;
                // Show/hide dropdown
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
<div id="user-profiles"></div>
</body>
</html>
