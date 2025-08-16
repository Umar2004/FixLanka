<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../unauthorized.php");
    exit();
}

include('../dbconnect.php');

// Handle search functionality
$search_ref = isset($_GET['search_ref']) ? $_GET['search_ref'] : '';
$search_date = isset($_GET['search_date']) ? $_GET['search_date'] : '';

// Build the SQL query with search conditions
$sql = "SELECT c.*, u.name as user_name, u.email as user_email, d.dept_name 
        FROM complaints c 
        LEFT JOIN users u ON c.user_id = u.user_id 
        LEFT JOIN departments d ON c.dept_id = d.dept_id 
        WHERE c.status = 'Rejected'";

$params = [];
$types = '';

if (!empty($search_ref)) {
    $sql .= " AND c.ref_number LIKE ?";
    $params[] = '%' . $search_ref . '%';
    $types .= 's';
}

if (!empty($search_date)) {
    $sql .= " AND DATE(c.created_at) = ?";
    $params[] = $search_date;
    $types .= 's';
}

$sql .= " ORDER BY c.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_rejected,
    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_rejected,
    COUNT(CASE WHEN WEEK(created_at) = WEEK(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 END) as this_week_rejected,
    COUNT(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 END) as this_month_rejected
    FROM complaints WHERE status = 'Rejected'";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rejected Complaints - FixLanka Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="../../home.php">
                <img src="../../uploads/logos/FIXLANKA_LOGO.png" alt="FixLanka Logo" height="40" class="me-2">
                <span>FixLanka</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage_rejected_complaints.php">Rejected Complaints</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="admin_dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="add_department.php"><i class="fas fa-plus-circle me-2"></i>Add Department</a></li>
                            <li><a class="dropdown-item" href="completed_complaints.php"><i class="fas fa-check-circle me-2"></i>Completed Complaints</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../home.php"><i class="fas fa-home me-2"></i>FixLanka Home</a></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-exclamation-triangle text-danger me-2"></i>Rejected Complaints Management</h2>
                <p class="text-muted mb-0">Manage and review complaints that have been rejected by departments</p>
            </div>
            <a href="admin_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="stat-value text-danger"><?php echo $stats['total_rejected']; ?></div>
                        <div class="stat-label">Total Rejected</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="stat-value text-warning"><?php echo $stats['today_rejected']; ?></div>
                        <div class="stat-label">Today</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="stat-value text-info"><?php echo $stats['this_week_rejected']; ?></div>
                        <div class="stat-label">This Week</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="stat-value text-primary"><?php echo $stats['this_month_rejected']; ?></div>
                        <div class="stat-label">This Month</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-search me-2"></i>Search & Filter</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search_ref" class="form-label">Reference Number</label>
                        <input type="text" class="form-control" id="search_ref" name="search_ref" 
                               value="<?php echo htmlspecialchars($search_ref); ?>" 
                               placeholder="Enter reference number">
                    </div>
                    <div class="col-md-4">
                        <label for="search_date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="search_date" name="search_date" 
                               value="<?php echo htmlspecialchars($search_date); ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                        <a href="manage_rejected_complaints.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Rejected Complaints Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Rejected Complaints 
                    <span class="badge bg-danger ms-2"><?php echo $result->num_rows; ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th><i class="fas fa-hashtag me-1"></i>Ref Number</th>
                                    <th><i class="fas fa-heading me-1"></i>Title</th>
                                    <th><i class="fas fa-building me-1"></i>Department</th>
                                    <th><i class="fas fa-user me-1"></i>Submitted By</th>
                                    <th><i class="fas fa-exclamation-circle me-1"></i>Reason</th>
                                    <th><i class="fas fa-image me-1"></i>Media</th>
                                    <th><i class="fas fa-map-marker-alt me-1"></i>Location</th>
                                    <th><i class="fas fa-calendar me-1"></i>Date</th>
                                    <th><i class="fas fa-cogs me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-danger"><?= htmlspecialchars($row['ref_number']) ?></span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['title']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars(substr($row['description'], 0, 50)) ?>...</small>
                                        </td>
                                        <td>
                                            <?php if ($row['dept_name']): ?>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($row['dept_name']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div>
                                                    <strong><?= htmlspecialchars($row['user_name'] ?? 'Unknown') ?></strong>
                                                    <br><small class="text-muted"><?= htmlspecialchars($row['user_email'] ?? '') ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-danger">
                                                <?= htmlspecialchars($row['rejection_reason'] ?? 'No reason provided') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['media_path'])): ?>
                                                <?php
                                                $path = htmlspecialchars($row['media_path']);
                                                // Adjust path for admin dashboard display
                                                if (strpos($path, 'Includes/citizen/') === 0) {
                                                    $path = '../citizen/' . str_replace('Includes/citizen/', '', $path);
                                                } elseif (strpos($path, 'uploads/') === 0) {
                                                    $path = '../citizen/' . $path;
                                                } elseif (strpos($path, '/') === false) {
                                                    $path = '../citizen/uploads/' . $path;
                                                }
                                                $ext = pathinfo($path, PATHINFO_EXTENSION);
                                                ?>
                                                <?php if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                    <img src="<?= $path ?>" alt="Complaint Media" 
                                                         style="max-width: 80px; max-height: 60px; object-fit: cover; cursor: pointer; border-radius: 4px;"
                                                         onclick="openModal('<?= $path ?>', 'image')"
                                                         class="border">
                                                <?php elseif (in_array(strtolower($ext), ['mp4', 'webm'])): ?>
                                                    <video style="max-width: 80px; max-height: 60px; border-radius: 4px;" class="border">
                                                        <source src="<?= $path ?>" type="video/<?= $ext ?>">
                                                    </video>
                                                <?php else: ?>
                                                    <a href="<?= $path ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-file"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">No Media</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['location_lat']) && !empty($row['location_lng'])): ?>
                                                <div id="map<?= $row['complaint_id'] ?>" style="width: 100px; height: 80px; border-radius: 4px;" class="border"></div>
                                            <?php else: ?>
                                                <span class="text-muted">No Location</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?= date('M d, Y', strtotime($row['created_at'])) ?></small>
                                            <br><small class="text-muted"><?= date('H:i', strtotime($row['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group-vertical btn-group-sm" role="group">
                                                <a href="reassign_complaint.php?id=<?= $row['complaint_id'] ?>" 
                                                   class="btn btn-primary btn-sm mb-1">
                                                    <i class="fas fa-exchange-alt me-1"></i>Reassign
                                                </a>
                                                <a href="delete_complaint.php?id=<?= $row['complaint_id'] ?>" 
                                                   class="btn btn-danger btn-sm"
                                                   onclick="return confirm('Are you sure you want to permanently delete this complaint?');">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem; opacity: 0.5;"></i>
                        <h4 class="mt-3 text-muted">No Rejected Complaints Found</h4>
                        <p class="text-muted">
                            <?php if (!empty($search_ref) || !empty($search_date)): ?>
                                No rejected complaints match your search criteria.
                            <?php else: ?>
                                Great! There are currently no rejected complaints in the system.
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($search_ref) || !empty($search_date)): ?>
                            <a href="manage_rejected_complaints.php" class="btn btn-primary">
                                <i class="fas fa-list me-2"></i>View All Rejected Complaints
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Media Modal -->
    <div class="modal fade" id="mediaModal" tabindex="-1" aria-labelledby="mediaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mediaModalLabel">Complaint Media</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="Complaint Media" class="img-fluid" style="display: none;">
                    <video id="modalVideo" controls class="img-fluid" style="display: none;">
                        <source src="" type="">
                        Your browser does not support the video tag.
                    </video>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    
    <script>
        // Initialize maps
        <?php 
        $result->data_seek(0); // Reset result pointer
        while ($row = $result->fetch_assoc()): 
            if (!empty($row['location_lat']) && !empty($row['location_lng'])): 
        ?>
            try {
                var map<?= $row['complaint_id'] ?> = L.map('map<?= $row['complaint_id'] ?>').setView([<?= $row['location_lat'] ?>, <?= $row['location_lng'] ?>], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map<?= $row['complaint_id'] ?>);
                L.marker([<?= $row['location_lat'] ?>, <?= $row['location_lng'] ?>]).addTo(map<?= $row['complaint_id'] ?>);
            } catch(e) {
                document.getElementById('map<?= $row['complaint_id'] ?>').innerHTML = '<div class="text-muted small">Map unavailable</div>';
            }
        <?php 
            endif;
        endwhile; 
        ?>

        // Modal functions
        function openModal(src, type) {
            const modal = new bootstrap.Modal(document.getElementById('mediaModal'));
            const modalImage = document.getElementById('modalImage');
            const modalVideo = document.getElementById('modalVideo');
            
            if (type === 'image') {
                modalImage.src = src;
                modalImage.style.display = 'block';
                modalVideo.style.display = 'none';
            } else if (type === 'video') {
                modalVideo.querySelector('source').src = src;
                modalVideo.load();
                modalVideo.style.display = 'block';
                modalImage.style.display = 'none';
            }
            
            modal.show();
        }
    </script>
</body>
</html>