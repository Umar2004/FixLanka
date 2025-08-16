<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department') {
    header("Location: unauthorized.php");
    exit();
}
include('../dbconnect.php');

// Add PHPMailer imports
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once '../../phpmailer/src/Exception.php';
require_once '../../phpmailer/src/PHPMailer.php';
require_once '../../phpmailer/src/SMTP.php';

function sendStatusUpdateEmail($to_email, $to_name, $complaint_title, $status, $ref_number, $rejection_reason = '') {
    $mail = new PHPMailer(true);
    
    try {
        // Enable debug output
        $mail->SMTPDebug = 3; // Enable verbose debug output
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug: $str");
        };

        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'Omar727221@gmail.com';
        $mail->Password = 'mwjn yzqg pjrk ayzp';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        
        // Add DKIM and SPF headers
        $mail->addCustomHeader('X-Mailer', 'FixLanka Complaint System');
        $mail->addCustomHeader('X-Priority', '1');
        $mail->addCustomHeader('X-MSMail-Priority', 'High');
        $mail->addCustomHeader('Importance', 'High');

        // Additional SMTP options for debugging
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom('Omar727221@gmail.com', 'FixLanka System', false);
        $mail->addReplyTo('Omar727221@gmail.com', 'FixLanka Support');
        $mail->addAddress($to_email, $to_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "FixLanka - Complaint Status Update: $ref_number";
        
        // Create a more professional email template
        $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px;'>
            <div style='background-color: #004080; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;'>
                <h1 style='margin: 0;'>FixLanka</h1>
                <p style='margin: 5px 0 0 0;'>Complaint Status Update</p>
            </div>
            <div style='padding: 20px; background-color: #f9f9f9;'>
                <p>Dear $to_name,</p>
                <p>Your complaint has been updated with the following details:</p>
                <div style='background-color: white; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                    <p><strong>Reference Number:</strong> $ref_number</p>
                    <p><strong>Title:</strong> $complaint_title</p>
                    <p><strong>New Status:</strong> <span style='color: " . 
                    ($status === 'Resolved' ? '#28a745' : 
                    ($status === 'Rejected' ? '#dc3545' : 
                    ($status === 'In Progress' ? '#ffc107' : '#004080'))) . 
                    ";'>$status</span></p>";
        
        if ($status === 'Rejected' && !empty($rejection_reason)) {
            $message .= "<p><strong>Rejection Reason:</strong><br>$rejection_reason</p>";
        }
        
        $message .= "
                </div>
                <p>You can track your complaint status by visiting our website and entering your reference number.</p>
                <p>Thank you for using FixLanka.</p>
            </div>
            <div style='text-align: center; padding: 20px; color: #666; font-size: 12px; border-top: 1px solid #eee;'>
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " FixLanka. All rights reserved.</p>
            </div>
        </div>";
        
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);

        // Log attempt to send email
        error_log("Attempting to send email to: $to_email for complaint: $ref_number");
        
        if($mail->send()) {
            error_log("Email sent successfully to $to_email for complaint $ref_number");
            return true;
        } else {
            error_log("Email sending failed for complaint $ref_number. Error: " . $mail->ErrorInfo);
            return false;
        }
    } catch (Exception $e) {
        error_log("Email sending failed for complaint $ref_number. Exception: " . $e->getMessage());
        error_log("Full error details: " . print_r($e, true));
        return false;
    }
}

