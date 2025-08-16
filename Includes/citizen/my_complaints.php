<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
include('../dbconnect.php');

// Check if user is logged in and is a citizen
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header("Location: ../unauthorized.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// Handle complaint deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_complaint'])) {
    $complaint_id = $_POST['complaint_id'];
    
    // First, get the complaint details to check ownership and get media path
    $check_sql = "SELECT media_path FROM complaints WHERE complaint_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $complaint_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $complaint_data = $check_result->fetch_assoc();
        
        // Delete associated media file if it exists
        if (!empty($complaint_data['media_path'])) {
            $media_path = $complaint_data['media_path'];
            
            // Handle different path formats
            if (strpos($media_path, 'Includes/citizen/') === 0) {
                $file_path = str_replace('Includes/citizen/', '', $media_path);
            } else {
                $file_path = $media_path;
            }
            
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete the complaint from database
        $delete_sql = "DELETE FROM complaints WHERE complaint_id = ? AND user_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $complaint_id, $user_id);
        
        if ($delete_stmt->execute()) {
            $message = "success|Complaint deleted successfully.";
        } else {
            $message = "error|Error deleting complaint.";
        }
    } else {
        $message = "error|Complaint not found or you don't have permission to delete it.";
    }
}

// Fetch complaints
$sql = "SELECT c.*, d.dept_name FROM complaints c 
        JOIN departments d ON c.dept_id = d.dept_id
        WHERE c.user_id = ? ORDER BY c.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Complaints - FixLanka</title>
    <link rel="stylesheet" href="citizen.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Image Modal Styles */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            animation: fadeIn 0.3s ease-in-out;
        }

        .image-modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .image-modal-content {
            position: relative;
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 80vw;
            max-height: 80vh;
            animation: slideIn 0.3s ease-in-out;
        }

        .image-modal-image {
            width: 100%;
            height: auto;
            max-width: 600px;
            max-height: 500px;
            border-radius: 10px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        .image-modal-close {
            position: absolute;
            top: 10px;
            right: 15px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }

        .image-modal-close:hover,
        .image-modal-close:focus {
            color: #000;
        }

        .proof-img {
            cursor: pointer;
            transition: transform 0.2s ease-in-out;
        }

        .proof-img:hover {
            transform: scale(1.05);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: scale(0.7) translateY(-50px);
            }
            to { 
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        /* Enhanced Button Styles */
        .submit-btn {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
            border: none;
            cursor: pointer;
            min-width: 140px;
            text-align: center;
            font-size: 14px;
        }
        
        .submit-btn:hover {
            background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
            text-decoration: none;
            color: white;
        }
        
        .back-btn {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
            border: none;
            cursor: pointer;
            min-width: 140px;
            text-align: center;
            font-size: 14px;
        }
        
        .back-btn:hover {
            background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(107, 114, 128, 0.4);
            text-decoration: none;
            color: white;
        }
        
        .delete-btn:hover {
            transform: translateY(-2px) !important;
        }
        
        /* Responsive button adjustments */
        @media (max-width: 768px) {
            .submit-btn, .back-btn {
                min-width: 120px;
                padding: 8px 16px;
                font-size: 13px;
            }
            
            .submit-complaint-container {
                padding: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .submit-btn, .back-btn {
                min-width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="submit-complaint-container">
        <h1 class="complaint-title">My Submitted Complaints</h1>
        
        <!-- Statistics Summary -->
        <?php 
        $total_complaints = $result->num_rows;
        $result->data_seek(0); // Reset result pointer
        $pending = $in_progress = $resolved = $rejected = 0;
        $complaints = [];
        while ($row = $result->fetch_assoc()) {
            $complaints[] = $row;
            switch($row['status']) {
                case 'Pending': $pending++; break;
                case 'In Progress': $in_progress++; break;
                case 'Resolved': $resolved++; break;
                case 'Rejected': $rejected++; break;
            }
        }
        ?>
        
        <div class="stats-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 30px;">
            <div class="stat-box" style="background: #e3f2fd; padding: 15px; border-radius: 8px; text-align: center;">
                <h3 style="margin: 0; color: #1976d2;"><?= $total_complaints ?></h3>
                <p style="margin: 5px 0 0 0; font-size: 14px;">Total</p>
            </div>
            <div class="stat-box" style="background: #fff3e0; padding: 15px; border-radius: 8px; text-align: center;">
                <h3 style="margin: 0; color: #f57c00;"><?= $pending ?></h3>
                <p style="margin: 5px 0 0 0; font-size: 14px;">Pending</p>
            </div>
            <div class="stat-box" style="background: #e8f5e8; padding: 15px; border-radius: 8px; text-align: center;">
                <h3 style="margin: 0; color: #388e3c;"><?= $in_progress ?></h3>
                <p style="margin: 5px 0 0 0; font-size: 14px;">In Progress</p>
            </div>
            <div class="stat-box" style="background: #e8f5e8; padding: 15px; border-radius: 8px; text-align: center;">
                <h3 style="margin: 0; color: #2e7d32;"><?= $resolved ?></h3>
                <p style="margin: 5px 0 0 0; font-size: 14px;">Resolved</p>
            </div>
            <?php if ($rejected > 0): ?>
            <div class="stat-box" style="background: #ffebee; padding: 15px; border-radius: 8px; text-align: center;">
                <h3 style="margin: 0; color: #d32f2f;"><?= $rejected ?></h3>
                <p style="margin: 5px 0 0 0; font-size: 14px;">Rejected</p>
            </div>
            <?php endif; ?>
        </div>

        <?php if (count($complaints) > 0): ?>
            <div class="complaints-grid" style="display: grid; gap: 20px;">
                <?php foreach ($complaints as $row): ?>
                    <div class="complaint-item" style="background: #f9f9f9; padding: 20px; border-radius: 10px; border-left: 4px solid #6366f1;">
                        <div style="display: flex; justify-content: between; align-items: flex-start; margin-bottom: 15px;">
                            <div style="flex: 1;">
                                <h3 style="margin: 0 0 10px 0; color: #333;"><?= htmlspecialchars($row['title']) ?></h3>
                                <div class="complaint-meta" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; font-size: 14px;">
                                    <p><strong>Department:</strong> <?= htmlspecialchars($row['dept_name']) ?></p>
                                    <p><strong>Reference:</strong> <?= $row['ref_number'] ?></p>
                                    <p><strong>Status:</strong> 
                                        <span class="status-badge" style="
                                            padding: 4px 8px; 
                                            border-radius: 12px; 
                                            font-size: 12px; 
                                            font-weight: bold;
                                            <?php 
                                            switch($row['status']) {
                                                case 'Pending': echo 'background: #fff3e0; color: #f57c00;'; break;
                                                case 'In Progress': echo 'background: #e3f2fd; color: #1976d2;'; break;
                                                case 'Resolved': echo 'background: #e8f5e8; color: #2e7d32;'; break;
                                                case 'Rejected': echo 'background: #ffebee; color: #d32f2f;'; break;
                                            }
                                            ?>
                                        "><?= $row['status'] ?></span>
                                    </p>
                                    <p><strong>Submitted:</strong> <?= date('M d, Y', strtotime($row['created_at'])) ?></p>
                                </div>
                            </div>
                            <div class="complaint-actions" style="margin-left: 15px;">
                                <button onclick="confirmDelete(<?= $row['complaint_id'] ?>, '<?= htmlspecialchars($row['ref_number']) ?>')" 
                                        class="delete-btn" 
                                        style="background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 4px rgba(220,53,69,0.3);"
                                        onmouseover="this.style.background='#c82333'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(220,53,69,0.4)'"
                                        onmouseout="this.style.background='#dc3545'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(220,53,69,0.3)'">
                                    🗑️ Delete
                                </button>
                            </div>
                        </div>
                        
                        <div class="complaint-description" style="margin-bottom: 15px; padding: 10px; background: white; border-radius: 6px;">
                            <p style="margin: 0;"><?= nl2br(htmlspecialchars($row['description'])) ?></p>
                        </div>

                        <?php if (!empty($row['media_path'])): ?>
                            <div class="complaint-media" style="margin-bottom: 15px;">
                                <strong>Attached Media:</strong><br>
                                <?php 
                                // Handle different path formats for media display
                                $display_path = $row['media_path'];
                                
                                // If path starts with 'Includes/citizen/', remove it for current location
                                if (strpos($display_path, 'Includes/citizen/') === 0) {
                                    $display_path = str_replace('Includes/citizen/', '', $display_path);
                                }
                                // If path is just 'uploads/filename', it's already correct for current location
                                elseif (strpos($display_path, 'uploads/') === 0) {
                                    // Path is already correct for current location
                                }
                                // If no path prefix, assume it's in uploads folder
                                elseif (strpos($display_path, '/') === false) {
                                    $display_path = 'uploads/' . $display_path;
                                }
                                ?>
                                <?php if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $row['media_path'])): ?>
                                    <img src="<?= htmlspecialchars($display_path) ?>" 
                                         alt="Complaint Evidence" 
                                         class="proof-img" 
                                         onclick="openImageModal('<?= htmlspecialchars($display_path) ?>')"
                                         style="max-width: 200px; max-height: 150px; object-fit: cover; border-radius: 6px; cursor: pointer; margin-top: 10px; border: 2px solid #ddd;"
                                         onerror="this.style.border='2px solid red'; this.alt='Image not found: <?= htmlspecialchars($display_path) ?>';">
                                <?php elseif (preg_match('/\.(mp4|webm|ogg)$/i', $row['media_path'])): ?>
                                    <video controls style="max-width: 200px; max-height: 150px; border-radius: 6px; margin-top: 10px;">
                                        <source src="<?= htmlspecialchars($display_path) ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($row['status'] === 'Rejected'): ?>
                            <div class="rejection-reason" style="background: #ffebee; padding: 10px; border-radius: 6px; border-left: 3px solid #d32f2f;">
                                <strong style="color: #d32f2f;">Rejection Reason:</strong>
                                <p style="margin: 5px 0 0 0; color: #666;"><?= htmlspecialchars($row['rejection_reason']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-complaints" style="text-align: center; padding: 40px; background: #f9f9f9; border-radius: 10px;">
                <h3>No Complaints Yet</h3>
                <p>You haven't submitted any complaints yet.</p>
                <a href="submit_complaint.php" class="submit-btn">📝 Submit Your First Complaint</a>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; text-align: center; padding: 0 20px;">
            <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
                <a href="../../home.php" class="back-btn">🏠 Back to Home</a>
                <a href="submit_complaint.php" class="submit-btn">➕ Submit New Complaint</a>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal">
        <div class="image-modal-content">
            <span class="image-modal-close" onclick="closeImageModal()">&times;</span>
            <img id="imageModalImage" class="image-modal-image" src="" alt="Complaint Image">
        </div>
    </div>

    <script>
        // Show success/error messages with SweetAlert
        <?php if (!empty($message)): ?>
        <?php 
        $parts = explode('|', $message);
        $type = $parts[0];
        $text = $parts[1];
        ?>
        window.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: '<?= $type === "success" ? "Success!" : "Error!" ?>',
                text: '<?= addslashes($text) ?>',
                icon: '<?= $type === "success" ? "success" : "error" ?>',
                confirmButtonText: 'OK',
                confirmButtonColor: '#6366f1'
            });
        });
        <?php endif; ?>

        // Delete confirmation with SweetAlert
        function confirmDelete(complaintId, refNumber) {
            Swal.fire({
                title: 'Delete Complaint?',
                html: `Are you sure you want to delete complaint <strong>${refNumber}</strong>?<br><br><small>This action cannot be undone and will permanently remove the complaint and its associated files.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Delete It!',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Deleting...',
                        text: 'Please wait while we delete your complaint.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Create and submit form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="delete_complaint" value="1">
                        <input type="hidden" name="complaint_id" value="${complaintId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

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
</body>
</html>
