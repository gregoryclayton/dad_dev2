<?php
// Database credentials
include 'connection.php';

// Start session for login/logout
session_start();

// Connect to MySQL
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// --- Handle Safe SQL Download (From JSON Profile Data) ---
if (isset($_GET['download_sql'])) {
    $filename = "artist_profiles_export_" . date("Y-m-d") . ".sql";
    
    // Headers to force download
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    
    echo "-- Artist Profile Export (Generated from JSON)\n";
    echo "-- Date: " . date("Y-m-d H:i:s") . "\n";
    echo "-- This file contains public profile information only.\n\n";
    
    // 1. Create the Table Structure
    echo "CREATE TABLE IF NOT EXISTS `artist_profiles` (\n";
    echo "  `id` int(11) NOT NULL AUTO_INCREMENT,\n";
    echo "  `first_name` varchar(255) DEFAULT NULL,\n";
    echo "  `last_name` varchar(255) DEFAULT NULL,\n";
    echo "  `nickname` varchar(255) DEFAULT NULL,\n";
    echo "  `bio` text,\n";
    echo "  `bio2` text,\n";
    echo "  `country` varchar(100) DEFAULT NULL,\n";
    echo "  `city` varchar(100) DEFAULT NULL,\n";
    echo "  `genre` varchar(100) DEFAULT NULL,\n";
    echo "  `subgenre` varchar(100) DEFAULT NULL,\n";
    echo "  `dob` date DEFAULT NULL,\n";
    echo "  `fact1` text,\n";
    echo "  `fact2` text,\n";
    echo "  `fact3` text,\n";
    echo "  PRIMARY KEY (`id`)\n";
    echo ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";

    echo "LOCK TABLES `artist_profiles` WRITE;\n";

    // 2. Populate Data from JSON files
    $baseDir = "/var/www/html/pusers";
    if (is_dir($baseDir)) {
        $dirs = glob($baseDir . '/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $profilePath = $dir . "/profile.json";
            if (file_exists($profilePath)) {
                $data = json_decode(file_get_contents($profilePath), true);
                if ($data) {
                    // Escape data for SQL
                    $first = isset($data['first']) ? $conn->real_escape_string($data['first']) : '';
                    $last = isset($data['last']) ? $conn->real_escape_string($data['last']) : '';
                    $nick = isset($data['nickname']) ? $conn->real_escape_string($data['nickname']) : '';
                    $bio = isset($data['bio']) ? $conn->real_escape_string($data['bio']) : '';
                    $bio2 = isset($data['bio2']) ? $conn->real_escape_string($data['bio2']) : '';
                    $country = isset($data['country']) ? $conn->real_escape_string($data['country']) : '';
                    $city = isset($data['city']) ? $conn->real_escape_string($data['city']) : '';
                    $genre = isset($data['genre']) ? $conn->real_escape_string($data['genre']) : '';
                    $subgenre = isset($data['subgenre']) ? $conn->real_escape_string($data['subgenre']) : '';
                    $dob = (isset($data['dob']) && !empty($data['dob'])) ? "'" . $conn->real_escape_string($data['dob']) . "'" : "NULL";
                    $f1 = isset($data['fact1']) ? $conn->real_escape_string($data['fact1']) : '';
                    $f2 = isset($data['fact2']) ? $conn->real_escape_string($data['fact2']) : '';
                    $f3 = isset($data['fact3']) ? $conn->real_escape_string($data['fact3']) : '';

                    // Generate INSERT statement (Excluding Email/Sensitive data)
                    echo "INSERT INTO `artist_profiles` (`first_name`, `last_name`, `nickname`, `bio`, `bio2`, `country`, `city`, `genre`, `subgenre`, `dob`, `fact1`, `fact2`, `fact3`) VALUES ('$first', '$last', '$nick', '$bio', '$bio2', '$country', '$city', '$genre', '$subgenre', $dob, '$f1', '$f2', '$f3');\n";
                }
            }
        }
    }
    
    echo "UNLOCK TABLES;\n";
    exit();
}

