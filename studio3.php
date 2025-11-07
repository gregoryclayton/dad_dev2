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
function update_user_profile_extra($first, $last, $bio, $dob, $country, $genre, $nickname, $bio2, $fact1, $fact2) {
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
        file_put_contents($profile_path, json_encode($profile, JSON_PRETTY_PRINT));
    }
}

// Helper: add work to profile.json
function add_user_work($first, $last, $desc, $date, $image_path, $uuid) {
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
            "image" => $image_path,
            "uuid" => $uuid
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
    header("Location: home.php");
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
        $work_dir = $user_dir . "/work";
        if (!is_dir($work_dir)) {
            mkdir($work_dir, 0755, true);
        }
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($_FILES['image']['tmp_name']);
        if (!in_array($file_type, $allowed_types)) {
            $image_upload_msg = "Only JPG, PNG, and GIF files are allowed.";
        } else {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $target_file = $work_dir . "/profile_image_" . time() . "." . $ext;
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
    $genre = isset($_POST['genre']) ? $_POST['genre'] : "";
    $nickname = isset($_POST['nickname']) ? $_POST['nickname'] : "";
    $bio2 = isset($_POST['bio2']) ? $_POST['bio2'] : "";
    $fact1 = isset($_POST['fact1']) ? $_POST['fact1'] : "";
    $fact2 = isset($_POST['fact2']) ? $_POST['fact2'] : "";
    update_user_profile_extra($_SESSION['first'], $_SESSION['last'], $bio, $dob, $country, $genre, $nickname, $bio2, $fact1, $fact2);
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
    $work_dir = $user_dir . "/work";
    if (!is_dir($work_dir)) {
        mkdir($work_dir, 0755, true);
    }
    $image_path = "";
    if (isset($_FILES['work_image']) && $_FILES['work_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($_FILES['work_image']['tmp_name']);
        if (!in_array($file_type, $allowed_types)) {
            $work_upload_msg = "Only JPG, PNG, and GIF files are allowed for work image.";
        } else {
            $ext = pathinfo($_FILES['work_image']['name'], PATHINFO_EXTENSION);
            $uuid = generateUUID();
            $target_file = $work_dir . "/work_image_" . $uuid . "." . $ext;
            if (move_uploaded_file($_FILES['work_image']['tmp_name'], $target_file)) {
                $image_path = $target_file;
                add_user_work($_SESSION['first'], $_SESSION['last'], $desc, $date, $image_path, $uuid);
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

<!DOCTYPE html>
<html>
<head>
    <title>Studio Management</title>
    <link rel="stylesheet" type="text/css" href="style.css">
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
        .form-container button {
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
        }
        .form-container button:hover {
            background-color: #d66a6a;
        }
        .form-message {
            margin-top: 10px;
            font-style: italic;
            color: #555;
        }
        .login-form-container {
            max-width: 400px;
            margin: 40px auto;
        }
        .welcome-header {
            text-align: center;
        }
        .welcome-header h2 { margin-bottom: 5px; }
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
            // --- Fetch current user's profile data to use as placeholders ---
            $safe_first_session = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
            $safe_last_session = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
            $user_profile_path = "/var/www/html/pusers/" . $safe_first_session . "_" . $safe_last_session . "/profile.json";
            $current_profile_data = [];
            if (file_exists($user_profile_path)) {
                $current_profile_data = json_decode(file_get_contents($user_profile_path), true);
            }
        ?>
            <div class="welcome-header">
                <h2>Welcome to Your Studio, <?php echo htmlspecialchars($_SESSION['first']); ?>!</h2>
                <p><a href="?logout=1">Logout</a></p>
            </div>
            
            <div class="studio-grid">
                <div class="form-container">
                    <h3>Update Bio Information</h3>
                    <?php if (!empty($profile_extra_msg)) { echo '<p class="form-message">' . htmlspecialchars($profile_extra_msg) . '</p>'; } ?>
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
                            <label for="genre">Genre</label>
                            <input id="genre" type="text" name="genre" placeholder="e.g., Digital Painting, 3D Art" value="<?php echo htmlspecialchars($current_profile_data['genre'] ?? ''); ?>">
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
                        <button type="submit" name="update_profile_extra">Save Info</button>
                    </form>
                </div>

                <div style="display: flex; flex-direction: column; gap: 24px;">
                    <div class="form-container">
                        <h3>Upload Profile Image</h3>
                        <?php if (!empty($image_upload_msg)) { echo '<p class="form-message">' . htmlspecialchars($image_upload_msg) . '</p>'; } ?>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-row">
                                <label for="image">Select Image</label>
                                <input id="image" type="file" name="image" accept="image/*" required>
                            </div>
                            <button type="submit" name="upload_image">Upload Image</button>
                        </form>
                        <?php
                        $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
                        $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
                        $user_dir = "/var/www/html/pusers/" . $safe_first . "_" . $safe_last;
                        $work_dir = $user_dir . "/work";
                        if (is_dir($work_dir)) {
                            $images = glob($work_dir . "/profile_image_*.*");
                            if ($images && count($images) > 0) {
                                usort($images, function($a, $b) { return filemtime($b) - filemtime($a); });
                                $latest_image = $images[0];
                                $web_path = str_replace("/var/www/html", "", $latest_image);
                                echo '<div style="margin-top:15px;"><img src="' . htmlspecialchars($web_path) . '" alt="Profile Image" style="max-width:150px; border-radius:8px;"></div>';
                            }
                        }
                        ?>
                    </div>

                    <div class="form-container">
                        <h3>Upload New Work</h3>
                        <?php if (!empty($work_upload_msg)) { echo '<p class="form-message">' . htmlspecialchars($work_upload_msg) . '</p>'; } ?>
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
                                <label for="work_image">Work Image</label>
                                <input id="work_image" type="file" name="work_image" accept="image/*" required>
                            </div>
                            <button type="submit" name="upload_work">Upload Work</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>





