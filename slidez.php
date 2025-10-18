<?php
// --- SLIDESHOW IMAGES FROM pusers/*/work ---
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
?>
