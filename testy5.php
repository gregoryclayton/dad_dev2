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
                    $images[] = 'pusers/' . $userFolder . '/work/' . $file;
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
   <meta charset="UTF-8">
  <title>digital artist database</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
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

            var profile_images_map = <?php echo json_encode($profile_images_map, JSON_UNESCAPED_SLASHES); ?>;
            
            div.innerHTML = "<strong>" + profileData.first + " " + profileData.last + "</strong><br>"; //+
               // "<span>" + (profileData.email ? profileData.email : "") + "</span><br>";
            
            
            //if (profile_images_map[profile_username] && profile_images_map[profile_username][0]) {
              //  html += '<div><img src="' + profile_images_map[profile_username][0] + '" class="profile-image" alt="Profile Image"></div>';
            //}
            

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
            // html += "<strong>Created At:</strong> " + (profileData.created_at ? profileData.created_at : "") + "<br>";
            if (profileData.bio) html += "<strong>Bio:</strong> " + profileData.bio + "<br>";
            if (profileData.dob) html += "<strong>Date of Birth:</strong> " + profileData.dob + "<br>";
            if (profileData.country) html += "<strong>Country:</strong> " + profileData.country + "<br>";
            // Work images & info
            if (profileData.work && Array.isArray(profileData.work) && profileData.work.length > 0) {
                html += "<strong>Work:</strong><ul class='workList'>";
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

<div style="display:flex;">
  <div class="title-container" id="mainTitleContainer" style="background-image: linear-gradient(135deg, #e27979 60%, #ed8fd1 100%); transition: background-image 0.7s; ">
    <br>
    <a href="index.php" style="text-decoration:none; color: white;">digital <br>artist <br>database</a>
  </div>
  
   <div id="dotMenuContainer" style="position:relative; align-self:end; margin-bottom:50px; margin-left:-30px;">
    <div id="dot" style="color:black; background: linear-gradient(135deg, #e27979 60%, #ed8fd1 100%); transition: background 0.7s;"></div>
    <div id="dotMenu" style="display:none; position:absolute; left:80px; top:-380%; transform:translateX(-50%); background-image: linear-gradient(to bottom right, rgba(226, 121, 121, 0.936), rgba(237, 143, 209, 0.936)); border-radius:50%; box-shadow:0 4px 24px #0002; padding:1.4em 2em; min-width:10px; z-index:0;">
      <!-- Your menu content here -->
     <!-- Add this play icon to the dot menu container -->
<div id="musicPlayIcon" style="display:none; position:absolute; top:7px; right:41px; background: white; border-radius:50%; padding:2px; font-size:10px; width:16px; height:16px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.2);">
  <span style="color:#e27979;">▶</span>
</div>
      <!-- New buttons for changing color -->
      <div style="position: relative;">
  <button id="musicBtn" style="margin-top:1em; background:white; color:#fff; border:none; border-radius:8px; font-family:monospace; font-size:1em; cursor:pointer; display:block; width:10px;" title="Toggle background music"></button>
  <div id="musicPlayIcon" style="display:none; position:absolute; top:-12px; right:-5px; background: white; border-radius:50%; padding:2px; font-size:10px; width:16px; height:16px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.2);">
    <span style="color:#e27979;">▶</span>
  </div>
</div>
      <button id="changeTitleBgBtn" style="margin-top:1em; background:grey; color:#fff; border:none; border-radius:8px; font-family:monospace; font-size:1em; cursor:pointer; display:block; width:10px;"></button>
      <button id="bwThemeBtn" style="margin-top:0.7em; background:lightgrey; color:#fff; border:none; border-radius:8px; padding:0.6em 1.1em; font-family:monospace; font-size:1em; cursor:pointer; display:block; width:10px;"></button>
    </div>
  </div>
  
</div>


<!-- Pop-out menu for quick nav, hidden by default -->
<div id="titleMenuPopout" style="display:none; position:fixed; z-index:10000; top:65px; left:40px; background: white; border-radius:14px; box-shadow:0 4px 24px #0002; padding:1.4em 2em; min-width:50px; font-family:monospace;">
  <div style="display:flex; flex-direction:column; gap:0.5em;">
    <a href="v4.5.php" style="color:#777; text-decoration:none; font-size:1.1em;">home</a>
    <a href="v4.5.php" style="color:#777; text-decoration:none; font-size:1.1em;">about</a>
       <a href="studio.php" style="color:#777; text-decoration:none; font-size:1.1em;">studio</a>
    <a href="signup.php" style="color:#b44; text-decoration:none; font-size:1.1em;">register</a>
    <a href="database.php" style="color:#555; text-decoration:none; font-size:1.1em;">database</a>
   
   
  </div>
</div>



    
<?php if (!isset($_SESSION['email'])): ?>


<form method="POST" style="display:flex; max-width:80vw; justify-content: flex-end; padding:10px; border-bottom: 1px solid #e2e2e2; background: #ffffff00; border-bottom-right-radius:10px; border-top-right-radius:10px;">
    <input style="width:50px" type="email" name="email" placeholder="email" required><br>
    <input style="width:50px" type="password" name="password"  placeholder="password" required><br>
    <button name="login">Login</button>
</form>
<?php else: ?>
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['first'] . " " . $_SESSION['last']); ?>!</h2>
    <a href="?logout=1">Logout</a>
    
    