// Add a test function to verify email configuration
function testEmailConfiguration() {
    try {
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = 3; // Increased debug level
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'Omar727221@gmail.com';
        $mail->Password = 'mwjn yzqg pjrk ayzp';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Add DKIM and SPF headers
        $mail->addCustomHeader('X-Mailer', 'FixLanka Complaint System');
        $mail->addCustomHeader('X-Priority', '1');
        $mail->addCustomHeader('X-MSMail-Priority', 'High');
        $mail->addCustomHeader('Importance', 'High');
        
        $mail->setFrom('Omar727221@gmail.com', 'FixLanka System', false);
        $mail->addReplyTo('Omar727221@gmail.com', 'FixLanka Support');
        $mail->addAddress('Omar727221@gmail.com', 'Test User');
        
        $mail->isHTML(true);
        $mail->Subject = 'FixLanka - Test Email';
        $mail->Body = '<h1>Test Email</h1><p>This is a test email to verify the email configuration.</p>';
        
        if($mail->send()) {
            error_log("Test email sent successfully");
            return true;
        } else {
            error_log("Test email failed to send. Error: " . $mail->ErrorInfo);
            return false;
        }
    } catch (Exception $e) {
        error_log("Test email failed with exception: " . $e->getMessage());
        return false;
    }
}

// Test email configuration when page loads
if(isset($_GET['test_email'])) {
    if(testEmailConfiguration()) {
        echo "Test email sent successfully. Check your email and the error log for details.";
    } else {
        echo "Test email failed. Check the error log for details.";
    }
    exit;
}

$dept_id = $_SESSION['user_id']; // department's user_id

// Get department name
$dept_sql = "SELECT dept_name FROM departments WHERE dept_id = ?";
$dept_stmt = $conn->prepare($dept_sql);
$dept_stmt->bind_param("i", $dept_id);
$dept_stmt->execute();
$dept_result = $dept_stmt->get_result();
$department = $dept_result->fetch_assoc();
$dept_name = $department['dept_name'] ?? 'Department';

// Update status or reject complaint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $complaint_id = $_POST['complaint_id'];
    $action = $_POST['action'];

    if ($action === 'reject') {
        $reason = $_POST['rejection_reason'];
        $sql = "UPDATE complaints SET status='Rejected', rejection_reason=? WHERE complaint_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $reason, $complaint_id);
        $stmt->execute();
        
        // Send email notification for rejection
        $sql = "SELECT c.*, u.email, u.name FROM complaints c 
                JOIN users u ON c.user_id = u.user_id 
                WHERE c.complaint_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $complaint_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            sendStatusUpdateEmail($row['email'], $row['name'], $row['title'], 'Rejected', $row['ref_number'], $reason);
        }
    } elseif ($action === 'resolve') {
        $media_path = "";
        if (!empty($_FILES['proof']['name'])) {
            $filename = basename($_FILES['proof']['name']);
            $tmpname = $_FILES['proof']['tmp_name'];
            
            // Create uploads directory if it doesn't exist
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename to prevent conflicts
            $file_extension = pathinfo($filename, PATHINFO_EXTENSION);
            $unique_filename = 'resolved_' . uniqid() . '.' . $file_extension;
            
            // Store relative path for database and web access
            $media_path = "Includes/department/uploads/" . $unique_filename;
            $full_path = $upload_dir . $unique_filename;
            
            move_uploaded_file($tmpname, $full_path);

            $sql = "UPDATE complaints SET status='Resolved', media_path=? WHERE complaint_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $media_path, $complaint_id);
            $stmt->execute();
            
            // Send email notification for resolution
            $sql = "SELECT c.*, u.email, u.name FROM complaints c 
                    JOIN users u ON c.user_id = u.user_id 
                    WHERE c.complaint_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $complaint_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                sendStatusUpdateEmail($row['email'], $row['name'], $row['title'], 'Resolved', $row['ref_number']);
            }
        }
    } elseif ($action === 'in_progress') {
        $sql = "UPDATE complaints SET status='In Progress' WHERE complaint_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $complaint_id);
        $stmt->execute();
        
        // Send email notification for in progress
        $sql = "SELECT c.*, u.email, u.name FROM complaints c 
                JOIN users u ON c.user_id = u.user_id 
                WHERE c.complaint_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $complaint_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            sendStatusUpdateEmail($row['email'], $row['name'], $row['title'], 'In Progress', $row['ref_number']);
        }
    }
}

