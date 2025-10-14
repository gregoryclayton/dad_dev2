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
    <title>Register/Login Example</title>
    <style>
        .user-profile { border:1px solid #ccc; margin:10px; padding:10px; cursor:pointer; }
        .profile-image { max-width:200px; max-height:200px; }
        .work-image { max-width:100px; max-height:100px; }
        #mainProfile { border:2px solid #444; margin:20px 0; padding:20px; background:#f6f6f6; }
    </style>
    <script>
    // Make the userProfiles array available to JS for manipulation
    var userProfiles = <?php echo json_encode($userProfiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>;

    // Render user profiles from the JSON array
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
            div.innerHTML = "<strong>" + profileData.first + " " + profileData.last + "</strong><br>" +
                "<span>" + (profileData.email ? profileData.email : "") + "</span><br>";
            div.onclick = function() {
                window.location.href = "profile.php?user=" + encodeURIComponent(profile_username);
            };
            container.appendChild(div);
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        renderProfiles(userProfiles);
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
    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
    $user_dir = "/var/www/html/pusers/" . $safe_first . "_" . $safe_last;
    if (is_dir($user_dir)) {
        $images = glob($user_dir . "/profile_image_*.*");
        if ($images && count($images) > 0) {
            $latest_image = $images[array_search(max(array_map('filemtime', $images)), array_map('filemtime', $images))];
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

    <?php
// This PHP script finds the top 10 most frequently selected works
// across all user profiles and displays them in a horizontally scrolling flexbox gallery
// with modal pop-outs when cards are clicked and detailed info in the modal only.

$baseDir = __DIR__ . '/p-users';
$workCounts = [];
$workDetails = [];

// Get all subdirectories in "p-users"
$subfolders = glob($baseDir . '/*', GLOB_ONLYDIR);

foreach ($subfolders as $subfolder) {
    $profilePath = $subfolder . '/profile.json';
    if (file_exists($profilePath)) {
        $jsonData = file_get_contents($profilePath);
        $profile = json_decode($jsonData, true);

        if (isset($profile['selected_works']) && is_array($profile['selected_works'])) {
            foreach ($profile['selected_works'] as $work) {
                $workKey = $work['path'];
                if (!isset($workCounts[$workKey])) {
                    $workCounts[$workKey] = 0;
                    $workDetails[$workKey] = $work;
                    $workDetails[$workKey]['user_folder'] = basename($subfolder);
                }
                $workCounts[$workKey]++;
            }
        }
    }
}

// Sort works by count descending
arsort($workCounts);

// Get top 10 works
$topWorks = array_slice(array_keys($workCounts), 0, 10);

// Prepare works data for JS modal
$topWorksData = [];
foreach ($topWorks as $workPath) {
    $work = $workDetails[$workPath];
    $work['selected_count'] = $workCounts[$workPath];
    $topWorksData[] = $work;
}
?>

<!-- Modal HTML, place near bottom of page or just below this gallery snippet -->
<div id="selectedWorksModal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.85); align-items:center; justify-content:center;">
  <div id="selectedWorksModalContent" style="background:#fff; border-radius:14px; padding:36px 28px; max-width:90vw; max-height:90vh; box-shadow:0 8px 32px #0005; display:flex; flex-direction:column; align-items:center; position:relative;">
    <span id="closeSelectedWorksModal" style="position:absolute; top:16px; right:24px; color:#333; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
    <img id="selectedWorksModalImg" src="" alt="" style="max-width:80vw; max-height:60vh; border-radius:8px; margin-bottom:22px;">
    <div id="selectedWorksModalInfo" style="text-align:center; width:100%;"></div>
    <a id="selectedWorksModalProfileBtn" href="#" style="display:inline-block; margin-top:18px; background:#e8bebe; border:none; border-radius:7px; padding:0.7em 2em; font-family:monospace; font-size:1em; color:black; text-decoration:none; cursor:pointer;">visit profile</a>
  </div>
</div>

<!-- Place this div below the slideshow in your main HTML file (e.g., v4.5_Version2.php) -->
<div id="selectedWorksGallery" style="
    width:90vw;
    margin:2em auto 0 auto;
    display:flex;
    flex-direction:row;
    gap:40px;
    color:black;
    align-items:stretch;
    overflow-x:auto;
    padding-bottom:16px;
    scrollbar-width:thin;
    scrollbar-color:#e27979 #f9f9f9;
">
<?php foreach ($topWorks as $i => $workPath):
    $work = $workDetails[$workPath];
    ?>
    <div class="selected-work-card" 
         data-idx="<?php echo $i; ?>" 
         style="cursor:pointer; min-width:260px; max-width:320px; flex:0 0 auto; background:#f9f9f9; border-radius:14px; box-shadow:0 4px 14px #0001; padding:20px; text-align:center; display:flex; flex-direction:column; align-items:center; transition:box-shadow 0.2s;">
        <img src="<?php echo htmlspecialchars($work['path']); ?>" alt="<?php echo htmlspecialchars($work['title']); ?>" style="width:100%; max-width:280px; max-height:220px; object-fit:cover; border-radius:10px; box-shadow:0 2px 8px #0002;">
        <div style="margin-top:12px;font-size:1.15em;font-weight:bold;">
            <?php echo htmlspecialchars($work['title']); ?>
        </div>
        <!-- Only work name shown in gallery! -->
    </div>
<?php endforeach; ?>
</div>

<script>
// Prepare data for JS
const selectedWorksData = <?php echo json_encode($topWorksData, JSON_PRETTY_PRINT); ?>;

// Modal logic
document.querySelectorAll('.selected-work-card').forEach(card => {
    card.addEventListener('click', function(e) {
        const idx = parseInt(card.getAttribute('data-idx'));
        const work = selectedWorksData[idx];

        document.getElementById('selectedWorksModalImg').src = work.path;
        document.getElementById('selectedWorksModalImg').alt = work.title;

        let infoHtml = `
            <div style="font-weight:bold; font-size:1.2em;">${work.title}</div>
            <div style="color:#e27979; margin-top:6px;">Selected ${work.selected_count} times</div>
            <div style="color:#555; font-size:1em; margin-top:8px;">${work.artist}</div>
            <div style="color:#888; margin-top:2px; font-size:0.95em;">${work.date ? work.date : (work.timestamp ? (new Date(work.timestamp)).toLocaleDateString() : '')}</div>
            <div style="color:#aaa; font-size:0.92em; margin-top:8px;">${work.user_folder}</div>
            <div style="color:#aaa; font-size:0.92em; margin-top:8px;">${work.path}</div>
        `;
        document.getElementById('selectedWorksModalInfo').innerHTML = infoHtml;

        // Set profile link in modal
        document.getElementById('selectedWorksModalProfileBtn').href = 'profile.php?artist=' + encodeURIComponent(work.user_folder);

        document.getElementById('selectedWorksModal').style.display = 'flex';
    });
});

// Close modal logic
document.getElementById('closeSelectedWorksModal').onclick = function() {
    document.getElementById('selectedWorksModal').style.display = 'none';
};
document.getElementById('selectedWorksModal').onclick = function(e) {
    if (e.target === this) this.style.display = 'none';
};
</script>

<!-- Comprehensive user profile display -->
<div id="mainProfile"></div>

<!-- User profiles array selection at bottom -->
<div id="user-profiles"></div>
</body>
</html>
