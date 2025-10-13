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
$profile_php_path = __DIR__ . "/profile/" . $username . ".php";

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
$php .= "</body></html>\n";

// Ensure the profile directory exists
$profile_dir = __DIR__ . "/profile";
if (!is_dir($profile_dir)) {
    mkdir($profile_dir, 0755, true);
}
// Write the file
file_put_contents($profile_php_path, $php);

echo "Profile page created";

$profile = json_decode(file_get_contents($profile_json_path), true);

// Create the PHP file
$php = "<?php\n";
$php .= "/* Dynamically generated profile page */\n";
$php .= "?>\n";
$php .= "<!DOCTYPE html>\n<html><head><title>User Profile: " . htmlspecialchars($username) . "</title></head><body>\n";
$php .= "<h1>User Profile: " . htmlspecialchars($profile['first'] . " " . $profile['last']) . "</h1>\n";
$php .= "<ul>\n";
foreach ($profile as $key => $value) {
    $php .= "<li><strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars($value) . "</li>\n";
}
$php .= "</ul>\n";
$php .= "</body></html>\n";

// Ensure the profile directory exists
$profile_dir = __DIR__ . "/profile";
if (!is_dir($profile_dir)) {
    mkdir($profile_dir, 0755, true);
}
// Write the file
file_put_contents($profile_php_path, $php);

echo "Profile page created";