// Fetch active complaints for this department (excluding resolved and rejected ones)
$sql = "SELECT complaints.*, users.name AS complainant_name, users.email AS complainant_email, users.mobile AS complainant_mobile 
        FROM complaints 
        LEFT JOIN users ON complaints.user_id = users.user_id 
        WHERE complaints.dept_id = ? AND complaints.status != 'Resolved' AND complaints.status != 'Rejected'
        ORDER BY complaints.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$result = $stmt->get_result();

$complaints = [];
while ($row = $result->fetch_assoc()) {
    $complaints[] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Department Dashboard - FixLanka</title>
    <link rel="stylesheet" href="department.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
      crossorigin=""></script>

</head>
<body class="department-dashboard">
    <div class="container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <h1><?= htmlspecialchars($dept_name) ?> Dashboard</h1>
                <p class="header-subtitle">Manage and track department complaints</p>
            </div>
            <div class="header-actions">
                <a href="completed_complaints.php" class="btn-primary">📊 View Completed Complaints</a>
                <a href="../logout.php" class="btn-secondary">🚪 Logout</a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="dashboard-stats">
            <?php
            $total_count = count($complaints);
            $pending_count = count(array_filter($complaints, fn($c) => $c['status'] === 'Pending'));
            $in_progress_count = count(array_filter($complaints, fn($c) => $c['status'] === 'In Progress'));
            ?>
            <div class="stat-card">
                <h3><?= $total_count ?></h3>
                <p>Total Active</p>
            </div>
            <div class="stat-card">
                <h3><?= $pending_count ?></h3>
                <p>Pending</p>
            </div>
            <div class="stat-card">
                <h3><?= $in_progress_count ?></h3>
                <p>In Progress</p>
            </div>
            <div class="stat-card">
                <h3><?= date('M Y') ?></h3>
                <p>Current Period</p>
            </div>
        </div>

        <!-- View Options and Filters -->
        <div class="view-controls">
            <div class="view-options">
                <button class="view-btn active" data-view="card">📋 Card View</button>
                <button class="view-btn" data-view="list">📄 List View</button>
                <button class="view-btn" data-view="table">📊 Table View</button>
            </div>
            <div class="filter-options">
                <select id="statusFilter" onchange="filterComplaints()">
                    <option value="all">All Status</option>
                    <option value="Pending">Pending</option>
                    <option value="In Progress">In Progress</option>
                </select>
                <input type="date" id="dateFilter" onchange="filterComplaints()" placeholder="Filter by date">
                <button onclick="clearFilters()" class="btn-secondary">Clear Filters</button>
            </div>
        </div>

        <!-- Complaints Container -->
        <div id="complaintsContainer" class="complaints-container">
            <?php if (count($complaints) > 0): ?>
                <!-- Card View (Default) -->
                <div class="view-content card-view active">
                    <?php foreach ($complaints as $row): ?>
                        <div class="complaint-card" data-status="<?= $row['status'] ?>" data-date="<?= date('Y-m-d', strtotime($row['created_at'])) ?>">
                    <h3><?= htmlspecialchars($row['title']) ?> (<?= $row['ref_number'] ?>)</h3>
                    <p><strong>Status:</strong> <?= $row['status'] ?></p>
                    <p><?= $row['description'] ?></p>
                    <p><strong>Location:</strong> 
                    (Lat: <?= htmlspecialchars($row['location_lat']) ?>, Lng: <?= htmlspecialchars($row['location_lng']) ?>)
                    </p>
                    <div id="map<?= $row['complaint_id'] ?>" style="height: 200px; width: 100%; margin-bottom: 10px;"></div>
                    <p><strong>Submitted:</strong> <?= $row['created_at'] ?></p>

    <p><strong>Complainant:</strong> <?= htmlspecialchars($row['complainant_name']) ?> (<?= htmlspecialchars($row['complainant_email']) ?>, <?= htmlspecialchars($row['complainant_mobile']) ?>)</p>

                    <?php if (!empty($row['media_path'])): ?>
                        <p><strong>Complaint Media:</strong></p>
                        <?php 
                        // Adjust path for department dashboard display
                        $display_path = $row['media_path'];
                        
                        // If path starts with 'Includes/department/', it's a resolved complaint proof - use relative path
                        if (strpos($display_path, 'Includes/department/') === 0) {
                            $display_path = str_replace('Includes/department/', '', $display_path);
                        }
                        // If path starts with 'Includes/citizen/', adjust for department folder location
                        elseif (strpos($display_path, 'Includes/citizen/') === 0) {
                            $display_path = '../citizen/' . str_replace('Includes/citizen/', '', $display_path);
                        }
                        // If path is just 'uploads/filename', adjust for department location
                        elseif (strpos($display_path, 'uploads/') === 0) {
                            $display_path = '../citizen/' . $display_path;
                        }
                        // If no path prefix, assume it's in citizen uploads folder
                        elseif (strpos($display_path, '/') === false) {
                            $display_path = '../citizen/uploads/' . $display_path;
                        }
                        ?>
                        <?php if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $row['media_path'])): ?>
                            <img src="<?= htmlspecialchars($display_path) ?>" alt="Complaint Image" style="max-width: 300px; cursor: pointer;" onclick="openImageModal('<?= htmlspecialchars($display_path) ?>')">
                        <?php elseif (preg_match('/\.(mp4|webm|ogg)$/i', $row['media_path'])): ?>
                            <video controls style="max-width: 300px;">
                                <source src="<?= htmlspecialchars($display_path) ?>" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($row['status'] === 'Pending'): ?>
                        <form method="POST">
                            <input type="hidden" name="complaint_id" value="<?= $row['complaint_id'] ?>">
                            <button name="action" value="in_progress">Mark as In Progress</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($row['status'] === 'In Progress'): ?>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="complaint_id" value="<?= $row['complaint_id'] ?>">
                            <label>Upload Proof (Image/Video):</label>
                            <input type="file" name="proof" accept="image/*,video/*" required><br>
                            <button name="action" value="resolve">Mark as Complete</button>
                        </form>

                        <form method="POST">
                            <input type="hidden" name="complaint_id" value="<?= $row['complaint_id'] ?>">
                            <label>Rejection Reason:</label><br>
                            <textarea name="rejection_reason" required></textarea><br>
                            <button name="action" value="reject">Reject Complaint</button>
                        </form>
                    <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- List View -->
                <div class="view-content list-view">
                    <?php foreach ($complaints as $row): ?>
                        <div class="complaint-list-item" data-status="<?= $row['status'] ?>" data-date="<?= date('Y-m-d', strtotime($row['created_at'])) ?>">
                            <div class="list-item-header">
                                <h4><?= htmlspecialchars($row['title']) ?></h4>
                                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>"><?= $row['status'] ?></span>
                            </div>
                            <div class="list-item-content">
                                <span class="ref-number"><?= $row['ref_number'] ?></span>
                                <span class="complainant"><?= htmlspecialchars($row['complainant_name']) ?></span>
                                <span class="date"><?= date('M d, Y', strtotime($row['created_at'])) ?></span>
                                <div class="list-actions">
                                    <?php if ($row['status'] === 'Pending'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="complaint_id" value="<?= $row['complaint_id'] ?>">
                                            <button name="action" value="in_progress" class="btn-small btn-primary">Mark In Progress</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Table View -->
                <div class="view-content table-view">
                    <table class="complaints-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Complainant</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $row): ?>
                                <tr data-status="<?= $row['status'] ?>" data-date="<?= date('Y-m-d', strtotime($row['created_at'])) ?>">
                                    <td><?= $row['ref_number'] ?></td>
                                    <td><?= htmlspecialchars($row['title']) ?></td>
                                    <td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>"><?= $row['status'] ?></span></td>
                                    <td><?= htmlspecialchars($row['complainant_name']) ?></td>
                                    <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                    <td>
                                        <?php if ($row['status'] === 'Pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="complaint_id" value="<?= $row['complaint_id'] ?>">
                                                <button name="action" value="in_progress" class="btn-small btn-primary">In Progress</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-complaints">
                    <h3>No Active Complaints</h3>
                    <p>No complaints are currently assigned to this department.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

<script>
// View switching functionality
function switchView(viewType) {
    // Remove active class from all view buttons
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Add active class to clicked button
    document.querySelector(`[data-view="${viewType}"]`).classList.add('active');
    
    // Hide all view contents
    document.querySelectorAll('.view-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Show selected view
    document.querySelector(`.${viewType}-view`).classList.add('active');
}

// Add event listeners for view buttons
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            switchView(this.dataset.view);
        });
    });
});

