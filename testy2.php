<?php
// Database credentials
include 'connection.php';

// Start session for login/logout
session_start();

// Connect to MySQL
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Helper: create user folder and profile.json
function create_user_profile($first, $last, $email) {
    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $first);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $last);
    $user_dir = "/var/www/html/pusers/" . $safe_first . "_" . $safe_last;
    if (!is_dir($user_dir)) {
        mkdir($user_dir, 0755, true);
    }
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

// Helper: update profile.json with bio, dob, country
function update_user_profile_extra($first, $last, $bio, $dob, $country) {
    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $first);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $last);
    $user_dir = "/var/www/html/pusers/" . $safe_first . "_" . $safe_last;
    $profile_path = $user_dir . "/profile.json";
    if (file_exists($profile_path)) {
        $profile = json_decode(file_get_contents($profile_path), true);
        $profile['bio'] = $bio;
        $profile['dob'] = $dob;
        $profile['country'] = $country;
        file_put_contents($profile_path, json_encode($profile, JSON_PRETTY_PRINT));
    }
}

// Helper: add work to profile.json
function add_user_work($first, $last, $desc, $date, $image_path) {
    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $first);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $last);
    $user_dir = "/var/www/html/pusers/" . $safe_first . "_" . $safe_last;
    $profile_path = $user_dir . "/profile.json";
    if (file_exists($profile_path)) {
        $profile = json_decode(file_get_contents($profile_path), true);
        if (!isset($profile['work']) || !is_array($profile['work'])) {
            $profile['work'] = [];
        }
        $profile['work'][] = [
            "desc" => $desc,
            "date" => $date,
            "image" => $image_path
        ];
        file_put_contents($profile_path, json_encode($profile, JSON_PRETTY_PRINT));
    }
}

// Handle registration
if (isset($_POST['register'])) {
    $first = $conn->real_escape_string($_POST['first']);
    $last = $conn->real_escape_string($_POST['last']);
    $email = $conn->real_escape_string($_POST['email']);
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check if email already exists
    $check = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($check->num_rows > 0) {
        echo "Email already registered!";
    } else {
        // Make sure your users table has first and last columns!
        $conn->query("INSERT INTO users (first,last,email,password) VALUES ('$first','$last','$email','$pass')");
        $user_dir = create_user_profile($first, $last, $email);
        echo "Registration successful! Please log in.";
    }
}

// Handle login
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
            echo "Logged in!";
        } else {
            echo "Incorrect password!";
        }
    } else {
        echo "No account found!";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: register_login.php");
    exit();
}

// Handle image upload (if logged in)
$image_upload_msg = "";
if (isset($_SESSION['email']) && isset($_POST['upload_image'])) {
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
        $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
        $user_dir = "/var/www/html/pusers/" . $safe_first . "_" . $safe_last;
        if (!is_dir($user_dir)) {
            mkdir($user_dir, 0755, true);
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($_FILES['image']['tmp_name']);
        if (!in_array($file_type, $allowed_types)) {
            $image_upload_msg = "Only JPG, PNG, and GIF files are allowed.";
        } else {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $target_file = $user_dir . "/profile_image_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_upload_msg = "Image uploaded successfully!";
            } else {
                $image_upload_msg = "Failed to upload image.";
            }
        }
    } else {
        $image_upload_msg = "No file uploaded or upload error.";
    }
}

// Handle bio/dob/country form submission
$profile_extra_msg = "";
if (isset($_SESSION['email']) && isset($_POST['update_profile_extra'])) {
    $bio = isset($_POST['bio']) ? $_POST['bio'] : "";
    $dob = isset($_POST['dob']) ? $_POST['dob'] : "";
    $country = isset($_POST['country']) ? $_POST['country'] : "";
    update_user_profile_extra($_SESSION['first'], $_SESSION['last'], $bio, $dob, $country);
    $profile_extra_msg = "Profile info updated!";
}

// Handle work upload
$work_upload_msg = "";
if (isset($_SESSION['email']) && isset($_POST['upload_work'])) {
    $desc = isset($_POST['work_desc']) ? $_POST['work_desc'] : "";
    $date = isset($_POST['work_date']) ? $_POST['work_date'] : "";
    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
    $user_dir = "/var/www/html/pusers/" . $safe_first . "_" . $safe_last;
    $image_path = "";
    if (isset($_FILES['work_image']) && $_FILES['work_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($_FILES['work_image']['tmp_name']);
        if (!in_array($file_type, $allowed_types)) {
            $work_upload_msg = "Only JPG, PNG, and GIF files are allowed for work image.";
        } else {
            $ext = pathinfo($_FILES['work_image']['name'], PATHINFO_EXTENSION);
            $target_file = $user_dir . "/work_image_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['work_image']['tmp_name'], $target_file)) {
                $image_path = $target_file;
                add_user_work($_SESSION['first'], $_SESSION['last'], $desc, $date, $image_path);
                $work_upload_msg = "Work uploaded successfully!";
            } else {
                $work_upload_msg = "Failed to upload work image.";
            }
        }
    } else {
        $work_upload_msg = "No work image uploaded or upload error.";
    }
}
?>

