<?php
// admin_dashboard.php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../unauthorized.php");
    exit();
}

// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    
    // Prevent admin from deleting themselves
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['message'] = [
            'type' => 'warning', 
            'text' => 'You cannot delete your own admin account.'
        ];
        header("Location: admin_dashboard.php");
        exit();
    }
    
    try {
        // Start transaction for data consistency
        $conn->begin_transaction();
        
        // Get user info before deletion for the success message
        $sql = "SELECT name, email FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_info = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user_info) {
            throw new Exception("User not found.");
        }

        // First, delete password reset requests by this user
        $sql = "DELETE FROM password_reset_requests WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $password_requests_deleted = $stmt->affected_rows;
        $stmt->close();

        // Then, delete reviews by this user
        $sql = "DELETE FROM reviews WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $reviews_deleted = $stmt->affected_rows;
        $stmt->close();

        // Then, delete complaints by this user
        $sql = "DELETE FROM complaints WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $complaints_deleted = $stmt->affected_rows;
        $stmt->close();

        // Now, delete the user
        $sql = "DELETE FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Commit transaction
            $conn->commit();
            
            // Set success message
            $_SESSION['message'] = [
                'type' => 'success', 
                'text' => "User '{$user_info['name']}' ({$user_info['email']}) has been successfully deleted along with {$complaints_deleted} complaints, {$reviews_deleted} reviews, and {$password_requests_deleted} password reset requests."
            ];
        } else {
            throw new Exception("Failed to delete user. User may not exist.");
        }
        $stmt->close();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        // Log detailed error for debugging
        error_log("User deletion failed for user_id: $user_id. Error: " . $e->getMessage());
        error_log("MySQL Error: " . $conn->error);
        
        $_SESSION['message'] = [
            'type' => 'danger', 
            'text' => 'Error deleting user: ' . $e->getMessage() . ' (Check error logs for details)'
        ];
    }
    
    // Redirect to prevent form resubmission and show updated data
    header("Location: admin_dashboard.php");
    exit();
}

// Handle department deletion
if (isset($_POST['delete_department'])) {
    $dept_id = $_POST['dept_id'];

    // Start a transaction for data integrity
    $conn->begin_transaction();

    try {
        // 1. Update complaints linked to this department to set dept_id to NULL
        $sql_update_complaints = "UPDATE complaints SET dept_id = NULL WHERE dept_id = ?";
        $stmt_update = $conn->prepare($sql_update_complaints);
        $stmt_update->bind_param("i", $dept_id);
        $stmt_update->execute();
        $stmt_update->close();

        // 2. Now delete the department
        $sql_delete_department = "DELETE FROM departments WHERE dept_id = ?";
        $stmt_delete = $conn->prepare($sql_delete_department);
        $stmt_delete->bind_param("i", $dept_id);
        $stmt_delete->execute();
        $stmt_delete->close();

        // Commit the transaction
        $conn->commit();

        $_SESSION['message'] = ['type' => 'success', 'text' => 'Department and associated complaints updated successfully.'];

    } catch (mysqli_sql_exception $exception) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error deleting department: ' . $exception->getMessage()];
    }

    // Redirect to prevent form resubmission
    header("Location: admin_dashboard.php");
    exit();
}

// Handle department update
if (isset($_POST['update_department'])) {
    $dept_id = $_POST['dept_id'];
    $dept_name = $_POST['dept_name'];
    $description = $_POST['description'];
    $contact_email = $_POST['contact_email'];
    $status = $_POST['status'];
    
    $sql = "UPDATE departments SET dept_name = ?, description = ?, contact_email = ?, status = ? WHERE dept_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $dept_name, $description, $contact_email, $status, $dept_id);
    $stmt->execute();
}

// Handle password change for department user
if (isset($_POST['change_password'])) {
    $user_id = $_POST['user_id'];
    $new_password = $_POST['new_password'];

    // Validate and hash the new password
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = ? WHERE user_id = ? AND role = 'department'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $hashed_password, $user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            // Password updated successfully
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Department user password updated successfully.'];
        } else {
            // No rows affected (user not found or not a department user)
            $_SESSION['message'] = ['type' => 'warning', 'text' => 'Could not update password. User not found or not a department user.'];
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'New password cannot be empty.'];
    }
    
    // Redirect to prevent form resubmission and show message
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch all users with their details
$users_sql = "SELECT u.*, 
    (SELECT COUNT(*) FROM complaints WHERE user_id = u.user_id) as total_complaints,
    (SELECT COUNT(*) FROM complaints WHERE user_id = u.user_id AND status = 'Resolved') as resolved_complaints
    FROM users u ORDER BY u.created_at DESC";
