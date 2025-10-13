<?php
// This script receives POST "username" (first_last)
// Checks pusers/first_last/profile.json
// If exists, generates profile/first_last.php with formatted user info

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

// Create the PHP file
$php = "<?php\n";
$php .= "/* Dynamically generated profile page */\n";
$php .= "?>\n";
$php .= "<!DOCTYPE html>\n<html><head><title>User Profile: " . htmlspecialchars($username) . "</title></head><body>\n";
$php .= "<h1>User Profile: " . htmlspecialchars($profile['first'] . " " . $profile['last']) . "</h1>\n";
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





<?php    
$baseDir = "/var/www/html/pusers";

// Collect all profile data into array
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

echo '<div id="user-profiles">';
foreach ($userProfiles as $profileData) {
    $safe_first = isset($profileData['first']) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profileData['first']) : '';
    $safe_last = isset($profileData['last']) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profileData['last']) : '';
    $profile_username = $safe_first . "_" . $safe_last;
    echo '<div class="user-profile" data-username="' . htmlspecialchars($profile_username) . '" style="border:1px solid #ccc; margin:10px; padding:10px; cursor:pointer;">';
    foreach ($profileData as $key => $value) {
        if ($key === 'work' && is_array($value)) {
            echo "<strong>Work:</strong><ul>";
            foreach ($value as $work_item) {
                echo "<li>";
                if (!empty($work_item['image'])) {
                    $web_path = str_replace("/var/www/html", "", $work_item['image']);
                    echo '<img src="' . htmlspecialchars($web_path) . '" alt="Work Image" style="max-width:100px; max-height:100px;"><br>';
                }
                if (!empty($work_item['desc'])) {
                    echo "<strong>Description:</strong> " . htmlspecialchars($work_item['desc']) . "<br>";
                }
                if (!empty($work_item['date'])) {
                    echo "<strong>Date:</strong> " . htmlspecialchars($work_item['date']) . "<br>";
                }
                echo "</li>";
            }
            echo "</ul>";
        } else {
            echo "<span class='profile-data'><strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars($value) . "<br></span>";
        }
    }
    echo '</div>';
}
echo '</div>';
?>



$php .= "</body></html>\n";

// Ensure the profile directory exists
$profile_dir = __DIR__ . "/pusers";
if (!is_dir($profile_dir)) {
    mkdir($profile_dir, 0755, true);
}
// Write the file
file_put_contents($profile_php_path, $php);

echo "Profile page created";