<?php endif; ?>



<div class="navbar">
    <div class="navbarbtns">
        <div ><a class="navbtn" href="register.php">[register]</a></div>
         <div ><a class="navbtn" href="studio3.php">[studio]</a></div>
        <div ><a class="navbtn" href="database.php">[database]</a></div>
    </div>
</div>






<div class="container-container-container" style="display:grid; align-items:center; justify-items: center;"> 
<div class="container-container" style="border: double; border-radius:20px; padding-top:50px; width:90%; align-items:center; justify-items: center; display:grid;   background-color: #f2e9e9; box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.1);">

<div style="display:flex; justify-content: center; align-items:center;">
  <div>
    <input type="text" id="artistSearchBar" placeholder="Search artists..." style="width:60vw; padding:0.6em 1em; font-size:1em; border-radius:7px; border:1px solid #ccc;">
  </div>
</div>

<!-- SORT BUTTONS AND SEARCH BAR ROW (MODIFIED) -->
<div style="display:flex; justify-content:center; align-items:center; margin:1em 0 1em 0;">
  <!-- SEARCH BAR MOVED TO THE LEFT -->
  
 

  <button id="sortAlphaBtn" style="padding:0.7em 1.3em; font-family: monospace; font-size:1em; color: black; background-color: rgba(255, 255, 255, 0); border:none; border-radius:8px; cursor:pointer;">
    name
  </button>
  <button id="sortDateBtn" style="padding:0.7em 1.3em; font-family: monospace; font-size:1em; background-color: rgba(255, 255, 255, 0); color:black; border:none; border-radius:8px; cursor:pointer;">
    date
  </button>
  <button id="sortCountryBtn" style="padding:0.7em 1.3em; font-family: monospace; font-size:1em; background-color: rgba(255, 255, 255, 0); color:black; border:none; border-radius:8px; cursor:pointer;">
    country
  </button>
  <button id="sortGenreBtn" style="padding:0.7em 1.3em; font-family: monospace; font-size:1em; background-color: rgba(255, 255, 255, 0); color:black; border:none; border-radius:8px; cursor:pointer;">
    genre
  </button>
</div>
 

  <!-- User profiles array selection at bottom -->
<div id="user-profiles"></div>

  <br><br><br><br><br>

</div>
    <br><br><br><br><br>
</div> 

<?php if (!isset($_SESSION['email'])): ?>
<h2>Register</h2>
<form method="POST">
    First Name: <input type="text" name="first" required><br>
    Last Name: <input type="text" name="last" required><br>
    Email: <input type="email" name="email" required><br>
    Password: <input type="password" name="password" required><br>
    <button name="register">Register</button>
</form>

<?php else: ?>
    <h2>You're already logged in <?php echo htmlspecialchars($_SESSION['first'] . " " . $_SESSION['last']); ?>.</h2>
    <a href="?logout=1">Logout</a>
    
    
<?php endif; ?>

<br><br><br><br><br>


