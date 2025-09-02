<?php


// Set the content type to JSON for all responses
header('Content-Type: application/json');

$commentsFilePath = 'comments.json';

// Ensure the comments file exists and is not empty. If not, create it with an empty array.
if (!file_exists($commentsFilePath) || filesize($commentsFilePath) === 0) {
    file_put_contents($commentsFilePath, '[]');
}

// Use a query parameter to determine the requested action
$action = $_GET['action'] ?? '';

// Handle GET requests to fetch comments
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_comments') {
    // Read and output the contents of the comments file
    echo file_get_contents($commentsFilePath);
    exit;
}

// Handle POST requests to add a new comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_comment') {
    // Get the raw POST data from the request body
    $inputJSON = file_get_contents('php://input');
    // Decode the JSON data into a PHP associative array
    $input = json_decode($inputJSON, true);

    // Basic validation
    if (json_last_error() !== JSON_ERROR_NONE || !isset($input['text']) || trim($input['text']) === '') {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid input. Comment text is required.']);
        exit;
    }

    // Read the existing comments
    $comments = json_decode(file_get_contents($commentsFilePath), true);
    
    // Add the new comment to the array
    $newComment = ['text' => htmlspecialchars($input['text'], ENT_QUOTES, 'UTF-8')];
    $comments[] = $newComment;

    // Write the updated array back to the file
    if (file_put_contents($commentsFilePath, json_encode($comments, JSON_PRETTY_PRINT))) {
        http_response_code(201); // Created
        echo json_encode(['success' => 'Comment added successfully.']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Failed to save comment. Check file permissions.']);
    }
    exit;
}

// If no valid action is found, return a 404 Not Found error
http_response_code(404);
echo json_encode(['error' => 'Endpoint not found.']);
?>