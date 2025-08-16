<?php
// Debug script to check profile picture paths in database
include('Includes/dbconnect.php');

echo "<h2>Profile Picture Paths in Database</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>User ID</th><th>Name</th><th>Profile Picture Path</th><th>File Exists</th></tr>";

$sql = "SELECT user_id, name, profile_picture FROM users WHERE profile_picture IS NOT NULL AND profile_picture != ''";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $path = $row['profile_picture'];
        $file_exists = file_exists($path) ? 'YES' : 'NO';
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . htmlspecialchars($path) . "</td>";
        echo "<td style='color: " . ($file_exists === 'YES' ? 'green' : 'red') . "'>" . $file_exists . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='4'>No users with profile pictures found</td></tr>";
}

echo "</table>";

echo "<h3>Current Working Directory</h3>";
echo "<p>" . getcwd() . "</p>";

echo "<h3>Directory Listings</h3>";
echo "<h4>Includes/citizen/uploads/</h4>";
if (is_dir('Includes/citizen/uploads/')) {
    $files = scandir('Includes/citizen/uploads/');
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "<p>- " . htmlspecialchars($file) . "</p>";
        }
    }
} else {
    echo "<p>Directory does not exist</p>";
}
?>