// --- Handle Login ---
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
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// --- Handle Logout ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// --- API endpoint for selecting a work ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'select_work') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['email'])) {
        echo json_encode(['success' => false, 'message' => 'User not logged in.']);
        exit;
    }

    $workData = isset($_POST['work_data']) ? json_decode($_POST['work_data'], true) : null;
    if (!$workData || !isset($workData['path'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid work data provided.']);
        exit;
    }

    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
    $userProfilePath = __DIR__ . '/pusers/' . $safe_first . '_' . $safe_last . '/profile.json';

    if (file_exists($userProfilePath)) {
        $profile = json_decode(file_get_contents($userProfilePath), true);
        if (!isset($profile['selected_works']) || !is_array($profile['selected_works'])) {
            $profile['selected_works'] = [];
        }

        $isAlreadySelected = false;
        foreach ($profile['selected_works'] as $selected) {
            if (isset($selected['path']) && $selected['path'] === $workData['path']) {
                $isAlreadySelected = true;
                break;
            }
        }

        if (!$isAlreadySelected) {
            $profile['selected_works'][] = $workData;
            file_put_contents($userProfilePath, json_encode($profile, JSON_PRETTY_PRINT));
        }
        
        echo json_encode(['success' => true, 'message' => 'Work selection updated.']);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Logged-in user profile not found.']);
        exit;
    }
}


// --- Data Preparation ---
$baseDir = "/var/www/html/pusers";
$userProfiles = [];
$profile_images_map = [];
$allWorks = []; // Array to hold all works for the list view

if (is_dir($baseDir)) {
    $dirs = glob($baseDir . '/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $profilePath = $dir . "/profile.json";
        if (file_exists($profilePath)) {
            $profileData = json_decode(file_get_contents($profilePath), true);
            if ($profileData) {
                $userProfiles[] = $profileData;
                
                $safe_first = isset($profileData['first']) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profileData['first']) : '';
                $safe_last = isset($profileData['last']) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profileData['last']) : '';
                $user_folder = $safe_first . "_" . $safe_last;
                $work_dir = $baseDir . '/' . $user_folder . '/work';
                $img = "";
                
                // Collect individual works for the toggle view
                if (isset($profileData['work']) && is_array($profileData['work'])) {
                    foreach ($profileData['work'] as $work) {
                        if (isset($work['path'])) {
                            $work['artist'] = $profileData['first'] . " " . $profileData['last'];
                            $work['profile_user'] = $user_folder;
                            // Add metadata to work items for sorting
                            $work['country'] = isset($profileData['country']) ? $profileData['country'] : '';
                            $work['genre'] = isset($profileData['genre']) ? $profileData['genre'] : '';
                            $allWorks[] = $work;
                        }
                    }
                }

                // --- Image Map Logic ---
                if (is_dir($work_dir)) {
                    // Prioritize profile_image_*
                    $candidates = glob($work_dir . "/profile_image_*.*");
                    if ($candidates && count($candidates) > 0) {
                        usort($candidates, function($a, $b) { return filemtime($b) - filemtime($a); });
                        $img = str_replace("/var/www/html", "", $candidates[0]);
                    } else {
                        // Fallback to any other image
                        $allImgs = glob($work_dir . "/*.{jpg,jpeg,png,gif}", GLOB_BRACE);
                        if ($allImgs && count($allImgs) > 0) {
                            usort($allImgs, function($a, $b) { return filemtime($b) - filemtime($a); });
                            $img = str_replace("/var/www/html", "", $allImgs[0]);
                        }
                    }
                }
                $profile_images_map[$user_folder] = $img;
            }
        }
    }
}

// Shuffle works for variety
shuffle($allWorks);