$users_result = $conn->query($users_sql);

// Fetch all departments with their details
$dept_sql = "SELECT d.*, 
    (SELECT COUNT(*) FROM complaints WHERE dept_id = d.dept_id) as total_complaints,
    (SELECT COUNT(*) FROM complaints WHERE dept_id = d.dept_id AND status = 'Resolved') as resolved_complaints
    FROM departments d ORDER BY d.created_at DESC";
$dept_result = $conn->query($dept_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FixLanka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <!-- SweetAlert Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: '<?php echo $_SESSION['message']['type'] === 'success' ? 'Success!' : ($_SESSION['message']['type'] === 'danger' ? 'Error!' : 'Notice'); ?>',
                    text: '<?php echo addslashes($_SESSION['message']['text']); ?>',
                    icon: '<?php echo $_SESSION['message']['type'] === 'success' ? 'success' : ($_SESSION['message']['type'] === 'danger' ? 'error' : ($_SESSION['message']['type'] === 'warning' ? 'warning' : 'info')); ?>',
                    confirmButtonColor: '#00bfff',
                    timer: <?php echo $_SESSION['message']['type'] === 'success' ? '3000' : '0'; ?>,
                    timerProgressBar: <?php echo $_SESSION['message']['type'] === 'success' ? 'true' : 'false'; ?>
                });
            });
        </script>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="home.php">
                <img src="../../uploads/logos/FIXLANKA_LOGO.png" alt="FixLanka Logo" height="40" class="me-2">
                <span>FixLanka</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_dashboard.php">Dashboard</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="create_department.php">Create Department</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="completed_complaints.php">View Completed Complaints</a></li>
                            <li><a class="dropdown-item" href="../../home.php">FixLanka Home</a></li>
                            <li><a class="dropdown-item" href="../login.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="text-center mb-4">Admin Dashboard</h2>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="quick-action-card">
                <i class="fas fa-plus-circle"></i>
                <h3>Add Department</h3>
                <a href="add_department.php">Add New Department</a>
            </div>

            <div class="quick-action-card">
                <i class="fas fa-plus-circle"></i>
                <h3>Change User Password panel</h3>
                <a href="change_user_password.php">Change User Password</a>
            </div>

            <div class="quick-action-card">
                <i class="fas fa-plus-circle"></i>
                <h3>view password requests panel</h3>
                <a href="view_password_requests.php">view password requests</a>
            </div>

            <div class="quick-action-card">
                <i class="fas fa-exclamation-circle"></i>
                <h3>Rejected Complaints</h3>
                <a href="manage_rejected_complaints.php">View Rejected Complaints</a>
            </div>
        </div>

        <!-- Users Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="mb-0">Manage Users</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>District</th>
                                <th>Role</th>
                                <th>Total Complaints</th>
                                <th>Resolved</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($user = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if(!empty($user['profile_picture'])): ?>
                                            <?php 
                                            // Handle profile picture path for admin dashboard
                                            $adminProfilePath = $user['profile_picture'];
                                            
                                            // Handle profile picture path resolution for admin dashboard
                                            // NEW: Profile pictures stored in: Includes/citizen/uploads/profilepics/
                                            // OLD: Profile pictures stored in: Includes/citizen/uploads/
                                            // Database stores full path, Admin needs: "../../" + database_path
                                            
                                            // Convert database path to web-accessible path from admin perspective
                                            if (strpos($adminProfilePath, 'Includes/citizen/uploads/profilepics/') === 0) {
                                                // New format: Includes/citizen/uploads/profilepics/filename.jpg
                                                $adminProfilePath = '../../' . $adminProfilePath;
                                            } elseif (strpos($adminProfilePath, 'Includes/citizen/uploads/') === 0) {
                                                // Old format: Includes/citizen/uploads/filename.jpg
                                                $adminProfilePath = '../../' . $adminProfilePath;
                                            } elseif (strpos($adminProfilePath, 'Includes/citizen/') === 0) {
                                                // General format: Includes/citizen/uploads/filename.jpg
                                                $adminProfilePath = '../../' . $adminProfilePath;
                                            } elseif (strpos($adminProfilePath, '/') === false) {
                                                // Just filename: filename.jpg (assume it's in new profilepics folder)
                                                $adminProfilePath = '../../Includes/citizen/uploads/profilepics/' . $adminProfilePath;
                                            } else {
                                                // Use path as is if it doesn't match any pattern
                                                $adminProfilePath = $user['profile_picture'];
                                            }
                                            ?>
                                            <img src="<?php echo htmlspecialchars($adminProfilePath); ?>" 
                                                 class="profile-image me-2" 
                                                 alt="<?php echo htmlspecialchars($user['name']); ?>'s Profile"
                                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAiIGhlaWdodD0iMzAiIHZpZXdCb3g9IjAgMCAzMCAzMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMTUiIGN5PSIxNSIgcj0iMTUiIGZpbGw9IiNlOWVjZWYiLz4KPHN2ZyB4PSI3LjUiIHk9IjcuNSIgd2lkdGg9IjE1IiBoZWlnaHQ9IjE1IiB2aWV3Qm94PSIwIDAgMjQgMjQiIGZpbGw9Im5vbmUiIHN0cm9rZT0iIzZjNzU3ZCIgc3Ryb2tlLXdpZHRoPSIyIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiPgo8cGF0aCBkPSJtMjAgMjEtMi0yLTItMiIvPgo8cGF0aCBkPSJtMTcgMTctNS01LTUtNSIvPgo8Y2lyY2xlIGN4PSI5IiBjeT0iOSIgcj0iMiIvPgo8L3N2Zz4KPC9zdmc+'; this.title='Profile picture not found';">
                                        <?php else: ?>
                                            <!-- Default avatar for users without profile pictures -->
                                            <div class="profile-image me-2 bg-secondary d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; border-radius: 50%; color: white; font-size: 12px; font-weight: bold;">
                                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($user['name']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['mobile']); ?></td>
                                <td><?php echo htmlspecialchars($user['district']); ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo strtolower($user['role']); ?>">
                                        <?php echo htmlspecialchars($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="stats-card">
                                        <div class="stat-value"><?php echo $user['total_complaints']; ?></div>
                                        <div class="stat-label">Total</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="stats-card">
                                        <div class="stat-value"><?php echo $user['resolved_complaints']; ?></div>
                                        <div class="stat-label">Resolved</div>
                                    </div>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($user['role'] === 'department'): ?>
                                            <button type="button" class="btn btn-secondary btn-sm change-password-btn" data-bs-toggle="modal" data-bs-target="#changePasswordModal" data-user-id="<?php echo $user['user_id']; ?>">Change Password</button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Departments Section -->
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Manage Departments</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Department Name</th>
                                <th>Description</th>
                                <th>Contact Email</th>
                                <th>Total Complaints</th>
                                <th>Resolved</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($dept = $dept_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $dept['dept_id']; ?></td>
                                <td>
                                    <form method="POST" class="update-form">
                                        <input type="hidden" name="dept_id" value="<?php echo $dept['dept_id']; ?>">
                                        <input type="text" name="dept_name" value="<?php echo htmlspecialchars($dept['dept_name']); ?>" class="form-control">
                                </td>
                                <td>
                                        <input type="text" name="description" value="<?php echo htmlspecialchars($dept['description']); ?>" class="form-control">
                                </td>
                                <td>
                                        <input type="email" name="contact_email" value="<?php echo htmlspecialchars($dept['contact_email']); ?>" class="form-control">
                                </td>
                                <td>
                                    <div class="stats-card">
                                        <div class="stat-value"><?php echo $dept['total_complaints']; ?></div>
                                        <div class="stat-label">Total</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="stats-card">
                                        <div class="stat-value"><?php echo $dept['resolved_complaints']; ?></div>
                                        <div class="stat-label">Resolved</div>
                                    </div>
                                </td>
                                <td>
                                    <select name="status" class="form-control">
                                        <option value="active" <?php echo $dept['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $dept['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($dept['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="submit" name="update_department" class="btn btn-primary btn-sm">Update</button>
                                    </form>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteDepartment(<?php echo $dept['dept_id']; ?>, '<?php echo htmlspecialchars($dept['dept_name']); ?>')">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordModalLabel">Change Department Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="modal-user-id">
                        <div class="mb-3">
                            <label for="new-password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new-password" name="new_password" required>
                        </div>
                         <div class="mb-3">
                            <label for="confirm-password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm-password" name="confirm_password" required>
                            <div class="invalid-feedback">Passwords do not match.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="change_password" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // SweetAlert confirmation for user deletion (button click approach)
        function deleteUser(userId, userName) {
            Swal.fire({
                title: 'Delete User?',
                html: `Are you sure you want to delete user <strong>${userName}</strong>?<br><br><small class="text-danger">This will also delete all their complaints and reviews. This action cannot be undone.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Delete User!',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Deleting User...',
                        text: 'Please wait while we delete the user and their data.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Create and submit form programmatically
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    
                    // Add user_id input
                    const userIdInput = document.createElement('input');
                    userIdInput.type = 'hidden';
                    userIdInput.name = 'user_id';
                    userIdInput.value = userId;
                    form.appendChild(userIdInput);
                    
                    // Add delete_user input
                    const deleteInput = document.createElement('input');
                    deleteInput.type = 'hidden';
                    deleteInput.name = 'delete_user';
                    deleteInput.value = '1';
                    form.appendChild(deleteInput);
                    
                    // Add to page and submit
                    document.body.appendChild(form);
                    console.log('Submitting user deletion for ID:', userId);
                    form.submit();
                }
            });
        }

        // SweetAlert confirmation for department deletion (button click approach)
        function deleteDepartment(deptId, deptName) {
            Swal.fire({
                title: 'Delete Department?',
                html: `Are you sure you want to delete the <strong>${deptName}</strong> department?<br><br><small class="text-warning">This will unassign all complaints from this department. This action cannot be undone.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Delete Department!',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Deleting Department...',
                        text: 'Please wait while we delete the department.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Create and submit form programmatically
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    
                    // Add dept_id input
                    const deptIdInput = document.createElement('input');
                    deptIdInput.type = 'hidden';
                    deptIdInput.name = 'dept_id';
                    deptIdInput.value = deptId;
                    form.appendChild(deptIdInput);
                    
                    // Add delete_department input
                    const deleteInput = document.createElement('input');
                    deleteInput.type = 'hidden';
                    deleteInput.name = 'delete_department';
                    deleteInput.value = '1';
                    form.appendChild(deleteInput);
                    
                    // Add to page and submit
                    document.body.appendChild(form);
                    console.log('Submitting department deletion for ID:', deptId);
                    form.submit();
                }
            });
        }

        // Handle change password modal
        const changePasswordModal = document.getElementById('changePasswordModal');
        changePasswordModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const modalUserIdInput = changePasswordModal.querySelector('#modal-user-id');
            modalUserIdInput.value = userId;
        });

        // Add password confirmation validation
        const passwordForm = changePasswordModal.querySelector('form');
        const newPasswordInput = passwordForm.querySelector('#new-password');
        const confirmPasswordInput = passwordForm.querySelector('#confirm-password');

        passwordForm.addEventListener('submit', function(event) {
            if (newPasswordInput.value !== confirmPasswordInput.value) {
                confirmPasswordInput.classList.add('is-invalid');
                event.preventDefault(); // Prevent form submission
                Swal.fire({
                    title: 'Password Mismatch!',
                    text: 'The password and confirm password fields do not match. Please check and try again.',
                    icon: 'error',
                    confirmButtonColor: '#00bfff'
                });
            } else if (newPasswordInput.value.length < 6) {
                event.preventDefault();
                Swal.fire({
                    title: 'Password Too Short!',
                    text: 'Password must be at least 6 characters long.',
                    icon: 'warning',
                    confirmButtonColor: '#00bfff'
                });
            } else {
                confirmPasswordInput.classList.remove('is-invalid');
                // Show loading state
                Swal.fire({
                    title: 'Updating Password...',
                    text: 'Please wait while we update the password.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            }
        });

         // Clear modal form when closed
         changePasswordModal.addEventListener('hidden.bs.modal', function () {
            passwordForm.reset();
            confirmPasswordInput.classList.remove('is-invalid');
        });

    </script>
</body>
</html>
