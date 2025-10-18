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


// --- SLIDESHOW IMAGES FROM puserswork ---
$images = [];
$pusersDir = __DIR__ . '/pusers';

if (is_dir($pusersDir)) {
    $userFolders = scandir($pusersDir);
    foreach ($userFolders as $userFolder) {
        if ($userFolder === '.' || $userFolder === '..') continue;
        $workDir = $pusersDir . '/' . $userFolder . '/work';
        if (is_dir($workDir)) {
            $workFiles = scandir($workDir);
            foreach ($workFiles as $file) {
                if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $file)) {
                    $images[] = 'p-users/' . $userFolder . '/work/' . $file;
                }
            }
        }
    }
}
// Randomize the order of images
shuffle($images);


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
            header("Location: home.php");
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
    header("Location: home.php");
    exit();
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
   <link rel="stylesheet" type="text/css" href="style.css">
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

<div class="navbar">
    <div class="navbarbtns">
        <div class="navbtn"><a href="register.php">register</a></div>
         <div class="navbtn"><a href="studio3.php">studio</a></div>
        <div class="navbtn"><a href="database.php">database</a></div>
    </div>
</div>
    
<?php if (!isset($_SESSION['email'])): ?>

<h2>Login</h2>
<form method="POST">
    Email: <input type="email" name="email" required><br>
    Password: <input type="password" name="password" required><br>
    <button name="login">Login</button>
</form>
<?php else: ?>
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['first'] . " " . $_SESSION['last']); ?>!</h2>
    <a href="?logout=1">Logout</a>
    
    
<?php endif; ?>




  <!-- Slideshow container -->
<div id="slideshow-container" style="position:relative;">
  <img id="slideshow-img" src="" alt="Slideshow photo" style="object-fit: cover; width:100%; border-radius:7px; height:550px; transition:0.1s;">
 
</div>    


<!-- User profiles array selection at bottom -->
<div id="user-profiles"></div>


<script>
  
//slideshow variables, php so I left it here, then interval controls

    var images = <?php echo json_encode($images, JSON_PRETTY_PRINT); ?>;
    var current = 0;
    var timer = null;
    var imgElem = document.getElementById('slideshow-img');
    //var captionElem = document.getElementById('slideshow-caption');
    //var prevBtn = document.getElementById('prev-btn');
   // var nextBtn = document.getElementById('next-btn');
    var interval = 77000;

    function showImage(idx) {
      if (!images.length) {
        imgElem.src = '';
        imgElem.alt = 'No photos found';
        captionElem.textContent = 'No photos found in folder.';
        return;
      }
      current = (idx + images.length) % images.length;
      imgElem.src = images[current];
      imgElem.alt = 'Photo ' + (current + 1);
      //captionElem.textContent = 'Photo ' + (current + 1) + ' of ' + images.length;
    }

    function nextImage() { showImage(current + 1); }
    function prevImage() { showImage(current - 1); }

    //prevBtn.onclick = function() { prevImage(); resetTimer(); }
    //nextBtn.onclick = function() { nextImage(); resetTimer(); }

    function startTimer() { if (timer) clearInterval(timer); timer = setInterval(nextImage, interval); }
    function resetTimer() { startTimer(); }

    showImage(0);
    startTimer();

    // Add this JS below your existing slideshow JS
imgElem.onclick = function () {
  showModal(current);
};

function getImageInfo(path) {
  // Example: "p-users/username/work/image.jpg"
  var info = {};
  var parts = path.split('/');
  if (parts.length >= 4) {
    info.userFolder = parts[1]; // username or user folder
    info.filename = parts[3];
    info.relativePath = path;
  } else {
    info.filename = path.split('/').pop();
    info.relativePath = path;
  }
  return info;
}

function showModal(idx) {
  var modal = document.getElementById('slideModal');
  var modalImg = document.getElementById('modalImg');
  var modalInfo = document.getElementById('modalInfo');
  var imgPath = images[idx];
  modalImg.src = imgPath;
  var info = getImageInfo(imgPath);
  // You can expand this info if you have more data
  modalInfo.innerHTML = `
    <div style="font-weight:bold; font-size:1.1em;">${info.filename}</div>
    <div style="color:#777; margin-top:2px;">User Folder: ${info.userFolder ? info.userFolder : 'Unknown'}</div>
    <div style="font-size:0.95em; color:#aaa;">Path: ${info.relativePath}</div>
  `;
  modal.style.display = 'flex';
}

// Close modal on click of close button or background
document.getElementById('closeSlideModal').onclick = function() {
  document.getElementById('slideModal').style.display = 'none';
};
document.getElementById('slideModal').onclick = function(e) {
  if (e.target === this) this.style.display = 'none';
};

</script>
   
</body>
</html>
