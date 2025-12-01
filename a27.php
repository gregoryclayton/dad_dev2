<?php
session_start();

// --- Basic Auth: Only allow 'gregoryclayton' to access this page ---
$allowed_user = 'gregoryclayton';
$admin_password = 'admin_pass'; // Replace with a secure password in production

// Handle logout (works when clicking the "Logout" link)
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    session_regenerate_id(true);
    header("Location: a27.php");
    exit;
}

// If not logged in, show login form and handle login attempts
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Check for form submission
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $posted_user = trim((string)$_POST['username']);
        $posted_pass = (string)$_POST['password'];

        if ($posted_user === $allowed_user && $posted_pass === $admin_password) {
            // Successful login
            $_SESSION['admin_logged_in'] = true;
            // regenerate session id for safety
            session_regenerate_id(true);
            header("Location: a27.php");
            exit;
        } else {
            // Login failed: show login form with error
            $login_error = "Invalid username or password.";
        }
    }

    // Display login form
    $username_val = isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '';
    echo '<!DOCTYPE html><html><head><title>Admin Login</title><style>body{font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f0f2f5;} .login-box{background: white; padding: 2em; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 300px;} input{width: 100%; padding: 8px; margin: 5px 0 15px 0; box-sizing: border-box;} button{width: 100%; padding: 10px; background: #e27979; color: white; border: none; border-radius: 4px; cursor: pointer;} .error{color: red; margin-bottom: 10px; font-size: 0.9em;}</style></head><body>';
    echo '<div class="login-box">';
    echo '<form method="POST" action="a27.php">';
    echo '<h2>Admin Login</h2>';
    if (isset($login_error)) {
        echo '<div class="error">' . htmlspecialchars($login_error) . '</div>';
    }
    echo '<label>Username</label>';
    echo '<input type="text" name="username" required value="' . $username_val . '">';
    echo '<label>Password</label>';
    echo '<input type="password" name="password" required>';
    echo '<button type="submit">Login</button>';
    echo '</form>';
    echo '</div></body></html>';
    exit;
}

// --- API Endpoint for fetching JSON ---
if (isset($_GET['action']) && $_GET['action'] === 'get_json' && isset($_GET['user_folder'])) {
    header('Content-Type: application/json');
    $user_folder = basename($_GET['user_folder']); // Sanitize folder name
    $profile_path = "/var/www/html/pusers/" . $user_folder . "/profile.json";

    if (file_exists($profile_path)) {
        echo file_get_contents($profile_path);
    } else {
        echo json_encode(['error' => 'Profile not found.']);
    }
    exit;
}


// --- Helper Functions (adapted from other files) ---

function set_flash_message($message, $type = 'success') {
    $_SESSION['flash_message'] = ['message' => $message, 'type' => $type];
}

function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message_data = $_SESSION['flash_message'];
        $class = $message_data['type'] === 'error' ? 'form-message-error' : 'form-message-success';
        echo '<div class="' . $class . '">' . htmlspecialchars($message_data['message']) . '</div>';
        unset($_SESSION['flash_message']);
    }
}

function generateUUID() {
    if (function_exists('random_bytes')) {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    return md5(uniqid(mt_rand(), true));
}

// --- Form Handling ---

// 1. Handle New User Creation
if (isset($_POST['create_user'])) {
    $first = trim($_POST['first_name']);
    $last = trim($_POST['last_name']);

    if (!empty($first) && !empty($last)) {
        $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $first);
        $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $last);
        $user_dir = "/var/www/html/pusers/" . $safe_first . "_" . $safe_last;

        if (!is_dir($user_dir)) {
            mkdir($user_dir, 0755, true);
            $profile = [
                "uuid" => generateUUID(),
                "first" => $first,
                "last" => $last,
                "email" => "", // Email is no longer required, so set to empty
                "created_at" => date("Y-m-d H:i:s"),
                "work" => [],
                "selected_works" => [],
                "selected_profiles" => []
            ];
            file_put_contents($user_dir . "/profile.json", json_encode($profile, JSON_PRETTY_PRINT));
            set_flash_message("Successfully created user directory and profile.json for {$first} {$last}.");
        } else {
            set_flash_message("Error: Directory for {$first} {$last} already exists.", 'error');
        }
    } else {
        set_flash_message("Error: First and Last name are required for user creation.", 'error');
    }
    header("Location: a27.php");
    exit();
}

