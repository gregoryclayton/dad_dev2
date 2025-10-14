<?php
// This script receives POST "username" (first_last)
// Checks pusers/first_last/profile.json
// If exists, generates pusers/first_last.php with formatted user info

if (!isset($_POST['username'])) {
    http_response_code(400);
    echo "Missing username";
    exit;
}

$username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_POST['username']);
$profile_json_path = "/var/www/html/pusers/" . $username . "/profile.json";
$profile_php_path = __DIR__ . "/pusers/" . $username . ".php";

// Check if profile.json exists
if (!file_exists($profile_json_path)) {
    http_response_code(404);
    echo "Profile not found";
    exit;
}

$profile = json_decode(file_get_contents($profile_json_path), true);

// Find profile image (latest profile_image_*)
$profile_image_html = "";
$user_dir = "/var/www/html/pusers/" . $username;
$profile_images = [];
if (is_dir($user_dir)) {
    $imgs = glob($user_dir . "/profile_image_*.*");
    if ($imgs && count($imgs) > 0) {
        usort($imgs, function($a, $b) { return filemtime($b) - filemtime($a); });
        $latest_img = $imgs[0];
        $web_path = str_replace("/var/www/html", "", $latest_img);
        $profile_image_html = '<div class="profile-img-div"><img src="' . htmlspecialchars($web_path) . '" alt="Profile Image" style="max-width:200px; max-height:200px;"></div>';
    }
}

// Build work HTML if work exists
$work_html = "";
if (!empty($profile['work']) && is_array($profile['work'])) {
    $work_html .= '<div class="work-div"><h2>Work</h2><ul>';
    foreach ($profile['work'] as $work_item) {
        $work_html .= "<li>";
        if (!empty($work_item['image'])) {
            $work_web_path = str_replace("/var/www/html", "", $work_item['image']);
            $work_html .= '<img src="' . htmlspecialchars($work_web_path) . '" alt="Work Image" style="max-width:100px; max-height:100px;"><br>';
        }
        if (!empty($work_item['desc'])) {
            $work_html .= "<strong>Description:</strong> " . htmlspecialchars($work_item['desc']) . "<br>";
        }
        if (!empty($work_item['date'])) {
            $work_html .= "<strong>Date:</strong> " . htmlspecialchars($work_item['date']) . "<br>";
        }
        $work_html .= "</li>";
    }
    $work_html .= '</ul></div>';
}

// Create the PHP file
$php = "<?php\n";
$php .= "/* Dynamically generated profile page */\n";
$php .= "?>\n";
$php .= "<!DOCTYPE html>\n<html><head><title>User Profile: " . htmlspecialchars($username) . "</title>
<style>
.profile-img-div { margin: 10px 0; }
.work-div { margin: 20px 0; }
.work-div ul { list-style: none; padding: 0; }
.work-div li { margin-bottom: 10px; }
</style>
</head><body>\n";
$php .= "<h1>User Profile: " . htmlspecialchars($profile['first'] . " " . $profile['last']) . "</h1>\n";

// Display profile image if exists
$php .= $profile_image_html;

// Display main user info
$php .= "<ul>\n";
$php .= "<li><strong>First Name:</strong> " . htmlspecialchars($profile['first']) . "</li>\n";
$php .= "<li><strong>Last Name:</strong> " . htmlspecialchars($profile['last']) . "</li>\n";
$php .= "<li><strong>Email:</strong> " . htmlspecialchars($profile['email']) . "</li>\n";
$php .= "<li><strong>Created At:</strong> " . htmlspecialchars($profile['created_at']) . "</li>\n";
// Optionally display bio/dob/country if present
if (!empty($profile['bio'])) {
    $php .= "<li><strong>Bio:</strong> " . nl2br(htmlspecialchars($profile['bio'])) . "</li>\n";
}
if (!empty($profile['dob'])) {
    $php .= "<li><strong>Date of Birth:</strong> " . htmlspecialchars($profile['dob']) . "</li>\n";
}
if (!empty($profile['country'])) {
    $php .= "<li><strong>Country:</strong> " . htmlspecialchars($profile['country']) . "</li>\n";
}
$php .= "</ul>\n";

// Display work section if exists
$php .= $work_html;

// Display user-profiles array at the bottom (as before)
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
    $php .= "User profiles directory not found.";
}

$php .= '<div id="user-profiles">';
foreach ($userProfiles as $profileData) {
    $safe_first = isset($profileData['first']) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profileData['first']) : '';
    $safe_last = isset($profileData['last']) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profileData['last']) : '';
    $profile_username = $safe_first . "_" . $safe_last;
    $php .= '<div class="user-profile" data-username="' . htmlspecialchars($profile_username) . '" style="border:1px solid #ccc; margin:10px; padding:10px; cursor:pointer;">';
    foreach ($profileData as $key => $value) {
        if ($key === 'work' && is_array($value)) {
            $php .= "<strong>Work:</strong><ul>";
            foreach ($value as $work_item) {
                $php .= "<li>";
                if (!empty($work_item['image'])) {
                    $web_path = str_replace("/var/www/html", "", $work_item['image']);
                    $php .= '<img src="' . htmlspecialchars($web_path) . '" alt="Work Image" style="max-width:100px; max-height:100px;"><br>';
                }
                if (!empty($work_item['desc'])) {
                    $php .= "<strong>Description:</strong> " . htmlspecialchars($work_item['desc']) . "<br>";
                }
                if (!empty($work_item['date'])) {
                    $php .= "<strong>Date:</strong> " . htmlspecialchars($work_item['date']) . "<br>";
                }
                $php .= "</li>";
            }
            $php .= "</ul>";
        } else {
            $php .= "<span class='profile-data'><strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars($value) . "<br></span>";
        }
    }
    $php .= '</div>';
}
$php .= '</div>';

$php .= "</body></html>\n";

// Ensure the profile directory exists
$profile_dir = __DIR__ . "/pusers";
if (!is_dir($profile_dir)) {
    mkdir($profile_dir, 0755, true);
}
// Write the file
file_put_contents($profile_php_path, $php);

echo "Profile page created";
