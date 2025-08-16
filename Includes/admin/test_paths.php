<?php
echo "<h2>Path Testing from Admin Directory</h2>";

// Test different path formats
$testPaths = [
    '../citizen/uploads/Image20241217213249.png',
    '../citizen/uploads/ChatGPT Image Aug 14, 2025, 01_59_47 PM.png',
    '../../Includes/citizen/uploads/Image20241217213249.png',
    '../../Includes/citizen/uploads/ChatGPT Image Aug 14, 2025, 01_59_47 PM.png'
];

foreach ($testPaths as $path) {
    echo "<h3>Testing: " . htmlspecialchars($path) . "</h3>";
    echo "<p>File exists: " . (file_exists($path) ? 'YES' : 'NO') . "</p>";
    if (file_exists($path)) {
        echo "<p><img src='" . htmlspecialchars($path) . "' style='max-width: 100px; max-height: 100px;' alt='Test'></p>";
    }
    echo "<hr>";
}

// Show current directory
echo "<h3>Current Working Directory</h3>";
echo "<p>" . getcwd() . "</p>";

// Test absolute path
echo "<h3>Absolute Path Test</h3>";
$absolutePath = __DIR__ . '/../citizen/uploads/Image20241217213249.png';
echo "<p>Absolute path: " . htmlspecialchars($absolutePath) . "</p>";
echo "<p>File exists: " . (file_exists($absolutePath) ? 'YES' : 'NO') . "</p>";
?>

<p><a href="admin_dashboard.php">Back to Admin Dashboard</a></p>
