<?php
include('../dbconnect.php');
$msg = "";
$registration_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $mobile = $_POST['mobile'];
    $district = $_POST['district'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Handle profile picture
    $pic_name = $_FILES['profile_pic']['name'];
    $pic_tmp = $_FILES['profile_pic']['tmp_name'];
    $upload_dir = __DIR__ . '/uploads/profilepics/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename to prevent conflicts
    $file_extension = pathinfo($pic_name, PATHINFO_EXTENSION);
    $unique_filename = 'profile_' . uniqid() . '.' . $file_extension;
    
    // Store path relative to website root for proper web access
    $upload_path = "Includes/citizen/uploads/profilepics/" . $unique_filename;
    move_uploaded_file($pic_tmp, $upload_dir . $unique_filename);

    // Default role = citizen
    $sql = "INSERT INTO users (name, email, mobile, district, password_hash, profile_picture, role)
            VALUES (?, ?, ?, ?, ?, ?, 'citizen')";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $name, $email, $mobile, $district, $password, $upload_path);

    if ($stmt->execute()) {
        $registration_success = true;
        $msg = "Registration successful! You can now login.";
    } else {
        $msg = "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>FixLanka - Register</title>
    <link rel="stylesheet" href="citizen.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="submit-complaint-container">
        <h1 class="complaint-title">Register - FixLanka</h1>
        

        
        <form method="POST" enctype="multipart/form-data">
            <label>Full Name:</label>
            <input type="text" name="name" required>

            <label>Email:</label>
            <input type="email" name="email" required>

            <label>Mobile Number:</label>
            <input type="text" name="mobile">

            <label>District:</label>
            <select name="district" required>
                <option value="">-- Select District --</option>
                <option value="Ampara">Ampara</option>
                <option value="Anuradhapura">Anuradhapura</option>
                <option value="Badulla">Badulla</option>
                <option value="Batticaloa">Batticaloa</option>
                <option value="Colombo">Colombo</option>
                <option value="Galle">Galle</option>
                <option value="Gampaha">Gampaha</option>
                <option value="Hambantota">Hambantota</option>
                <option value="Jaffna">Jaffna</option>
                <option value="Kalutara">Kalutara</option>
                <option value="Kandy">Kandy</option>
                <option value="Kegalle">Kegalle</option>
                <option value="Kilinochchi">Kilinochchi</option>
                <option value="Kurunegala">Kurunegala</option>
                <option value="Mannar">Mannar</option>
                <option value="Matale">Matale</option>
                <option value="Matara">Matara</option>
                <option value="Monaragala">Monaragala</option>
                <option value="Mullaitivu">Mullaitivu</option>
                <option value="Nuwara Eliya">Nuwara Eliya</option>
                <option value="Polonnaruwa">Polonnaruwa</option>
                <option value="Puttalam">Puttalam</option>
                <option value="Ratnapura">Ratnapura</option>
                <option value="Trincomalee">Trincomalee</option>
                <option value="Vavuniya">Vavuniya</option>
            </select>

            <label>Password:</label>
            <input type="password" name="password" id="password" required>

            <label>Confirm Password:</label>
            <input type="password" id="confirm_password" required>

            <div style="margin: 10px 0;">
                <input type="checkbox" onclick="togglePassword()" style="width: auto; margin-right: 8px;"> 
                <span style="font-size: 14px;">Show Password</span>
            </div>

            <label>Profile Picture:</label>
            <input type="file" name="profile_pic" accept="image/*" required>

            <button type="submit" class="submit-btn">Register</button>
            <button type="button" class="back-btn" onclick="window.location.href='../../home.php'">Return to Home</button>
        </form>
    </div>

    <script>
// Show success popup and redirect to login if registration was successful
<?php if ($registration_success): ?>
window.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title: 'Registration Successful!',
        text: 'Your account has been created successfully. You will be redirected to the login page.',
        icon: 'success',
        confirmButtonText: 'Go to Login',
        confirmButtonColor: '#6366f1',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '../login.php';
        }
    });
});
<?php endif; ?>

// Show error popup if there was an error
<?php if (!empty($msg) && !$registration_success): ?>
window.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title: 'Registration Failed!',
        text: '<?= addslashes($msg) ?>',
        icon: 'error',
        confirmButtonText: 'Try Again',
        confirmButtonColor: '#ef4444'
    });
});
<?php endif; ?>

function togglePassword() {
    const pass = document.getElementById("password");
    const confirm = document.getElementById("confirm_password");
    pass.type = pass.type === "password" ? "text" : "password";
    confirm.type = confirm.type === "password" ? "text" : "password";
}

document.querySelector("form").addEventListener("submit", function(e) {
    const pass = document.getElementById("password").value;
    const confirm = document.getElementById("confirm_password").value;
    if (pass !== confirm) {
        Swal.fire({
            title: 'Password Mismatch!',
            text: 'Passwords do not match. Please check and try again.',
            icon: 'warning',
            confirmButtonText: 'OK',
            confirmButtonColor: '#f59e42'
        });
        e.preventDefault(); // stop form submission
    }
});
</script>

</body>
</html>
