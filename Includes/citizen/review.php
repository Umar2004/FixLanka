<?php
session_start();
include '../dbconnect.php';

$message = "";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];

    // Verify user_id exists in users table
    $user_check_stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
    $user_check_stmt->bind_param("i", $user_id);
    $user_check_stmt->execute();
    $user_check_result = $user_check_stmt->get_result();

    if ($user_check_result->num_rows === 0) {
        $message = "Invalid user. Please log in again.";
        $user_check_stmt->close();
    } else {
        $user_check_stmt->close();

        $ref_number = trim($_POST['ref_number']);
        $review_text = trim($_POST['review_text']);

        // Validate inputs
        if (empty($ref_number) || empty($review_text) || !isset($_FILES['before_picture']) || !isset($_FILES['after_picture'])) {
            $message = "Please fill in all required fields and upload both pictures.";
        } else {
            // Handle file uploads
            $upload_dir = 'uploads/reviews/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $before_file = $_FILES['before_picture'];
            $after_file = $_FILES['after_picture'];

            $before_path = $upload_dir . basename($before_file['name']);
            $after_path = $upload_dir . basename($after_file['name']);

            $upload_ok = true;
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

            if (!in_array($before_file['type'], $allowed_types) || !in_array($after_file['type'], $allowed_types)) {
                $message = "Only JPG, PNG, and GIF files are allowed.";
                $upload_ok = false;
            }

            if ($upload_ok) {
                if (move_uploaded_file($before_file['tmp_name'], $before_path) && move_uploaded_file($after_file['tmp_name'], $after_path)) {
                    //Insert the database 
                    $stmt = $conn->prepare("INSERT INTO reviews (user_id, ref_number, before_image, after_image, review_text) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("issss", $user_id, $ref_number, $before_path, $after_path, $review_text);

                    if ($stmt->execute()) {
                        $message = "Review submitted successfully!";
                    } else {
                        $message = "Error submitting review: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $message = "Error uploading files.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Update Your Reviews - FixLanka</title>
  <link rel="stylesheet" href="citizen.css" />
</head>
<body>
  <div class="review-container">
    <h1>UPDATE YOUR REVIEWS</h1>
    <?php if ($message): ?>
      <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data" class="review-form">
      <label for="ref_number">Complain Ref.No</label>
      <input type="text" id="ref_number" name="ref_number" required />
    
      <div class="pictures">
        <div class="picture-upload">
          
          <label for="before_picture">Before picture</label>
          <input type="file" id="before_picture" name="before_picture" accept="image/*" required />
        </div>
        <div class="picture-upload">
          <label for="after_picture">After picture</label>
          <input type="file" id="after_picture" name="after_picture" accept="image/*" required />
        </div>
      </div>

      <label for="review_text">Input your Review</label>
      <textarea id="review_text" name="review_text" rows="4" required></textarea>

      <button type="submit" class="submit-btn">Submit</button>
      <button type="button" class="home-btn" onclick="window.location.href='home.php'">Return to Home</button>
    </form>
  </div>
</body>
</html>