// Get logged-in user's profile for the selection feature
$loggedInUserProfile = null;
if (isset($_SESSION['first']) && isset($_SESSION['last'])) {
    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
    $profile_json_path = "/var/www/html/pusers/" . $safe_first . "_" . $safe_last . "/profile.json";
    if (file_exists($profile_json_path)) {
        $loggedInUserProfile = json_decode(file_get_contents($profile_json_path), true);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Database</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; overflow-x: hidden; }
        
        .user-row {
            display:flex; flex-direction:column; align-items:flex-start; padding:10px 0;
            border-bottom:1px solid #eee; cursor:pointer; width:100%;
        }
        .user-row:hover { background:#f9f9f9; }
        .user-row-main { display:flex; width:100%; align-items:center; padding:0 10px; }
        
        .mini-profile {
            width:40px; height:40px; object-fit:cover; border-radius:8px;
            margin-right:10px; box-shadow:0 2px 8px #0000001f; flex-shrink:0;
        }
        .user-name { font-size:14px; font-family:monospace; }
        .user-submeta { color:#666; font-size:.9em; margin-top:4px; }
        
        /* --- DROPDOWN CSS FIXES FOR MOBILE OVERFLOW --- */
        .profile-dropdown { display:none; width:100%; padding:15px 10px 0; box-sizing: border-box; }
        
        .dropdown-inner { 
            display:flex; flex-direction:column; gap:15px; 
            width: 100%; min-width: 0; /* Fix for flex overflow */
        }
        
        .dropdown-header { 
            display:flex; gap:15px; align-items:center; width: 100%; 
        }
        .dropdown-header > div { 
            flex: 1; min-width: 0; /* Fix for text overflow */
        }
        
        .dropdown-main-image { 
            width:80px; height:80px; border-radius:10px; object-fit:cover; 
            background:#f4f4f4; flex-shrink:0; 
        }
        
        .dropdown-name { 
            font-size:1.4em; font-weight:700; margin:0; 
            word-wrap: break-word; overflow-wrap: break-word; 
        }
        
        .dropdown-meta { 
            margin-top:8px; color:#555; line-height:1.5; font-size:.9em; 
            word-wrap: break-word; overflow-wrap: break-word; 
        }
        
        .dropdown-gallery-title { margin-top:15px; font-weight:600; font-size:1em; }
        
        .dropdown-work-gallery { 
            display:flex; overflow-x:auto; gap:10px; padding:5px 0 10px; 
            width: 100%; /* Constrain width */
            -webkit-overflow-scrolling: touch; 
        }
        
        .dropdown-work-item { display:flex; flex-direction:column; flex-shrink:0; width:120px; }
        .work-image { 
            width:120px; height:120px; object-fit:cover; border-radius:8px; 
            cursor:pointer; box-shadow:0 2px 8px #00000014; 
        }
        
        .work-info { font-size:.85em; padding-top:6px; }
        .work-info .desc { font-weight:600; color:#333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .work-info .date { color:#777; }
        
        .dropdown-body { width: 100%; min-width: 0; }
        /* ---------------------------------------------- */

        .container-container-container { display:grid; align-items:center; justify-items: center; margin-top: 30px; }
        .container-container { border: double; border-radius:20px; padding: 20px; width:90%; display:grid; background-color: #f2e9e9; position: relative; box-sizing: border-box; }
        
        .top-controls { position: absolute; top: 20px; right: 20px; display: flex; gap: 10px; z-index: 10; }
        .view-toggle-btn, .download-sql-btn { padding: 8px 12px; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9em; text-decoration: none; white-space: nowrap; }
        .view-toggle-btn { background-color: #e27979; }
        .download-sql-btn { background-color: #3498db; }

        @media (min-width: 600px) { 
            .dropdown-inner { flex-direction: row; } 
            .dropdown-header { flex-basis: 220px; flex-shrink: 0; flex-direction: column; align-items: flex-start; gap: 0; } 
            .dropdown-main-image { width: 120px; height: 120px; } 
            .dropdown-body { min-width: 0; flex-grow: 1; } 
        }
        @media (max-width: 760px) { 
            .container-container { padding-left: 15px; padding-right: 15px; } 
            #artistSearchBar { width: 100%; } 
            .top-controls { position: static; margin-bottom: 15px; justify-self: end; }
        }
    </style>
    <script>
        var userProfiles = <?php echo json_encode($userProfiles, JSON_UNESCAPED_SLASHES); ?>;
        var allWorks = <?php echo json_encode($allWorks, JSON_UNESCAPED_SLASHES); ?>;
        var profileImagesMap = <?php echo json_encode($profile_images_map, JSON_UNESCAPED_SLASHES); ?>;
        var isLoggedIn = <?php echo json_encode(isset($_SESSION['email'])); ?>;
        var loggedInUserProfile = <?php echo json_encode($loggedInUserProfile); ?>;
        var currentView = 'profiles'; // 'profiles' or 'works'
    </script>
</head>
<body>

<div class="navbar">
    <div class="navbarbtns">
        <div class="navbtn"><a href="home.php">home</a></div>
        <div class="navbtn"><a href="register.php">register</a></div>
        <div class="navbtn"><a href="studio3.php">studio</a></div>
        <div class="navbtn"><a href="database.php">database</a></div>
    </div>
</div>
    
<?php if (!isset($_SESSION['email'])): ?>
    <div style="padding: 10px; text-align: right;">
        <form method="POST" style="display: inline-block;">
            Email: <input type="email" name="email" required>
            Password: <input type="password" name="password" required>
            <button name="login">Login</button>
        </form>
    </div>
<?php else: ?>
    <div style="padding: 10px; text-align: right;">
        Welcome, <strong><?php echo htmlspecialchars($_SESSION['first']); ?></strong>!
        <a href="?logout=1" style="margin-left: 10px;">Logout</a>
    </div>
<?php endif; ?>

<!-- Content Section (Search, Sort, Profiles) -->
<div class="container-container-container">
    <div class="container-container">
        <div class="top-controls">
            <button class="view-toggle-btn" id="viewToggleBtn" onclick="toggleView()">Switch to Works</button>
            <a href="?download_sql=1" class="download-sql-btn">Download SQL</a>
        </div>
        
        <div style="display:flex; justify-content: center; align-items:center;">
            <input type="text" id="artistSearchBar" placeholder="Search artists..." style="width:60vw; padding:0.6em 1em; font-size:1em; border-radius:7px; border:1px solid #ccc;">
        </div>
        <div style="display:flex; justify-content:center; align-items:center; margin:1em 0; flex-wrap: wrap; gap: 5px;" id="sortButtons">
            <button id="sortAlphaBtn" style="padding:0.7em 1.3em; font-family: monospace;">name</button>
            <button id="sortDateBtn" style="padding:0.7em 1.3em; font-family: monospace;">date</button>
            <button id="sortCountryBtn" style="padding:0.7em 1.3em; font-family: monospace;">country</button>
            <button id="sortGenreBtn" style="padding:0.7em 1.3em; font-family: monospace;">genre</button>
        </div>
        <div id="database-content" style="width: 100%;"></div>
    </div>
</div>

<div id="selectedWorksModal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.85); align-items:center; justify-content:center;">
  <div id="selectedWorksModalContent" style="background:#fff; border-radius:14px; padding:36px 28px; max-width:90vw; max-height:90vh; box-shadow:0 8px 32px #0005; display:flex; flex-direction:column; align-items:center; position:relative;">
    <span id="closeSelectedWorksModal" style="position:absolute; top:16px; right:24px; color:#333; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
    <img id="selectedWorksModalImg" src="" alt="" style="max-width:80vw; max-height:60vh; border-radius:8px; margin-bottom:22px; display:none;">
    <audio id="selectedWorksModalAudio" controls src="" style="width: 80%; max-width: 400px; margin-bottom: 22px; display:none;"></audio>
    <div id="selectedWorksModalInfo" style="text-align:center; width:100%;"></div>
    <a id="selectedWorksModalProfileBtn" href="#" style="display:inline-block; margin-top:18px; background:#e8bebe; color:#000; padding:0.6em 1.2em; border-radius:8px; text-decoration:none;">Visit Artist's Profile</a>
    <div class="like-container" style="position:absolute; bottom:36px; right:28px;">
        <div id="like-ui-placeholder"></div>
    </div>
  </div>
</div> 

<script>
    function escapeHtml(s) { return String(s || '').replace(/[&<>"']/g, m => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'})[m]); }

    function toggleView() {
        const btn = document.getElementById('viewToggleBtn');
        const searchInput = document.getElementById('artistSearchBar');
        const sortButtons = document.getElementById('sortButtons');
        
        if (currentView === 'profiles') {
            currentView = 'works';
            btn.innerText = "Switch to Artists";
            searchInput.placeholder = "Search works...";
            sortButtons.style.display = 'flex'; 
            renderWorks(allWorks);
        } else {
            currentView = 'profiles';
            btn.innerText = "Switch to Works";
            searchInput.placeholder = "Search artists...";
            sortButtons.style.display = 'flex';
            renderProfiles(userProfiles);
        }
    }

    function selectWork(workData) {
        if (!isLoggedIn) return;
        const formData = new FormData();
        formData.append('action', 'select_work');
        formData.append('work_data', JSON.stringify(workData));

        fetch('database.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (loggedInUserProfile.selected_works && !loggedInUserProfile.selected_works.some(w => w.path === workData.path)) {
                    loggedInUserProfile.selected_works.push(workData);
                } else if (!loggedInUserProfile.selected_works) {
                    loggedInUserProfile.selected_works = [workData];
                }
                const radio = document.getElementById('selectedWorkLikeRadio');
                if (radio) radio.checked = true;
            }
        });
    }

    function openSelectedWorkModal(workDataset) {
        const modal = document.getElementById('selectedWorksModal');
        const imgEl = document.getElementById('selectedWorksModalImg');
        const audioEl = document.getElementById('selectedWorksModalAudio');
        const infoEl = document.getElementById('selectedWorksModalInfo');
        const profileBtn = document.getElementById('selectedWorksModalProfileBtn');
        const likeUIPlaceholder = document.getElementById('like-ui-placeholder');

        imgEl.style.display = 'none'; audioEl.style.display = 'none';
        audioEl.pause(); audioEl.src = '';
        
        const type = workDataset.type || 'image';
        const title = workDataset.title || 'Artwork';

        if (type === 'audio') {
            audioEl.src = workDataset.path || ''; audioEl.style.display = 'block';
        } else {
            imgEl.src = workDataset.path || ''; imgEl.alt = title; imgEl.style.display = 'block';
        }
        
        infoEl.innerHTML = `<div style="font-weight:bold;font-size:1.1em;">${escapeHtml(title)}</div><div style="color:#666;margin-top:6px;">by ${escapeHtml(workDataset.artist)}</div>${workDataset.date ? `<div style="color:#888;margin-top:6px;">${escapeHtml(workDataset.date)}</div>` : ''}`;
        
        profileBtn.style.display = workDataset.profile ? 'inline-block' : 'none';
        if(workDataset.profile) profileBtn.href = 'profile.php?user=' + encodeURIComponent(workDataset.profile);

        if (isLoggedIn && loggedInUserProfile) {
            const isSelected = loggedInUserProfile.selected_works?.some(sw => sw.path === workDataset.path);
            likeUIPlaceholder.innerHTML = `<input type="radio" name="selectedWorkLike" id="selectedWorkLikeRadio" style="width:20px; height:20px; accent-color:#e27979; cursor:pointer;" ${isSelected ? 'checked' : ''}>`;
            document.getElementById('selectedWorkLikeRadio').onclick = () => selectWork(workDataset);
        } else {
            likeUIPlaceholder.innerHTML = `<div style="display:flex; flex-direction:column; align-items:center; opacity:0.6;">
          <input type="radio" style="width:20px; height:20px; cursor:not-allowed;" disabled>
          <span style="font-size:9px; color:#888; margin-top:4px;">login to select</span>
        </div>`;
        }

        modal.style.display = 'flex';
    }

    function buildDropdownContent(container, profileData, profile_username, imgSrc) {
        let bioHtml = profileData.bio ? `<div><strong>Bio:</strong> ${escapeHtml(profileData.bio)}</div>` : '';
        let workHtml = '';
        if (profileData.work && Array.isArray(profileData.work) && profileData.work.length > 0) {
            workHtml += '<div class="dropdown-gallery-title">Work</div><div class="dropdown-work-gallery">';
            profileData.work.forEach(function(work_item) {
                var workImgSrc = work_item.path || (work_item.image ? work_item.image.replace("/var/www/html", "") : '');
                if(workImgSrc) {
                    const workData = {
                        path: workImgSrc,
                        title: work_item.title || work_item.desc || '',
                        date: work_item.date || '',
                        artist: `${profileData.first || ''} ${profileData.last || ''}`,
                        profile: profile_username,
                        type: work_item.type || 'image'
                    };
                    workHtml += `<div class="dropdown-work-item">
                                    <img src="${escapeHtml(workImgSrc)}" class="work-image" onclick='openSelectedWorkModal(${JSON.stringify(workData)})'>
                                    <div class="work-info">
                                        <div class="desc">${escapeHtml(work_item.title || work_item.desc || '')}</div>
                                        <div class="date">${escapeHtml(work_item.date || '')}</div>
                                    </div>
                                 </div>`;
                }
            });
            workHtml += '</div>';
        }
        
        container.innerHTML = `<div class="dropdown-inner">
            <div class="dropdown-header">
                <img src="${imgSrc || 'placeholder.png'}" class="dropdown-main-image">
                <div>
                    <div class="dropdown-name">${escapeHtml(profileData.first)} ${escapeHtml(profileData.last)}</div>
                    <button class="profile-btn" style="margin-top:10px; padding: 5px 10px; font-size: 0.9em;" onclick="event.stopPropagation(); window.location.href='profile.php?user=${encodeURIComponent(profile_username)}'">Visit Full Profile</button>
                </div>
            </div>
            <div class="dropdown-body">
                <div class="dropdown-meta">${bioHtml}</div>
                ${workHtml}
            </div>
        </div>`;
    }

    function buildWorkDropdownContent(container, workData) {
        var imgHtml = '';
        if (workData.type === 'audio') {
            imgHtml = '<div class="dropdown-main-image" style="display:flex;align-items:center;justify-content:center;font-size:2em;color:#555;">🎵</div>';
        } else {
            imgHtml = `<img src="${escapeHtml(workData.path)}" class="dropdown-main-image" style="cursor:pointer;" onclick='openSelectedWorkModal(${JSON.stringify(workData)})'>`;
        }

        var descHtml = workData.desc ? `<div><strong>Description:</strong> ${escapeHtml(workData.desc)}</div>` : '';
        
        container.innerHTML = `
            <div class="dropdown-inner">
                <div class="dropdown-header">
                    ${imgHtml}
                    <div>
                        <div class="dropdown-name">${escapeHtml(workData.title)}</div>
                        <div class="dropdown-meta">by ${escapeHtml(workData.artist)}</div>
                        <button class="profile-btn" style="margin-top:10px; padding: 5px 10px; font-size: 0.9em;" onclick="event.stopPropagation(); window.location.href='profile.php?user=${encodeURIComponent(workData.profile)}'">Visit Artist</button>
                    </div>
                </div>
                <div class="dropdown-body">
                    <div class="dropdown-meta">
                        ${descHtml}
                        <div style="margin-top:10px; font-size:0.9em; color:#777;">Date: ${escapeHtml(workData.date)}</div>
                        <div style="margin-top:15px;">
                            <button style="padding:6px 12px; border-radius:4px; border:1px solid #ccc; background:#fff; cursor:pointer; font-weight:bold;" onclick='openSelectedWorkModal(${JSON.stringify(workData)})'>View Full / Select</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function renderProfiles(profiles) {
        var container = document.getElementById('database-content');
        container.innerHTML = '';
        
        profiles.forEach(function(profileData) {
            var safe_first = (profileData.first || '').replace(/[^a-zA-Z0-9_\-\.]/g, '_');
            var safe_last = (profileData.last || '').replace(/[^a-zA-Z0-9_\-\.]/g, '_');
            var profile_username = `${safe_first}_${safe_last}`;
            var miniSrc = profileImagesMap[profile_username] || '';
            var submetaParts = [];
            if (profileData.dob) submetaParts.push(`Born: ${profileData.dob.substring(0,4)}`);
            if (profileData.country) submetaParts.push(escapeHtml(profileData.country));
            if (profileData.genre) submetaParts.push(escapeHtml(profileData.genre));

            var row = document.createElement('div');
            row.className = 'user-row';
            row.innerHTML = `<div class="user-row-main">
                ${miniSrc ? `<img src="${escapeHtml(miniSrc)}" alt="${escapeHtml(profile_username)} photo" class="mini-profile">` : '<div class="mini-profile" style="background:#e9eef6;"></div>'}
                <div>
                    <div class="user-name">${escapeHtml(profileData.first)} ${escapeHtml(profileData.last)}</div>
                    <div class="user-submeta">${submetaParts.join(' &bull; ')}</div>
                </div>
            </div>
            <div class="profile-dropdown"></div>`;

            var dropdownContainer = row.querySelector('.profile-dropdown');
            row.querySelector('.user-row-main').addEventListener('click', function(e) {
                e.stopPropagation();
                if (dropdownContainer.style.display === 'block') {
                    dropdownContainer.style.display = 'none';
                    dropdownContainer.innerHTML = '';
                } else {
                    buildDropdownContent(dropdownContainer, profileData, profile_username, miniSrc);
                    dropdownContainer.style.display = 'block';
                }
            });
            container.appendChild(row);
        });
    }

    function renderWorks(works) {
        var container = document.getElementById('database-content');
        container.innerHTML = '';

        if (works.length === 0) {
            container.innerHTML = '<div style="padding: 20px; text-align:center; color:#777;">No works found.</div>';
            return;
        }

        works.forEach(function(work) {
            var workImgSrc = work.path || (work.image ? work.image.replace("/var/www/html", "") : '');
            if (!workImgSrc && work.type !== 'audio') return;

            var workData = {
                path: workImgSrc,
                title: work.title || work.desc || 'Untitled',
                date: work.date || '',
                artist: work.artist || 'Unknown',
                profile: work.profile_user || '',
                type: work.type || 'image',
                desc: work.desc || ''
            };

            var row = document.createElement('div');
            row.className = 'user-row'; // Reuse standard row class

            var thumbHtml = '';
            if (workData.type === 'audio') {
                thumbHtml = '<div class="mini-profile" style="background:#eee; display:flex; align-items:center; justify-content:center; font-size:1.2em;">🎵</div>';
            } else {
                thumbHtml = `<img src="${escapeHtml(workData.path)}" class="mini-profile" alt="work thumb">`;
            }

            row.innerHTML = `
                <div class="user-row-main">
                    ${thumbHtml}
                    <div>
                        <div class="user-name">${escapeHtml(workData.title)}</div>
                        <div class="user-submeta">by ${escapeHtml(workData.artist)} &bull; ${escapeHtml(workData.date)}</div>
                    </div>
                </div>
                <div class="profile-dropdown"></div>
            `;

            var dropdownContainer = row.querySelector('.profile-dropdown');
            row.querySelector('.user-row-main').addEventListener('click', function(e) {
                e.stopPropagation();
                if (dropdownContainer.style.display === 'block') {
                    dropdownContainer.style.display = 'none';
                    dropdownContainer.innerHTML = '';
                } else {
                    buildWorkDropdownContent(dropdownContainer, workData);
                    dropdownContainer.style.display = 'block';
                }
            });

            container.appendChild(row);
        });
    }

    function searchDatabase() {
        var search = (document.getElementById('artistSearchBar').value || "").toLowerCase();
        
        if (currentView === 'profiles') {
            if (!search) { renderProfiles(userProfiles); return; }
            var filtered = userProfiles.filter(p => 
                (p.first && p.first.toLowerCase().includes(search)) ||
                (p.last && p.last.toLowerCase().includes(search)) ||
                (p.country && p.country.toLowerCase().includes(search)) ||
                (p.genre && p.genre.toLowerCase().includes(search))
            );
            renderProfiles(filtered);
        } else {
            if (!search) { renderWorks(allWorks); return; }
            var filtered = allWorks.filter(w => 
                (w.title && w.title.toLowerCase().includes(search)) ||
                (w.desc && w.desc.toLowerCase().includes(search)) ||
                (w.artist && w.artist.toLowerCase().includes(search))
            );
            renderWorks(filtered);
        }
    }
    
    // Robust sorting helper to handle nulls/undefined/case
    function safeSortStr(strA, strB) {
        var a = String(strA || "").toLowerCase().trim();
        var b = String(strB || "").toLowerCase().trim();
        if (a < b) return -1;
        if (a > b) return 1;
        return 0;
    }

    function safeSortDate(dateA, dateB) {
        var dA = dateA ? new Date(dateA).getTime() : 0;
        var dB = dateB ? new Date(dateB).getTime() : 0;
        // Handle invalid dates (NaN) by treating them as 0
        if (isNaN(dA)) dA = 0;
        if (isNaN(dB)) dB = 0;
        return dA - dB;
    }

    function sortAndRender(criteria) {
        if (currentView === 'profiles') {
            let sorted = userProfiles.slice();
            if (criteria === 'alpha') {
                sorted.sort((a, b) => safeSortStr((a.first||"")+" "+(a.last||""), (b.first||"")+" "+(b.last||"")));
            } else if (criteria === 'date') {
                sorted.sort((a, b) => safeSortDate(a.dob, b.dob));
            } else if (criteria === 'country') {
                sorted.sort((a, b) => safeSortStr(a.country, b.country));
            } else if (criteria === 'genre') {
                sorted.sort((a, b) => safeSortStr(a.genre, b.genre));
            }
            renderProfiles(sorted);
        } else {
            let sorted = allWorks.slice();
            if (criteria === 'alpha') { 
                sorted.sort((a, b) => safeSortStr(a.title, b.title));
            } else if (criteria === 'date') { 
                sorted.sort((a, b) => safeSortDate(a.date, b.date));
            } else if (criteria === 'country') { 
                sorted.sort((a, b) => safeSortStr(a.country, b.country));
            } else if (criteria === 'genre') { 
                sorted.sort((a, b) => safeSortStr(a.genre, b.genre));
            }
            renderWorks(sorted);
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        renderProfiles(userProfiles);

        document.getElementById('artistSearchBar').addEventListener('input', searchDatabase);
        
        document.getElementById('sortAlphaBtn').addEventListener('click', () => sortAndRender('alpha'));
        document.getElementById('sortDateBtn').addEventListener('click', () => sortAndRender('date'));
        document.getElementById('sortCountryBtn').addEventListener('click', () => sortAndRender('country'));
        document.getElementById('sortGenreBtn').addEventListener('click', () => sortAndRender('genre'));

        // Modal close logic
        const modal = document.getElementById('selectedWorksModal');
        const closeBtn = document.getElementById('closeSelectedWorksModal');
        if (closeBtn) closeBtn.onclick = () => { modal.style.display = 'none'; document.getElementById('selectedWorksModalAudio').pause(); };
        if (modal) modal.onclick = (e) => { if (e.target === modal) { modal.style.display = 'none'; document.getElementById('selectedWorksModalAudio').pause(); } };
    });
</script>

</body>
</html>
