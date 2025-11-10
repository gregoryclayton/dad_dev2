<?php

function logWork($uuid, $desc, $date, $path, $type, $artist, $user_folder) {
    // Corrected path: Store the log file in the 'pusers' directory, which is writable.
    $log_file = '/var/www/html/pusers/works.json';

    $works = [];
    if (file_exists($log_file)) {
        $works_content = file_get_contents($log_file);
        if (!empty($works_content)) {
            $works = json_decode($works_content, true);
        }
        // Ensure it's an array, handle potential decode errors or empty file
        if (!is_array($works)) {
            $works = [];
        }
    }

    $new_work = [
        'uuid' => $uuid,
        'desc' => $desc,
        'date' => $date,
        'path' => $path,
        'type' => $type,
        'artist' => $artist,
        'user_folder' => $user_folder,
        'timestamp' => gmdate('Y-m-d H:i:s')
    ];

    // Add the new work to the array
    $works[] = $new_work;

    // Save the updated array back to the file with pretty printing
    file_put_contents($log_file, json_encode($works, JSON_PRETTY_PRINT));
}

?>