// 2. Handle Profile Image Upload (To Central Directory)
if (isset($_POST['upload_profile_image'])) {
    $user_folder = $_POST['profile_image_user_folder'] ?? null;

    if (!empty($user_folder) && isset($_FILES['profile_image_file']) && $_FILES['profile_image_file']['error'] == 0) {
        $user_dir = "/var/www/html/pusers/" . basename($user_folder);
        $profile_path = $user_dir . "/profile.json";
        
        // Check if user exists
        if (is_dir($user_dir) && file_exists($profile_path)) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $mime_type = mime_content_type($_FILES['profile_image_file']['tmp_name']);

            if (in_array($mime_type, $allowed_types)) {
                // Setup central directory
                $central_work_dir = "/var/www/html/work";
                if (!is_dir($central_work_dir)) {
                    mkdir($central_work_dir, 0755, true);
                }

                $ext = pathinfo($_FILES['profile_image_file']['name'], PATHINFO_EXTENSION);
                $uuid = generateUUID();
                
                // Save to central /work/ folder
                $target_file = $central_work_dir . "/profile_image_" . $uuid . "." . $ext;
                
                if (move_uploaded_file($_FILES['profile_image_file']['tmp_name'], $target_file)) {
                    // Calculate Web Path
                    $web_path = "/work/profile_image_" . $uuid . "." . $ext;

                    // Update Profile JSON
                    $profile = json_decode(file_get_contents($profile_path), true);
                    $profile['profile_image'] = $web_path;
                    file_put_contents($profile_path, json_encode($profile, JSON_PRETTY_PRINT));

                    set_flash_message("Profile image uploaded successfully for {$user_folder}.");
                } else {
                    set_flash_message("Error: Failed to move uploaded file. Check permissions for /var/www/html/work.", 'error');
                }
            } else {
                set_flash_message("Error: Invalid file type. Only JPG, PNG, and GIF are allowed.", 'error');
            }
        } else {
            set_flash_message("Error: Directory or profile for user '{$user_folder}' not found.", 'error');
        }
    } else {
        set_flash_message("Error: You must select a user and a file to upload.", 'error');
    }
    header("Location: a27.php");
    exit();
}


// 3. Handle Work File Upload (To Central Directory)
if (isset($_POST['upload_file'])) {
    $user_folder = $_POST['user_folder'];
    $desc = $_POST['work_desc'] ?? "";
    $date = $_POST['work_date'] ?? "";

    if (!empty($user_folder) && isset($_FILES['work_file']) && $_FILES['work_file']['error'] == 0) {
        $user_dir = "/var/www/html/pusers/" . $user_folder;
        $profile_path = $user_dir . "/profile.json";

        if (file_exists($profile_path)) {
            // Setup central directory
            $central_work_dir = "/var/www/html/work";
            if (!is_dir($central_work_dir)) {
                mkdir($central_work_dir, 0755, true);
            }

            $file_type = 'image'; // Default to image
            $mime = mime_content_type($_FILES['work_file']['tmp_name']);
            if (strpos($mime, 'audio') !== false) {
                $file_type = 'audio';
            }

            $ext = pathinfo($_FILES['work_file']['name'], PATHINFO_EXTENSION);
            $uuid = generateUUID();
            
            // Save to central /work/ folder
            $target_file = $central_work_dir . "/work_" . $uuid . "." . $ext;

            if (move_uploaded_file($_FILES['work_file']['tmp_name'], $target_file)) {
                $profile = json_decode(file_get_contents($profile_path), true);
                if (!isset($profile['work']) || !is_array($profile['work'])) {
                    $profile['work'] = [];
                }

                // The web path is now relative to root
                $web_path = "/work/work_" . $uuid . "." . $ext;

                $profile['work'][] = [
                    "uuid" => $uuid,
                    "desc" => $desc,
                    "date" => $date,
                    "path" => $web_path,
                    "type" => $file_type
                ];
                file_put_contents($profile_path, json_encode($profile, JSON_PRETTY_PRINT));
                set_flash_message("File uploaded successfully to {$user_folder}'s profile.");
            } else {
                set_flash_message("Error: Failed to move uploaded file. Check permissions for /var/www/html/work.", 'error');
            }
        } else {
            set_flash_message("Error: Profile for selected user '{$user_folder}' not found.", 'error');
        }
    } else {
        set_flash_message("Error: You must select a user and a file to upload.", 'error');
    }
    header("Location: a27.php");
    exit();
}

