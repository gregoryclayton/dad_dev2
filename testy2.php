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
        "created_at" => date("Y-m-d H:i:s")
    ];
    file_put_contents($user_dir . "/profile.json", json_encode($profile, JSON_PRETTY_PRINT));
    return $user_dir;
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
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register/Login Example</title>
    <script>
    // Add event delegation for user-profile click
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById('user-profiles').addEventListener('click', function(e) {
            var target = e.target;
            // Click on any data display inside user-profile
            while (target && !target.classList.contains('user-profile')) {
                // If a profile-data span, traverse up
                target = target.parentElement;
            }
            if (target && target.classList.contains('user-profile')) {
                var profileName = target.getAttribute('data-username');
                if (profileName) {
                    window.location.href = "profile/" + encodeURIComponent(profileName) + ".php";
                }
            }
        });
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
<?php endif; ?>

<?php    
$baseDir = "/var/www/html/pusers2";

echo '<div id="user-profiles">';
if (is_dir($baseDir)) {
    $dirs = glob($baseDir . '/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $profilePath = $dir . "/profile.json";
        if (file_exists($profilePath)) {
            $profileData = json_decode(file_get_contents($profilePath), true);
            if ($profileData) {
                // Compose the username for linking (first_last)
                $safe_first = isset($profileData['first']) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profileData['first']) : '';
                $safe_last = isset($profileData['last']) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profileData['last']) : '';
                $profile_username = $safe_first . "_" . $safe_last;
                echo '<div class="user-profile" data-username="' . htmlspecialchars($profile_username) . '" style="border:1px solid #ccc; margin:10px; padding:10px; cursor:pointer;">';
                foreach ($profileData as $key => $value) {
                    echo "<span class='profile-data'><strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars($value) . "<br></span>";
                }
                echo '</div>';
            }
        }
    }
} else {
    echo "User profiles directory not found.";
}
echo '</div>';
    ?>
</body>
</html>
