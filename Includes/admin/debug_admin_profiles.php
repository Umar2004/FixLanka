<?php
// Debug script for admin profile pictures
session_start();
include('../dbconnect.php');

echo "<h2>Admin Profile Picture Debug</h2>";

// Check current working directory
echo "<h3>Current Directory</h3>";
echo "<p>" . getcwd() . "</p>";

// Check if we can access the citizen uploads directories
echo "<h3>Directory Check</h3>";
$citizen_uploads = '../citizen/uploads/';
$profilepics_dir = '../citizen/uploads/profilepics/';

echo "<p>Checking: " . $citizen_uploads . "</p>";
echo "<p>Directory exists: " . (is_dir($citizen_uploads) ? 'YES' : 'NO') . "</p>";

echo "<p>Checking: " . $profilepics_dir . "</p>";
echo "<p>Directory exists: " . (is_dir($profilepics_dir) ? 'YES' : 'NO') . "</p>";

if (is_dir($citizen_uploads)) {
    echo "<h4>Files in ../citizen/uploads/:</h4>";
    $files = scandir($citizen_uploads);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && !is_dir($citizen_uploads . $file)) {
            echo "<p>- " . htmlspecialchars($file) . "</p>";
        }
    }
}

if (is_dir($profilepics_dir)) {
    echo "<h4>Files in ../citizen/uploads/profilepics/:</h4>";
    $files = scandir($profilepics_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && !is_dir($profilepics_dir . $file)) {
            echo "<p>- " . htmlspecialchars($file) . "</p>";
        }
    }
}

// Check profile picture paths from database
echo "<h3>Database Profile Picture Paths</h3>";
$sql = "SELECT user_id, name, profile_picture FROM users WHERE profile_picture IS NOT NULL AND profile_picture != '' LIMIT 5";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>User ID</th><th>Name</th><th>DB Path</th><th>Admin Path</th><th>File Exists</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        $dbPath = $row['profile_picture'];
        
        // Apply the same logic as admin dashboard
        $adminPath = $dbPath;
        if (strpos($adminPath, 'Includes/citizen/uploads/profilepics/') === 0) {
            // New format: Includes/citizen/uploads/profilepics/filename.jpg
            $adminPath = '../../' . $adminPath;
        } elseif (strpos($adminPath, 'Includes/citizen/uploads/') === 0) {
            // Old format: Includes/citizen/uploads/filename.jpg
            $adminPath = '../../' . $adminPath;
        } elseif (strpos($adminPath, 'Includes/citizen/') === 0) {
            // General format: Includes/citizen/uploads/filename.jpg
            $adminPath = '../../' . $adminPath;
        } elseif (strpos($adminPath, '/') === false) {
            // Just filename: filename.jpg (assume it's in new profilepics folder)
            $adminPath = '../../Includes/citizen/uploads/profilepics/' . $adminPath;
        }
        
        $fileExists = file_exists($adminPath) ? 'YES' : 'NO';
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . htmlspecialchars($dbPath) . "</td>";
        echo "<td>" . htmlspecialchars($adminPath) . "</td>";
        echo "<td style='color: " . ($fileExists === 'YES' ? 'green' : 'red') . "'>" . $fileExists . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No users with profile pictures found</p>";
}

// Test a specific image
echo "<h3>Test Image Display</h3>";
$testPath = '../citizen/uploads/Image20241217213249.png';
echo "<p>Test path: " . htmlspecialchars($testPath) . "</p>";
echo "<p>File exists: " . (file_exists($testPath) ? 'YES' : 'NO') . "</p>";
if (file_exists($testPath)) {
    echo "<p><img src='" . htmlspecialchars($testPath) . "' style='max-width: 100px; max-height: 100px;' alt='Test Image'></p>";
}
?>

<p><a href="admin_dashboard.php">Back to Admin Dashboard</a></p>