// 4. Handle JSON Profile Save
if (isset($_POST['save_profile_json'])) {
    $user_folder = $_POST['user_folder_to_edit'];
    $json_content = $_POST['json_editor_content'];

    if (!empty($user_folder) && !empty($json_content)) {
        $profile_path = "/var/www/html/pusers/" . $user_folder . "/profile.json";

        if (file_exists($profile_path)) {
            $data = json_decode($json_content);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Pretty-print the JSON before saving
                file_put_contents($profile_path, json_encode($data, JSON_PRETTY_PRINT));
                set_flash_message("Successfully updated profile.json for {$user_folder}.");
            } else {
                set_flash_message("Error: Invalid JSON format. Changes were not saved.", 'error');
            }
        } else {
            set_flash_message("Error: Profile for selected user '{$user_folder}' not found.", 'error');
        }
    } else {
        set_flash_message("Error: No user selected or content was empty.", 'error');
    }
    header("Location: a27.php");
    exit();
}


// --- Data for Forms ---
$pusers_dir = "/var/www/html/pusers";
$user_folders = [];
if (is_dir($pusers_dir)) {
    $user_folders = array_filter(scandir($pusers_dir), function($item) use ($pusers_dir) {
        return is_dir($pusers_dir . '/' . $item) && !in_array($item, ['.', '..']);
    });
    sort($user_folders);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background-color: #f0f2f5; color: #333; margin: 0; }
        .container { max-width: 800px; margin: 2em auto; padding: 2em; background: #fff; border-radius: 12px; box-shadow: 0 6px 24px rgba(0,0,0,0.1); }
        h1, h2 { border-bottom: 1px solid #e5e5e5; padding-bottom: 10px; }
        .form-section { margin-bottom: 2em; padding-bottom: 1em; border-bottom: 1px solid #e5e5e5;}
        .form-section:last-child { border-bottom: none; }
        .form-row { margin-bottom: 1em; }
        .form-row label { display: block; font-weight: 600; margin-bottom: 5px; }
        .form-row input, .form-row select, .form-row textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 1em; box-sizing: border-box; }
        textarea#json-editor-content { min-height: 400px; font-family: monospace; font-size: 0.9em; }
        button { background-color: #e27979; color: white; border: none; padding: 12px 20px; border-radius: 6px; font-size: 1em; font-weight: 600; cursor: pointer; transition: background-color 0.2s; }
        button:hover { background-color: #d66a6a; }
        button:disabled { background-color: #ccc; cursor: not-allowed; }
        .form-message-success { padding: 1em; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 6px; margin-bottom: 1em; }
        .form-message-error { padding: 1em; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 6px; margin-bottom: 1em; }
        .logout-btn { position: absolute; top: 20px; right: 20px; text-decoration: none; background: #555; color: white; padding: 8px 12px; border-radius: 6px; }
    </style>
</head>
<body>

    <a href="?logout=1" class="logout-btn">Logout</a>

    <div class="container">
        <h1>Admin Dashboard</h1>
        <?php display_flash_message(); ?>

        <div class="form-section">
            <h2>Create New User Directory</h2>
            <form method="POST">
                <div class="form-row">
                    <label for="first_name">First Name</label>
                    <input id="first_name" type="text" name="first_name" required>
                </div>
                <div class="form-row">
                    <label for="last_name">Last Name</label>
                    <input id="last_name" type="text" name="last_name" required>
                </div>
                <button type="submit" name="create_user">Create User</button>
            </form>
        </div>

        <div class="form-section">
            <h2>Upload Profile Image</h2>
            <p style="font-size:0.9em; color:#666;">Images will be saved to central storage and linked in profile.json.</p>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <label for="profile_image_user_folder">Select User</label>
                    <select id="profile_image_user_folder" name="profile_image_user_folder" required>
                        <option value="">-- Choose a user --</option>
                        <?php foreach ($user_folders as $folder): ?>
                            <option value="<?php echo htmlspecialchars($folder); ?>"><?php echo htmlspecialchars($folder); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label for="profile_image_file">Profile Image (JPG, PNG, GIF)</label>
                    <input id="profile_image_file" type="file" name="profile_image_file" accept="image/jpeg,image/png,image/gif" required>
                </div>
                <button type="submit" name="upload_profile_image">Upload Profile Image</button>
            </form>
        </div>

        <div class="form-section">
            <h2>Edit User Profile (profile.json)</h2>
            <form method="POST" id="edit-profile-form">
                <div class="form-row">
                    <label for="select-user-to-edit">Select User to Edit</label>
                    <select id="select-user-to-edit" name="user_folder_to_edit" required>
                        <option value="">-- Choose a user --</option>
                        <?php foreach ($user_folders as $folder): ?>
                            <option value="<?php echo htmlspecialchars($folder); ?>"><?php echo htmlspecialchars($folder); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label for="json-editor-content">profile.json</label>
                    <textarea id="json-editor-content" name="json_editor_content" disabled></textarea>
                </div>
                <button type="submit" name="save_profile_json" id="save-json-btn" disabled>Save Changes</button>
            </form>
        </div>

        <div class="form-section">
            <h2>Upload File to Central Work Storage</h2>
            <p style="font-size:0.9em; color:#666;">Files will be saved to central storage and linked in the user's profile.json.</p>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <label for="user_folder">Select User</label>
                    <select id="user_folder" name="user_folder" required>
                        <option value="">-- Choose a user --</option>
                        <?php foreach ($user_folders as $folder): ?>
                            <option value="<?php echo htmlspecialchars($folder); ?>"><?php echo htmlspecialchars($folder); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label for="work_desc">File Description</label>
                    <textarea id="work_desc" name="work_desc" rows="2"></textarea>
                </div>
                <div class="form-row">
                    <label for="work_date">Date of Work</label>
                    <input id="work_date" type="date" name="work_date">
                </div>
                <div class="form-row">
                    <label for="work_file">File (Image or Audio)</label>
                    <input id="work_file" type="file" name="work_file" accept="image/*,audio/*" required>
                </div>
                <button type="submit" name="upload_file">Upload File</button>
            </form>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const userSelect = document.getElementById('select-user-to-edit');
        const jsonEditor = document.getElementById('json-editor-content');
        const saveButton = document.getElementById('save-json-btn');

        userSelect.addEventListener('change', function() {
            const userFolder = this.value;
            if (userFolder) {
                // Fetch the JSON content
                fetch(`a27.php?action=get_json&user_folder=${encodeURIComponent(userFolder)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            jsonEditor.value = `Error: ${data.error}`;
                            jsonEditor.disabled = true;
                            saveButton.disabled = true;
                        } else {
                            // Pretty-print the JSON
                            jsonEditor.value = JSON.stringify(data, null, 2);
                            jsonEditor.disabled = false;
                            saveButton.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching profile:', error);
                        jsonEditor.value = 'Failed to load profile data.';
                        jsonEditor.disabled = true;
                        saveButton.disabled = true;
                    });
            } else {
                jsonEditor.value = '';
                jsonEditor.disabled = true;
                saveButton.disabled = true;
            }
        });
    });
    </script>

</body>
</html>