<footer style="background:#222; color:#eee; padding:2em 0; text-align:center; font-size:0.95em;">
  <div style="margin-bottom:1em;">
    <nav>
      <a href="/index.php" style="color:#eee; margin:0 15px; text-decoration:none;">home</a>
      <a href="/about.php" style="color:#eee; margin:0 15px; text-decoration:none;">about</a>
      <a href="/signup.php" style="color:#eee; margin:0 15px; text-decoration:none;">sign up</a>
      <a href="/contribute.php" style="color:#eee; margin:0 15px; text-decoration:none;">contribute</a>
      <a href="/database.php" style="color:#eee; margin:0 15px; text-decoration:none;">database</a>
      
    </nav>
  </div>
  <div style="margin-bottom:1em;">
    <a href="https://discord.com/" target="_blank" rel="noopener" style="margin:0 8px;">
      <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/discord.svg" alt="Twitter" height="22" style="vertical-align:middle; filter:invert(1);">
    </a>
    <a href="https://facebook.com/" target="_blank" rel="noopener" style="margin:0 8px;">
      <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/facebook.svg" alt="Facebook" height="22" style="vertical-align:middle; filter:invert(1);">
    </a>
    <a href="https://instagram.com/" target="_blank" rel="noopener" style="margin:0 8px;">
      <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/instagram.svg" alt="Instagram" height="22" style="vertical-align:middle; filter:invert(1);">
    </a>
    <a href="https://github.com/" target="_blank" rel="noopener" style="margin:0 8px;">
      <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/github.svg" alt="GitHub" height="22" style="vertical-align:middle; filter:invert(1);">
    </a>
  </div>
  <div>
    &copy; 2025 Digital Artist Database. All Rights Reserved.
  </div>
</footer>


<!-- Slide Modal (work card reveal) -->
<div id="slideModal" style="display:none; position:fixed; top:0; left:0;right:0;bottom:0; z-index:9999; background:rgba(0,0,0,0.7); align-items:center; justify-content:center;">
  <div id="slideCard" style="background:white; border-radius:14px; padding:24px 28px; max-width:90vw; max-height:90vh; box-shadow:0 8px 32px #0005; display:flex; flex-direction:column; align-items:center; position:relative;">
    <button id="closeSlideModal" style="position:absolute; top:12px; right:18px; font-size:1.3em; background:none; border:none; color:#333; cursor:pointer;">×</button>
    <img id="modalImg" src="" alt="Image" style="max-width:80vw; max-height:60vh; border-radius:8px; margin-bottom:22px;">
    <div id="modalInfo" style="text-align:center; width:100%;">
      <h2 id="modalTitle" style="color:black; margin-bottom:8px; font-size:24px;"></h2>
      <p id="modalDate" style="color:black; margin-bottom:12px; font-size:16px;"></p>
      <p id="modalArtist" style="color:black; font-weight:bold; font-size:18px;"></p>
    </div>

    <div style="position: absolute; bottom:18px; right:32px; display: flex; align-items: center;">
  <?php if (isset($_SESSION['user_id'])): ?>
    <input type="radio" name="slideWorkSelect" id="slideWorkRadio" style="width:22px; height:22px; accent-color:#e27979;">
  <?php else: ?>
    <div style="display: flex; flex-direction: column; align-items: center;">
      <input type="radio" name="slideWorkSelect" id="slideWorkRadio" style="width:22px; height:22px; accent-color:#e27979; opacity:0.5; cursor:not-allowed;" disabled>
      <span style="font-size:8px; color:#888; margin-top:3px;">sign in to like</span>
    </div>
  <?php endif; ?>
</div>
    
    <button id="visitProfileBtn" style="margin-top:18px; background:#e8bebe; border:none; border-radius:7px; padding:0.7em 2em; font-family:monospace; font-size:1em; cursor:pointer;">visit profile</button>
  </div>
</div>


    

<!-- Add this modal container for expanded work cards to the HTML part of the file, just before the closing body tag -->
<div id="workModal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.85); overflow:auto;">
  <div style="position:relative; margin:5% auto; padding:20px; width:85%; max-width:900px; animation:modalFadeIn 0.3s;">
    
    <div style="position: absolute; bottom:18px; right:32px; display: flex; align-items: center;">
  <?php if (isset($_SESSION['user_id'])): ?>
    <input type="radio" name="slideWorkSelect" id="slideWorkRadio" style="width:22px; height:22px; accent-color:#e27979;">
  <?php else: ?>
    <div style="display: flex; flex-direction: column; align-items: center;">
      <input type="radio" name="slideWorkSelect" id="slideWorkRadio" style="width:22px; height:22px; accent-color:#e27979; opacity:0.5; cursor:not-allowed;" disabled>
      <span style="font-size:8px; color:#888; margin-top:3px;">sign in to like</span>
    </div>
  <?php endif; ?>
