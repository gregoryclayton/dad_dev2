<?php
// Database credentials
include 'connection.php';

// Start session for login/logout
session_start();

// Connect to MySQL
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Helper: create user folder and profile.json
function create_user_profile($email) {
    $user_dir = "/var/www/html/pusers/" . preg_replace('/[^a-zA-Z0-9_\-\.@]/', '_', $email);
    if (!is_dir($user_dir)) {
        mkdir($user_dir, 0755, true);
    }
    $profile = [
        "email" => $email,
        "created_at" => date("Y-m-d H:i:s")
    ];
    file_put_contents($user_dir . "/profile.json", json_encode($profile, JSON_PRETTY_PRINT));
}

// Handle registration
if (isset($_POST['register'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    // Check if email already exists
    $check = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($check->num_rows > 0) {
        echo "Email already registered!";
    } else {
        $conn->query("INSERT INTO users (email,password) VALUES ('$email','$pass')");
        create_user_profile($email);
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
?>

<!DOCTYPE html>
<html>
<head><title>Register/Login Example</title></head>
<body>
<?php if (!isset($_SESSION['email'])): ?>
<h2>Register</h2>
<form method="POST">
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
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?>!</h2>
    <a href="?logout=1">Logout</a>
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
                echo '<div class="user-profile" style="border:1px solid #ccc; margin:10px; padding:10px;">';
                foreach ($profileData as $key => $value) {
                    echo "<strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars($value) . "<br>";
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
