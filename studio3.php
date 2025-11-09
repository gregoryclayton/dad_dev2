<?php
// Database credentials
include 'connection.php';

// Start session for login/logout and flash messages
session_start();

// Connect to MySQL
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

function set_flash_message($message, $type = 'success') {
    $_SESSION['flash_message'] = ['message' => $message, 'type' => $type];
}

function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message_data = $_SESSION['flash_message'];
        $class = $message_data['type'] === 'error' ? 'form-message-error' : 'form-message';
        echo '<p class="' . $class . '">' . htmlspecialchars($message_data['message']) . '</p>';
        unset($_SESSION['flash_message']);
    }
}

// Helper function to recursively delete a directory
function delete_directory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delete_directory("$dir/$file") : unlink("$dir/$file");
    }
    rmdir($dir);
}

// Handle profile deletion
if (isset($_SESSION['email']) && isset($_POST['delete_profile'])) {
    $email = $_SESSION['email'];
    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
    $user_dir = "/var/www/html/pusers/" . $safe_first . "_" . $safe_last;

    if (is_dir($user_dir)) {
        delete_directory($user_dir);
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->close();

    session_destroy();
    header("Location: home.php?profile_deleted=true");
    exit();
}


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

// Generate a UUID for the work
function generateUUID() {
    if (function_exists('random_bytes')) {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    } else {
        return md5(uniqid(mt_rand(), true));
    }
}

// Helper: update profile.json with extra info
function update_user_profile_extra($first, $last, $bio, $dob, $country, $genre, $nickname, $bio2, $fact1, $fact2, $fact3, $city, $subgenre) {
    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $first);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $last);
    $user_dir = "/var/www/html/pusers/" . $safe_first . "_" . $safe_last;
    $profile_path = $user_dir . "/profile.json";
    if (file_exists($profile_path)) {
        $profile = json_decode(file_get_contents($profile_path), true);
        $profile['bio'] = $bio;
        $profile['dob'] = $dob;
        $profile['country'] = $country;
        $profile['genre'] = $genre;
        $profile['nickname'] = $nickname;
        $profile['bio2'] = $bio2;
        $profile['fact1'] = $fact1;
        $profile['fact2'] = $fact2;
        $profile['fact3'] = $fact3;
        $profile['city'] = $city;
        $profile['subgenre'] = $subgenre;
        file_put_contents($profile_path, json_encode($profile, JSON_PRETTY_PRINT));
    }
}

// Helper: add work to profile.json
function add_user_work($first, $last, $desc, $date, $file_path, $uuid, $file_type) {
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
            "path" => $file_path,
            "type" => $file_type,
            "uuid" => $uuid
        ];
        file_put_contents($profile_path, json_encode($profile, JSON_PRETTY_PRINT));
    }
}

// --- FORM SUBMISSION HANDLING WITH PRG ---

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
            set_flash_message("Logged in successfully!");
        } else {
            set_flash_message("Incorrect password!", 'error');
        }
    } else {
        set_flash_message("No account found with that email.", 'error');
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: home.php");
    exit();
}

