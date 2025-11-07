<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['email']) && isset($_SESSION['first']) && isset($_SESSION['last'])) {
    $safe_first = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['first']);
    $safe_last = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_SESSION['last']);
    $user_dir_name = $safe_first . '_' . $safe_last;
    $profile_path = __DIR__ . '/pusers/' . $user_dir_name . '/profile.json';

    $profile_data = null;
    if (file_exists($profile_path)) {
        $profile_data = json_decode(file_get_contents($profile_path), true);
    }

    echo json_encode([
        'loggedIn' => true,
        'email' => $_SESSION['email'],
        'first' => $_SESSION['first'],
        'last' => $_SESSION['last'],
        'profile' => $profile_data
    ]);
} else {
    echo json_encode(['loggedIn' => false]);
}
exit;