<?php    
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
    <title>Register/Login Example</title>
    <script>
    // Embed the user profiles as a JSON array for easy JS manipulation
    var userProfiles = <?php echo json_encode($userProfiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>;

    // Renders user profiles and sets up click handling for opening profiles
    document.addEventListener("DOMContentLoaded", function() {
        var container = document.getElementById('user-profiles');
        if (container && Array.isArray(userProfiles)) {
            userProfiles.forEach(function(profileData) {
                var safe_first = profileData.first ? profileData.first.replace(/[^a-zA-Z0-9_\-\.]/g, '_') : '';
                var safe_last = profileData.last ? profileData.last.replace(/[^a-zA-Z0-9_\-\.]/g, '_') : '';
                var profile_username = safe_first + "_" + safe_last;
                var div = document.createElement('div');
                div.className = "user-profile";
                div.setAttribute("data-username", profile_username);
                div.style.border = "1px solid #ccc";
                div.style.margin = "10px";
                div.style.padding = "10px";
                div.style.cursor = "pointer";

                for (var key in profileData) {
                    if (key === 'work' && Array.isArray(profileData.work)) {
                        var workList = document.createElement('ul');
                        workList.innerHTML = "<strong>Work:</strong>";
                        profileData.work.forEach(function(work_item) {
                            var li = document.createElement('li');
                            if (work_item.image) {
                                var img = document.createElement('img');
                                img.src = work_item.image.replace("/var/www/html", "");
                                img.alt = "Work Image";
                                img.style.maxWidth = "100px";
                                img.style.maxHeight = "100px";
                                li.appendChild(img);
                                li.appendChild(document.createElement('br'));
                            }
                            if (work_item.desc) {
                                li.innerHTML += "<strong>Description:</strong> " + work_item.desc + "<br>";
                            }
                            if (work_item.date) {
                                li.innerHTML += "<strong>Date:</strong> " + work_item.date + "<br>";
                            }
                            workList.appendChild(li);
                        });
                        div.appendChild(workList);
                    } else {
                        var span = document.createElement('span');
                        span.className = "profile-data";
                        span.innerHTML = "<strong>" + key + ":</strong> " + profileData[key] + "<br>";
                        // Make each data span clickable (for profile navigation)
                        span.style.cursor = "pointer";
                        span.addEventListener("click", function(event) {
                            event.stopPropagation(); // Prevent bubbling to parent .user-profile
                            openProfile(profile_username);
                        });
                        div.appendChild(span);
                    }
                }
                // Also allow clicking anywhere in the card to open the profile
                div.addEventListener("click", function() {
                    openProfile(profile_username);
                });
                container.appendChild(div);
            });
        }

        // Profile opening handler
        function openProfile(profileName) {
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "create_profile_page.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    window.location.href = "profile/" + encodeURIComponent(profileName) + ".php";
                }
            };
            xhr.send("username=" + encodeURIComponent(profileName));
        }
    });
    </script>
</head>
<body>
<?php if (!isset($_SESSION['email'])): ?>
<h2>Register</h2>
<form method="POST">
    First Name: <input type="text" name="first" required><br>
    Last Name: <input type="text" name="last" required><br>
    Email: <input type="email" name="email" required><br>
    Password: <input type="password" name="password" required><br>
    <button name="register">Register</button>
</form>
<h2>Login</h2>
<form method="POST">
    Email: <input type="email" name="email" required><br>
    Password: <input type="password" name="password" required><br>
    <button name="login">Login</button>
</form>
<?php else: ?>
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['first'] . " " . $_SESSION['last']); ?>!</h2>
    <a href="?logout=1">Logout</a>
    
    <!-- Image upload form -->
    <h3>Upload Profile Image</h3>
    <?php if (!empty($image_upload_msg)) { echo '<p>' . htmlspecialchars($image_upload_msg) . '</p>'; } ?>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="image" accept="image/*" required>
        <button type="submit" name="upload_image">Upload Image</button>
    </form>
    <?php
    // Display uploaded image if exists
    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
    $user_dir = "/var/www/html/pusers/" . $safe_first . "_" . $safe_last;
    if (is_dir($user_dir)) {
        // Look for most recent image
        $images = glob($user_dir . "/profile_image_*.*");
        if ($images && count($images) > 0) {
            $latest_image = $images[array_search(max(array_map('filemtime', $images)), array_map('filemtime', $images))];
            // For web display, convert server path to web-accessible path if needed
            $web_path = str_replace("/var/www/html", "", $latest_image);
            echo '<div><img src="' . htmlspecialchars($web_path) . '" alt="Profile Image" style="max-width:200px; max-height:200px;"></div>';
        }
    }
    ?>
    <!-- Bio/DOB/Country form -->
    <h3>Update Bio Information</h3>
    <?php if (!empty($profile_extra_msg)) { echo '<p>' . htmlspecialchars($profile_extra_msg) . '</p>'; } ?>
    <form method="POST">
        Bio: <textarea name="bio" rows="3" cols="40"></textarea><br>
        Date of Birth: <input type="date" name="dob"><br>
        Country: <input type="text" name="country"><br>
        <button type="submit" name="update_profile_extra">Save Info</button>
    </form>

    <!-- Work upload form -->
    <h3>Upload Work</h3>
    <?php if (!empty($work_upload_msg)) { echo '<p>' . htmlspecialchars($work_upload_msg) . '</p>'; } ?>
    <form method="POST" enctype="multipart/form-data">
        Description: <textarea name="work_desc" rows="2" cols="40" required></textarea><br>
        Date of Work: <input type="date" name="work_date" required><br>
        Work Image: <input type="file" name="work_image" accept="image/*" required><br>
        <button type="submit" name="upload_work">Upload Work</button>
    </form>
<?php endif; ?>


<div id="user-profiles"></div>
</body>
</html>