// Filter complaints
function filterComplaints() {
    const statusFilter = document.getElementById('statusFilter').value;
    const dateFilter = document.getElementById('dateFilter').value;
    
    // Get all complaint items across all views
    const complaints = document.querySelectorAll('[data-status][data-date]');
    
    complaints.forEach(complaint => {
        let showComplaint = true;
        
        // Status filter
        if (statusFilter !== 'all' && complaint.dataset.status !== statusFilter) {
            showComplaint = false;
        }
        
        // Date filter
        if (dateFilter && complaint.dataset.date !== dateFilter) {
            showComplaint = false;
        }
        
        // Show/hide complaint
        complaint.style.display = showComplaint ? '' : 'none';
    });
    
    // Update statistics
    updateStatistics();
}

// Clear all filters
function clearFilters() {
    document.getElementById('statusFilter').value = 'all';
    document.getElementById('dateFilter').value = '';
    filterComplaints();
}

// Update statistics based on visible complaints
function updateStatistics() {
    const visibleComplaints = document.querySelectorAll('[data-status][data-date]:not([style*="display: none"])');
    const stats = {
        total: visibleComplaints.length,
        pending: 0,
        inProgress: 0
    };
    
    visibleComplaints.forEach(complaint => {
        const status = complaint.dataset.status;
        if (status === 'Pending') stats.pending++;
        else if (status === 'In Progress') stats.inProgress++;
    });
    
    // Update stat cards (only first 3 cards are for statistics, 4th is current period)
    const statCards = document.querySelectorAll('.dashboard-stats .stat-card h3');
    if (statCards.length >= 3) {
        statCards[0].textContent = stats.total;
        statCards[1].textContent = stats.pending;
        statCards[2].textContent = stats.inProgress;
        // Keep the 4th card (current period) unchanged
    }
}