</div>
  
  <span id="closeModal" style="position:absolute; top:10px; right:20px; color:white; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
    <div id="modalContent" style="background:#333; padding:25px; border-radius:15px; color:white;"></div>
  </div>
</div>

<!-- First, add this full-screen image container element before the closing body tag -->
<div id="fullscreenImage" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.95); z-index:10000; cursor:zoom-out;">
  <div style="position:absolute; top:15px; right:20px; color:white; font-size:30px; cursor:pointer;" id="closeFullscreen">&times;</div>
  <img id="fullscreenImg" src="" alt="Fullscreen Image" style="position:absolute; top:0; left:0; right:0; bottom:0; margin:auto; max-width:95vw; max-height:95vh; object-fit:contain; transition:all 0.3s ease;">
</div>

    
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

<script>
    
   

document.addEventListener('DOMContentLoaded', function() {
  var closeBtn = document.getElementById('closeSlideModal');
  var modal = document.getElementById('slideModal');
  var visitProfileBtn = document.getElementById('visitProfileBtn');
  var modalUserProfile = "";

  if (closeBtn && modal) {
    closeBtn.onclick = function(e) {
      modal.style.display = 'none';
    };
    // Also allow clicking the dark background to close
    modal.onclick = function(e) {
      if (e.target === modal) modal.style.display = 'none';
    };
  }

  if (visitProfileBtn) {
    visitProfileBtn.onclick = function visiting() {
      if (modalUserProfile) {
        window.location.href = 'profile.php?user=' + encodeURIComponent(modalUserProfile);
      }
    };
  }

  // Updated showModal function with better information display
  window.showModal = function(idx) {
    var modal = document.getElementById('slideModal');
    var modalImg = document.getElementById('modalImg');
    var modalTitle = document.getElementById('modalTitle');
    var modalDate = document.getElementById('modalDate');
    var modalArtist = document.getElementById('modalArtist');
    var imgPath = images[idx];
    
    modalImg.src = imgPath;
    
    // Get enhanced information about the work and artist
    var info = getEnhancedWorkInfo(imgPath);
    
    // Set the information in the modal
    modalTitle.textContent = info.title || 'Untitled Work';
    modalDate.textContent = info.date ? 'Created: ' + info.date : '';
    modalArtist.textContent = info.artistName ? 'By: ' + info.artistName : '';
    
    // Store the user profile for the visit button
    modalUserProfile = info.userFolder || '';
    
    // Display the modal
    modal.style.display = 'flex';
  };

  // Enhanced function to get better work information
  window.getEnhancedWorkInfo = function(path) {
    var info = {
      title: '',
      date: '',
      artistName: '',
      userFolder: ''
    };
    
    // Extract user folder from path (e.g., "p-users/username/work/image.jpg")
    var parts = path.split('/');
    if (parts.length >= 3 && parts[0] === 'pusers') {
      info.userFolder = parts[1];
      
      // Try to convert user folder to artist name (firstname_lastname → Firstname Lastname)
      var nameParts = info.userFolder.split('_');
      if (nameParts.length >= 2) {
        var formattedName = nameParts.map(function(part) {
          return part.charAt(0).toUpperCase() + part.slice(1).toLowerCase();
        }).join(' ');
        info.artistName = formattedName;
      }
    }
    
    // Look for matching work in ARTISTS array for better info
    if (window.userProfiles) {
      var foundMatch = false;
      
      // First try exact match by path
      for (var i = 0; i < userProfiles.length; i++) {
        var artist = userProfiles[i];
        for (var j = 1; j <= 6; j++) {
          var workLink = artist['work' + j + 'link'];
          if (workLink && path.indexOf(workLink.replace(/^\//, '')) !== -1) {
            info.title = artist['work' + j] || '';
            info.date = artist.date || '';
            info.artistName = (artist.firstname + ' ' + artist.lastname).trim();
            foundMatch = true;
            break;
          }
        }
        if (foundMatch) break;
      }
      
      // If no exact match but we have a userFolder, try to match by name
      if (!foundMatch && info.userFolder) {
        for (var i = 0; i < userProfiles.length; i++) {
          var artist = userProfiles[i];
          var artistFolder = (artist.firstname + '_' + artist.lastname).toLowerCase();
          artistFolder = artistFolder.replace(/[^a-z0-9_\-]/g, '_');
          
          if (artistFolder === info.userFolder.toLowerCase()) {
            info.date = artist.date || '';
            info.artistName = (artist.firstname + ' ' + artist.lastname).trim();
            break;
          }
        }
      }
    }
    
    // If we still don't have a title, extract one from the filename
    if (!info.title && parts.length > 0) {
      var filename = parts[parts.length - 1];
      // Remove extension and format nicely
      filename = filename.replace(/\.[^/.]+$/, "");
      filename = filename.replace(/_/g, " ");
      // Capitalize first letter of each word
      filename = filename.replace(/\b\w/g, function(l) { 
        return l.toUpperCase(); 
      });
      info.title = filename;
    }
    
    return info;
  };

  // Attach to slideshow image (if not already)
  var imgElem = document.getElementById('slideshow-img');
  if (imgElem) {
    imgElem.onclick = function() {
      var current = window.current || 0;
      window.showModal(current);
    };
  }
});

    // These will overlay the title/date
    var titleElem = document.getElementById('slideshow-title');
    var dateElem = document.getElementById('slideshow-date');

    function getWorkInfoFromImagePath(path) {
      // Try to match image to an artist and work entry in ARTISTS array
      // ARTISTS and their workNlink fields are available via PHP/JS bridge
      var match = {
        title: '',
        date: ''
      };
      if (!path || !window.userProfiles) return match;
      
      // First try to match with artists' works in the database
      for (var i=0; i<userProfiles.length; ++i) {
        var a = userProfiles[i];
        for (var n=1; n<=6; ++n) {
          var link = a['work'+n+'link'];
          if (link && path.indexOf(link.replace(/^\//,'')) !== -1) {
            match.title = a['work'+n] || '';
            match.date = a['date'] || '';
            return match;
          }
        }
      }
      
      // If no match in database, extract a cleaner title from filename
      var filename = path.split('/').pop();
      // Remove file extension
      filename = filename.replace(/\.[^/.]+$/, "");
      // Replace underscores with spaces
      filename = filename.replace(/_/g, " ");
      // Capitalize first letter of each word
      filename = filename.replace(/\b\w/g, function(l){ return l.toUpperCase() });
      
      match.title = filename;
      return match;
    }

    function showImage(idx) {
      if (!images.length) {
        imgElem.src = '';
        imgElem.alt = 'No photos found';
        if(titleElem) titleElem.textContent = '';
        if(dateElem) dateElem.textContent = '';
        return;
      }
      current = (idx + images.length) % images.length;
      var imgPath = images[current];
      imgElem.src = imgPath;
      imgElem.alt = 'Photo ' + (current + 1);
      
      // Overlay title/date only - no path information
      var info = getWorkInfoFromImagePath(imgPath);
      if(titleElem) titleElem.textContent = info.title || '';
      if(dateElem) dateElem.textContent = info.date || '';
    }

    // ... rest of slideshow code (leave as is) ...
</script>

<script>

// ...existing scripts...

// --- Title-container popout menu functionality ---
document.addEventListener('DOMContentLoaded', function() {
  var titleContainer = document.getElementById('mainTitleContainer');
  var menu = document.getElementById('titleMenuPopout');
  var closeBtn = document.getElementById('closeTitleMenu');

  function closeMenu() {
    menu.style.display = 'none';
  }

  if (titleContainer && menu) {
    titleContainer.style.cursor = "pointer";
    titleContainer.addEventListener('click', function(e) {
      // Position menu relative to the titleContainer (left, below)
      var rect = titleContainer.getBoundingClientRect();
      menu.style.left = (rect.left + window.scrollX + rect.width + 18) + "px";
      menu.style.top = (rect.top + window.scrollY) + "px";
      menu.style.display = 'block';
    });
  }

  // Close button in menu
  if (closeBtn) {
    closeBtn.onclick = function(e) {
      closeMenu();
    };
  }

  // Clicking anywhere outside the menu closes it
  document.addEventListener('mousedown', function(e) {
    if (menu.style.display === 'block' && !menu.contains(e.target) && !titleContainer.contains(e.target)) {
      closeMenu();
    }
  });

  // Escape key closes menu
  document.addEventListener('keydown', function(e) {
    if (e.key === "Escape") closeMenu();
  });
});
</script>
<script src="sortButtons.js"></script>    
   
</body>
</html>
