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
<!DOCTYPE html>
<html>
<head>
    <title>User Profile</title>
    <style>
        #mainProfile { border:2px solid #444; margin:20px 0; padding:20px; background:#f6f6f6; }
        .profile-image { max-width:200px; max-height:200px; }
        .work-image { max-width:100px; max-height:100px; }
    </style>
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
        if (profileData.email) html += "<strong>Email:</strong> " + profileData.email + "<br>";
        if (profileData.created_at) html += "<strong>Created At:</strong> " + profileData.created_at + "<br>";
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
            html += '<div id="workDiv"><strong>Work:</strong><ul>';
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
</head>
<body>
<div id="mainProfile"></div>
</body>
</html>