document.addEventListener("DOMContentLoaded", function() {
<?php foreach ($complaints as $row): ?>
<?php if (is_numeric($row['location_lat']) && is_numeric($row['location_lng'])): ?>
    console.log("Initializing map for complaint <?= $row['complaint_id'] ?>");
    var map<?= $row['complaint_id'] ?> = L.map('map<?= $row['complaint_id'] ?>').setView([<?= $row['location_lat'] ?>, <?= $row['location_lng'] ?>], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    }).addTo(map<?= $row['complaint_id'] ?>);
    L.marker([<?= $row['location_lat'] ?>, <?= $row['location_lng'] ?>]).addTo(map<?= $row['complaint_id'] ?>);
<?php else: ?>
    document.getElementById('map<?= $row['complaint_id'] ?>').innerHTML = '<p>Location data not available</p>';
<?php endif; ?>
<?php endforeach; ?>
});

// Image Modal Functions
function openImageModal(imageSrc) {
    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('imageModalImage');
    
    modalImage.src = imageSrc;
    modal.classList.add('show');
    
    // Prevent body scrolling when modal is open
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    modal.classList.remove('show');
    
    // Restore body scrolling
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside the image
document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeImageModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageModal();
    }
});
</script>

<!-- Image Modal -->
<div id="imageModal" class="image-modal">
    <div class="image-modal-content">
        <span class="image-modal-close" onclick="closeImageModal()">&times;</span>
        <img id="imageModalImage" class="image-modal-image" src="" alt="Complaint Image">
    </div>
</div>

</body>
</html>
