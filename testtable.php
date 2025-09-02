<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = ""; // Change this if your MySQL password is different
$dbname = "mysql";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if 'testtable' exists
$table_exists = false;
$result = $conn->query("SHOW TABLES LIKE 'testtable'");
if ($result && $result->num_rows > 0) {
    $table_exists = true;
}

// If table doesn't exist, create it
if (!$table_exists) {
    $create_sql = "CREATE TABLE testtable (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if ($conn->query($create_sql) === TRUE) {
        // Optionally insert some sample data
        $conn->query("INSERT INTO testtable (name) VALUES ('Sample 1'),('Sample 2')");
        echo "<p>Table 'testtable' created and sample data inserted.</p>";
    } else {
        die("Error creating table: " . $conn->error);
    }
}

// Fetch data from 'testtable'
$result = $conn->query("SELECT * FROM testtable");

echo "<h2>Data from 'testtable'</h2>";
if ($result && $result->num_rows > 0) {
    echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Created At</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['id']}</td><td>{$row['name']}</td><td>{$row['created_at']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No data found.</p>";
}

$conn->close();
?>