// Handle bio/dob/country form submission
if (isset($_SESSION['email']) && isset($_POST['update_profile_extra'])) {
    $bio = $_POST['bio'] ?? ""; $dob = $_POST['dob'] ?? ""; $country = $_POST['country'] ?? "";
    $genre = $_POST['genre'] ?? ""; $nickname = $_POST['nickname'] ?? ""; $bio2 = $_POST['bio2'] ?? "";
    $fact1 = $_POST['fact1'] ?? ""; $fact2 = $_POST['fact2'] ?? ""; $fact3 = $_POST['fact3'] ?? "";
    $city = $_POST['city'] ?? ""; $subgenre = $_POST['subgenre'] ?? "";
    update_user_profile_extra($_SESSION['first'], $_SESSION['last'], $bio, $dob, $country, $genre, $nickname, $bio2, $fact1, $fact2, $fact3, $city, $subgenre);
    set_flash_message("Profile info updated!");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle image upload (if logged in)
if (isset($_SESSION['email']) && isset($_POST['upload_image'])) {
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
        $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
        $user_dir = "/var/www/html/pusers/" . $safe_first . "_" . $safe_last;
        $work_dir = $user_dir . "/work";
        if (!is_dir($work_dir)) mkdir($work_dir, 0755, true);
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array(mime_content_type($_FILES['image']['tmp_name']), $allowed_types)) {
            set_flash_message("Only JPG, PNG, and GIF files are allowed.", 'error');
        } else {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $target_file = $work_dir . "/profile_image_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                set_flash_message("Image uploaded successfully!");
            } else {
                set_flash_message("Failed to upload image.", 'error');
            }
        }
    } else {
        set_flash_message("No file uploaded or upload error.", 'error');
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle work upload
if (isset($_SESSION['email']) && isset($_POST['upload_work'])) {
    $desc = $_POST['work_desc'] ?? "";
    $date = $_POST['work_date'] ?? "";
    
    if (isset($_FILES['work_file']) && $_FILES['work_file']['error'] == 0) {
        $allowed_mimes = ['image/jpeg' => 'image', 'image/png' => 'image', 'image/gif' => 'image', 'audio/mpeg' => 'audio'];
        $mime_type = mime_content_type($_FILES['work_file']['tmp_name']);
        
        if (!array_key_exists($mime_type, $allowed_mimes)) {
            set_flash_message("Only JPG, PNG, GIF, and MP3 files are allowed.", 'error');
        } else {
            $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
            $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
            $user_dir = "/var/www/html/pusers/" . $safe_first . "_" . $safe_last;
            $work_dir = $user_dir . "/work";
            if (!is_dir($work_dir)) mkdir($work_dir, 0755, true);

            $file_type = $allowed_mimes[$mime_type];
            $ext = pathinfo($_FILES['work_file']['name'], PATHINFO_EXTENSION);
            $uuid = generateUUID();
            $target_file = $work_dir . "/work_" . $uuid . "." . $ext;
            
            if (move_uploaded_file($_FILES['work_file']['tmp_name'], $target_file)) {
                $web_path = str_replace("/var/www/html/", "", $target_file);
                add_user_work($_SESSION['first'], $_SESSION['last'], $desc, $date, $web_path, $uuid, $file_type);
                set_flash_message("Work uploaded successfully!");
            } else {
                set_flash_message("Failed to upload work file.", 'error');
            }
        }
    } else {
        set_flash_message("No work file uploaded or upload error.", 'error');
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_SESSION['email'])) {
    $safe_first_session = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
    $safe_last_session = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
    $profile_user_segment = $safe_first_session . "_" . $safe_last_session;
    $user_profile_path = "/var/www/html/pusers/" . $profile_user_segment . "/profile.json";
    $current_profile_data = [];
    if (file_exists($user_profile_path)) {
        $current_profile_data = json_decode(file_get_contents($user_profile_path), true);
    }
    
    // --- Data preparation for the collection gallery ---
    $collection_items = [];
    if (!empty($current_profile_data['selected_works'])) {
        foreach ($current_profile_data['selected_works'] as $work) {
            $work['gallery_type'] = 'work';
            $collection_items[] = $work;
        }
    }
    if (!empty($current_profile_data['selected_profiles'])) {
         foreach ($current_profile_data['selected_profiles'] as $profile) {
            $profile['gallery_type'] = 'profile';
            $collection_items[] = $profile;
        }
    }
}
// Function to get a profile image for the collection
function get_profile_image_for_collection($user_folder) {
    $baseDir = "/var/www/html/pusers";
    $work_dir = $baseDir . '/' . $user_folder . '/work';
    if (is_dir($work_dir)) {
        $candidates = glob($work_dir . "/profile_image_*.*");
        if ($candidates && count($candidates) > 0) {
            usort($candidates, fn($a, $b) => filemtime($b) - filemtime($a));
            return str_replace("/var/www/html", "", $candidates[0]);
        }
        $allImgs = glob($work_dir . "/*.{jpg,jpeg,png,gif}", GLOB_BRACE);
        if ($allImgs && count($allImgs) > 0) {
            usort($allImgs, fn($a, $b) => filemtime($b) - filemtime($a));
            return str_replace("/var/www/html", "", $allImgs[0]);
        }
    }
    return "";
}


?>

<!DOCTYPE html>
<html>
<head>
    <title>Studio Management</title>
    <link rel="stylesheet" type="text/css" href="style.css">

     <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f0f2f5;
            color: #333;
        }
        .studio-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .studio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 24px;
        }
        .form-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.08);
            padding: 24px;
            display: flex;
            flex-direction: column;
        }
        .form-container h3 {
            margin-top: 0;
            font-size: 1.25em;
            border-bottom: 1px solid #e5e5e5;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .form-row {
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
        }
        .form-row label {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 0.9em;
        }
        .form-row input[type="text"],
        .form-row input[type="date"],
        .form-row input[type="email"],
        .form-row input[type="password"],
        .form-row textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1em;
            box-sizing: border-box;
        }
        .form-row input[type="file"] {
            padding: 5px;
        }
        .form-row textarea {
            resize: vertical;
            min-height: 80px;
        }
        .form-container button, .visit-profile-btn {
            background-color: #e27979;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            align-self: flex-start;
            text-decoration: none;
            display: inline-block;
        }
        .form-container button:hover, .visit-profile-btn:hover {
            background-color: #d66a6a;
        }
        .form-message { margin-top: 10px; font-style: italic; color: #27ae60; }
        .form-message-error { margin-top: 10px; font-style: italic; color: #c0392b; }
        .login-form-container {
            max-width: 400px;
            margin: 40px auto;
        }
        .welcome-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .welcome-header h2 { margin-bottom: 5px; }
        .delete-btn {
            background-color: #c0392b;
        }
        .delete-btn:hover {
            background-color: #a53125;
        }
        .studio-gallery-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.08);
            padding: 15px 24px;
            margin-top: 24px;
            margin-bottom: 24px;
        }
        .studio-gallery-container h4 {
            margin-top: 0;
            font-size: 1.1em;
            border-bottom: 1px solid #e5e5e5;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .horizontal-gallery {
            display: flex;
            overflow-x: auto;
            gap: 12px;
            padding-bottom: 15px;
        }
        .studio-work-thumb, .studio-audio-thumb, .studio-profile-thumb {
            width: 120px;
            height: 120px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.09);
            cursor: pointer;
            flex-shrink: 0;
            background-color: #f0f2f5;
            position: relative;
        }
        .studio-work-thumb {
            object-fit: cover;
        }
        .studio-audio-thumb {
             display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:10px; font-size:12px; color:#555;
        }
        .studio-audio-thumb::before {
            content:'🎵'; font-size:36px; margin-bottom:8px;
        }
        .studio-profile-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }
        .studio-profile-thumb .initials-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #9aa3b2;
            background: linear-gradient(135deg,#f3f3f5,#e9eef6);
        }
        .thumb-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.6);
            color: white;
            font-size: 11px;
            padding: 4px 6px;
            text-align: center;
            border-radius: 0 0 8px 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #selectedWorksModal .like-container, #selectedWorksModal .login-to-select { display: none; }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbarbtns">
            <div class="navbtn"><a href="home.php">home</a></div>
            <div class="navbtn"><a href="studio3.php">studio</a></div>
            <div class="navbtn"><a href="database.php">database</a></div>
        </div>
    </div>

    <div class="createButton"><a href="editor.html" target="_blank">CREATE</a></div>
    <div class="studio-container">
        <?php if (!isset($_SESSION['email'])): ?>
            <div class="form-container login-form-container">
                <h3>Login to Your Studio</h3>
                 <?php display_flash_message(); ?>
                <form method="POST">
                    <div class="form-row">
                        <label for="login_email">Email</label>
                        <input id="login_email" type="email" name="email" required>
                    </div>
                    <div class="form-row">
                        <label for="login_pass">Password</label>
                        <input id="login_pass" type="password" name="password" required>
                    </div>
                    <button type="submit" name="login">Login</button>
                </form>
                <p style="text-align:center; margin-top:20px;">Don't have an account? <a href="register.php">Register here</a>.</p>
            </div>
        <?php else: 
            $profile_url = "profile.php?user=" . urlencode($profile_user_segment);
        ?>
            <div class="welcome-header">
                <h2>Welcome to Your Studio, <?php echo htmlspecialchars($_SESSION['first']); ?>!</h2>
                <?php display_flash_message(); ?>
                <p><a href="?logout=1">Logout</a></p>
            </div>

            <?php if (!empty($current_profile_data['work'])): ?>
            <div class="studio-gallery-container">
                <h4>My Work</h4>
                <div class="horizontal-gallery">
                    <?php foreach ($current_profile_data['work'] as $item):
                        $dataAttrs = 'data-path="'.htmlspecialchars($item['path']).'" data-type="'.htmlspecialchars($item['type']).'" data-title="'.htmlspecialchars($item['desc']).'" data-date="'.htmlspecialchars($item['date']).'" data-artist="'.htmlspecialchars($_SESSION['first'].' '.$_SESSION['last']).'" data-profile="'.htmlspecialchars($profile_user_segment).'"';
                         if ($item['type'] === 'image') {
                            echo '<img src="'.htmlspecialchars($item['path']).'" '.$dataAttrs.' class="studio-work-thumb">';
                        } else if ($item['type'] === 'audio') {
                            echo '<div '.$dataAttrs.' class="studio-audio-thumb">'.htmlspecialchars($item['desc']).'</div>';
                        }
                    endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($collection_items)): ?>
            <div class="studio-gallery-container">
                <h4>My Collection</h4>
                <div class="horizontal-gallery">
                    <?php foreach ($collection_items as $item):
                        if ($item['gallery_type'] === 'work') {
                            $item_type = $item['type'] ?? 'image';
                            $dataAttrs = 'data-path="'.htmlspecialchars($item['path']).'" data-type="'.$item_type.'" data-title="'.htmlspecialchars($item['title']).'" data-date="'.htmlspecialchars($item['date']).'" data-artist="'.htmlspecialchars($item['artist']).'" data-profile="'.htmlspecialchars($item['user_folder']).'"';
                            if ($item_type === 'image') {
                                echo '<img src="'.htmlspecialchars($item['path']).'" '.$dataAttrs.' class="studio-work-thumb collection-item">';
                            } else if ($item_type === 'audio') {
                                echo '<div '.$dataAttrs.' class="studio-audio-thumb collection-item">'.htmlspecialchars($item['title']).'</div>';
                            }
                        } elseif ($item['gallery_type'] === 'profile') {
                            $user_folder = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $item['first']) . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $item['last']);
                            $profile_link = 'profile.php?user=' . urlencode($user_folder);
                            $profile_img = get_profile_image_for_collection($user_folder);
                            echo '<a href="'.$profile_link.'" class="studio-profile-thumb">';
                            if ($profile_img) {
                                echo '<img src="'.htmlspecialchars($profile_img).'" alt="'.htmlspecialchars($item['first'].' '.$item['last']).'">';
                            } else {
                                $initials = strtoupper(substr($item['first'], 0, 1) . substr($item['last'], 0, 1));
                                echo '<div class="initials-placeholder">'.$initials.'</div>';
                            }
                            echo '<div class="thumb-overlay">'.htmlspecialchars($item['first'].' '.$item['last']).'</div>';
                            echo '</a>';
                        }
                    endforeach; ?>
                </div>
            </div>
            <?php endif; ?>


            <div style="text-align: center; margin-bottom: 24px;">
                 <a href="<?php echo $profile_url; ?>" class="visit-profile-btn">Visit Profile</a>
            </div>

            <div style="background:#fff; border-radius:12px; box-shadow:0 6px 24px rgba(0,0,0,0.08); padding: 20px; max-width: 500px; margin: 0 auto 24px auto; text-align: center;">
                <h4 style="margin-top:0; font-size: 1.1em; border-bottom: 1px solid #e5e5e5; padding-bottom: 10px; margin-bottom: 15px;">Your Information</h4>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($_SESSION['first'] . ' ' . $_SESSION['last']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['email']); ?></p>
            </div>
            
            <div class="studio-grid">
                <div class="form-container">
                    <h3>Update Bio Information</h3>
                    <form method="POST">
                        <div class="form-row">
                            <label for="bio">Bio</label>
                            <textarea id="bio" name="bio" rows="3"><?php echo htmlspecialchars($current_profile_data['bio'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-row">
                             <label for="bio2">Bio 2</label>
                            <textarea id="bio2" name="bio2" rows="3"><?php echo htmlspecialchars($current_profile_data['bio2'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-row">
                            <label for="dob">Date of Birth</label>
                            <input id="dob" type="date" name="dob" value="<?php echo htmlspecialchars($current_profile_data['dob'] ?? ''); ?>">
                        </div>
                        <div class="form-row">
                            <label for="country">Country</label>
                            <input id="country" type="text" name="country" placeholder="e.g., USA" value="<?php echo htmlspecialchars($current_profile_data['country'] ?? ''); ?>">
                        </div>
                        <div class="form-row">
                            <label for="city">City</label>
                            <input id="city" type="text" name="city" placeholder="e.g., New York" value="<?php echo htmlspecialchars($current_profile_data['city'] ?? ''); ?>">
                        </div>
                        <div class="form-row">
                            <label for="genre">Genre</label>
                            <input id="genre" type="text" name="genre" placeholder="e.g., Digital Painting, 3D Art" value="<?php echo htmlspecialchars($current_profile_data['genre'] ?? ''); ?>">
                        </div>
                        <div class="form-row">
                            <label for="subgenre">Subgenre</label>
                            <input id="subgenre" type="text" name="subgenre" placeholder="e.g., Surrealism, Sci-Fi" value="<?php echo htmlspecialchars($current_profile_data['subgenre'] ?? ''); ?>">
                        </div>
                        <div class="form-row">
                            <label for="nickname">Nickname</label>
                            <input id="nickname" type="text" name="nickname" placeholder="e.g., ArtMaster" value="<?php echo htmlspecialchars($current_profile_data['nickname'] ?? ''); ?>">
                        </div>
                        <div class="form-row">
                            <label for="fact1">Fact 1</label>
                            <input id="fact1" type="text" name="fact1" placeholder="A fun fact about you" value="<?php echo htmlspecialchars($current_profile_data['fact1'] ?? ''); ?>">
                        </div>
                        <div class="form-row">
                            <label for="fact2">Fact 2</label>
                            <input id="fact2" type="text" name="fact2" placeholder="Another interesting fact" value="<?php echo htmlspecialchars($current_profile_data['fact2'] ?? ''); ?>">
                        </div>
                        <div class="form-row">
                            <label for="fact3">Fact 3</label>
                            <input id="fact3" type="text" name="fact3" placeholder="One more fact" value="<?php echo htmlspecialchars($current_profile_data['fact3'] ?? ''); ?>">
                        </div>
                        <button type="submit" name="update_profile_extra">Save Info</button>
                    </form>
                </div>

                <div style="display: flex; flex-direction: column; gap: 24px;">
                    <div class="form-container">
                        <h3>Upload Profile Image</h3>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-row">
                                <label for="image">Select Image</label>
                                <input id="image" type="file" name="image" accept="image/*" required>
                            </div>
                            <button type="submit" name="upload_image">Upload Image</button>
                        </form>
                        <?php
                        $user_dir_path = "/var/www/html/pusers/" . $profile_user_segment;
                        $work_dir_path = $user_dir_path . "/work";
                        if (is_dir($work_dir_path)) {
                            $images = glob($work_dir_path . "/profile_image_*.*");
                            if ($images && count($images) > 0) {
                                usort($images, fn($a, $b) => filemtime($b) <=> filemtime($a));
                                $web_path = str_replace("/var/www/html", "", $images[0]);
                                echo '<div style="margin-top:15px;"><img src="' . htmlspecialchars($web_path) . '" alt="Profile Image" style="max-width:150px; border-radius:8px;"></div>';
                            }
                        }
                        ?>
                    </div>

                    <div class="form-container">
                        <h3>Upload New Work</h3>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-row">
                                <label for="work_desc">Description</label>
                                <textarea id="work_desc" name="work_desc" rows="2" required></textarea>
                            </div>
                            <div class="form-row">
                                <label for="work_date">Date of Work</label>
                                <input id="work_date" type="date" name="work_date" required>
                            </div>
                            <div class="form-row">
                                <label for="work_file">Work File (Image or MP3)</label>
                                <input id="work_file" type="file" name="work_file" accept="image/jpeg,image/png,image/gif,audio/mpeg" required>
                            </div>
                            <button type="submit" name="upload_work">Upload Work</button>
                        </form>
                    </div>
                </div>
            </div>
             <div class="form-container" style="margin-top: 24px;">
                <h3>Delete Profile</h3>
                <p>Warning: This action is irreversible. It will permanently delete your profile, all uploaded work, and all associated data.</p>
                <form method="POST" onsubmit="return confirm('Are you absolutely sure you want to delete your entire profile? This cannot be undone.');">
                    <button type="submit" name="delete_profile" class="delete-btn">Delete My Profile Permanently</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
<div id="selectedWorksModal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.85); align-items:center; justify-content:center;">
  <div id="selectedWorksModalContent" style="background:#fff; border-radius:14px; padding:36px 28px; max-width:90vw; max-height:90vh; box-shadow:0 8px 32px #0005; display:flex; flex-direction:column; align-items:center; position:relative;">
    <span id="closeSelectedWorksModal" style="position:absolute; top:16px; right:24px; color:#333; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
    <img id="selectedWorksModalImg" src="" alt="" style="max-width:80vw; max-height:60vh; border-radius:8px; margin-bottom:22px; display:none;">
    <audio id="selectedWorksModalAudio" controls src="" style="width: 80%; max-width: 400px; margin-bottom: 22px; display:none;"></audio>
    <div id="selectedWorksModalInfo" style="text-align:center; width:100%;"></div>
    <a id="selectedWorksModalProfileBtn" href="#" style="display:inline-block; margin-top:18px; background:#e8bebe; color:#000; padding:0.6em 1.2em; border-radius:8px; text-decoration:none;">Visit Artist's Profile</a>
  </div>
</div>

<script>
    function escapeHtml(s) { return String(s || '').replace(/[&<>"']/g, m => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'})[m]); }

    function openSelectedWorkModal(workDataset) {
        const modal = document.getElementById('selectedWorksModal');
        const imgEl = document.getElementById('selectedWorksModalImg');
        const audioEl = document.getElementById('selectedWorksModalAudio');
        const infoEl = document.getElementById('selectedWorksModalInfo');
        const profileBtn = document.getElementById('selectedWorksModalProfileBtn');

        imgEl.style.display = 'none';
        audioEl.style.display = 'none';
        audioEl.pause(); 
        audioEl.src = '';

        const title = workDataset.title || workDataset.desc || 'Artwork';
        
        if (workDataset.type === 'audio') {
            audioEl.src = workDataset.path || '';
            audioEl.style.display = 'block';
        } else {
            imgEl.src = workDataset.path || '';
            imgEl.alt = title;
            imgEl.style.display = 'block';
        }
        
        infoEl.innerHTML = `<div style="font-weight:bold;font-size:1.1em;">${escapeHtml(title)}</div><div style="color:#666;margin-top:6px;">by ${escapeHtml(workDataset.artist)}</div>${workDataset.date ? `<div style="color:#888;margin-top:6px;">${escapeHtml(workDataset.date)}</div>` : ''}`;
        
        if (profileBtn && workDataset.profile) {
            profileBtn.href = 'profile.php?user=' + encodeURIComponent(workDataset.profile);
            profileBtn.style.display = 'inline-block';
        } else {
            profileBtn.style.display = 'none';
        }

        modal.style.display = 'flex';
    }

    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('.studio-work-thumb, .studio-audio-thumb').forEach(thumb => {
            thumb.addEventListener('click', () => {
                openSelectedWorkModal(thumb.dataset);
            });
        });
        
        var modal = document.getElementById('selectedWorksModal');
        var closeBtn = document.getElementById('closeSelectedWorksModal');
        if(closeBtn) closeBtn.onclick = () => { modal.style.display = 'none'; document.getElementById('selectedWorksModalAudio').pause(); };
        if(modal) modal.onclick = (e) => { if (e.target === modal) { modal.style.display = 'none'; document.getElementById('selectedWorksModalAudio').pause(); } };
    });
</script>

</body>
</html>
