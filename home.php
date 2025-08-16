<?php
session_start();
include 'includes/dbconnect.php'; // your DB connection
$message = "";

$userProfilePic = ''; // No default - user must have profile picture

// If user is logged in, fetch their profile picture
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $stmt_user = $conn->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
    if ($stmt_user) {
        $stmt_user->bind_param("i", $userId);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        if ($user = $result_user->fetch_assoc()) {
            if (!empty($user['profile_picture'])) {
                // Use the path exactly as stored in database
                $userProfilePic = $user['profile_picture'];
                // Debug: Show what path we're using
                // echo "<!-- Homepage profile pic path: " . htmlspecialchars($userProfilePic) . " -->";
            }
        }
        $stmt_user->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $ref_number = trim($_POST['ref_number']);

  if (!empty($ref_number)) {
    // Prepare SQL to fetch complaint
    $stmt = $conn->prepare("SELECT ref_number, status, title, created_at FROM complaints WHERE ref_number = ?");
    $stmt->bind_param("s", $ref_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
      $message = "✅ <b>Status:</b> " . $row['status'] . "<br><b>Title:</b> " . $row['title'] . "<br><b>Created At:</b> " . $row['created_at'];
    } else {
      $message = "❌ No complaint found for reference: <b>$ref_number</b>";
    }

    $stmt->close();

    // Redirect to prevent form resubmission
    $_SESSION['message'] = $message;
    header("Location: " . $_SERVER['PHP_SELF'] . "#status-result");
    exit();
  } else {
    $message = "⚠️ Please enter a valid reference number.";
  }
}

// Load message from session if available
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['message'])) {
  $message = $_SESSION['message'];
  unset($_SESSION['message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FixLanka</title>
  <link rel="stylesheet" href="home.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
  <!-- Navbar -->
  <header>
    <nav class="navbar">
      <img src="uploads/logos/FIXLANKA_LOGO.png" alt="FixLanka Logo" class="logo">
      <div class="hamburger" onclick="document.querySelector('.nav-links').classList.toggle('active')">
        <span></span>
        <span></span>
        <span></span>
      </div>
      <ul class="nav-links">
        <li><a href="#">Home</a></li>
        <li><a href="#how">How it Works</a></li>
        <li><a href="#departments">Departments</a></li>
        <li><a href="#reviews">Reviews</a></li>
        <?php if (isset($_SESSION['user_id'])): ?>
          <li><a href="Includes/citizen/my_complaints.php">My Complaints</a></li>
          <li><a href="Includes/logout.php">Logout</a></li>
          <?php if (!empty($userProfilePic)): ?>
          <li>
            <img src="<?php echo htmlspecialchars($userProfilePic); ?>" alt="Profile" class="nav-profile-pic" onclick="openProfileModal('<?php echo htmlspecialchars($userProfilePic); ?>')">
          </li>
          <?php endif; ?>
        <?php else: ?>
          <li><a href="Includes/login.php">Login</a></li>
          <li><a href="Includes/citizen/register.php" class="login-btn">Register</a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </header>

  <!-- Hero Section -->
  <section class="hero" style="background-image: url('uploads/Images/Home_bg.png');">
    <div class="hero-content">
      <h1 class="main-title">REPORT PUBLIC PROBLEMS FASTLY</h1>
      <p class="main-subtitle">Click to Pick. Snap to Fix!</p>
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="Includes/citizen/submit_complaint.php" class="btn">Report an Issue</a>
      <?php else: ?>
        <a href="Includes/login.php" class="btn">Report an Issue</a>
      <?php endif; ?>
      <form class="ref-check" method="POST">
        <input type="text" name="ref_number" placeholder="Enter Complain Reference Number" required>
        <button type="submit">Check Status</button>
      </form>
      <div id="status-result"></div>
      <?php if (!empty($message)) echo '<div class="complaint-status">' . $message . '</div>'; ?>
    </div>
  </section>

  <!-- How It Works -->
  <section class="how-it-works" id="how">
    <h2>HOW IT WORKS</h2>
    <div class="steps">
      <div><img src="uploads/icons/click.png" alt="Click" ><p><strong>Click</strong></p></div>
      <div><img src="uploads/icons/snap.png" alt="Snap"><p><strong>Snap</strong></p></div>
      <div><img src="uploads/icons/submit.png" alt="Submit"><p><strong>Submit</strong></p></div>
      <div><img src="uploads/icons/track.png" alt="Track"><p><strong>Track</strong></p></div>
    </div>


    <a href="Includes/citizen/register.php" class="btn">Register</a>
  </section>

  <!-- Departments Carousel -->
  <section class="carousel-section" id="departments">
    <h2>DEPARTMENTS</h2>
    <div class="swiper department-swiper">
      <div class="swiper-wrapper">
        <div class="swiper-slide"><img src="uploads/logos/CEB.png" alt="CEB"><p>CEB</p></div>
        <div class="swiper-slide"><img src="uploads/logos/RDA.png" alt="RDA"><p>RDA</p></div>
        <div class="swiper-slide"><img src="uploads/logos/SLTB.png" alt="SLTB"><p>SLTB</p></div>
        <div class="swiper-slide"><img src="uploads/logos/NWSDB.png" alt="NWSDB"><p>NWSDB</p></div>
        <div class="swiper-slide"><img src="uploads/logos/POLICE.png" alt="Police"><p>Police</p></div>
        <div class="swiper-slide"><img src="uploads/logos/DMC.png" alt="DMC"><p>DMC</p></div>
        <div class="swiper-slide"><img src="uploads/logos/MOH.png" alt="MOH"><p>MOH</p></div>
        <div class="swiper-slide"><img src="uploads/logos/SLR.png" alt="SLR"><p>SLR</p></div>
      </div>
      </br>
      </br>
      </br>
      <div class="swiper-pagination"></div>
    </div>
  </section>

  <!-- Reviews Carousel -->
    <section class="carousel-section-rv" id="reviews">
      <h2>REVIEWS</h2>
      <div class="swiper review-swiper">
        <div class="swiper-wrapper">
          <?php 
          include 'includes/dbconnect.php';
          $sql = "SELECT r.review_text, r.before_image, r.after_image, u.name, u.profile_picture 
                  FROM reviews r 
                  JOIN users u ON r.user_id = u.user_id 
                  ORDER BY r.created_at DESC 
                  LIMIT 10";
          $result = $conn->query($sql);
          if ($result && $result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                  // Use profile picture path as stored in database
                  $profilePic = !empty($row['profile_picture']) ? $row['profile_picture'] : '';
                  // Debug: Show review profile pic path
                  // echo "<!-- Review profile pic path: " . htmlspecialchars($profilePic) . " -->";
                  echo '<div class="swiper-slide review-card">';
                  echo '<div class="review-header">';
                  if (!empty($profilePic)) {
                      echo '<img src="' . htmlspecialchars($profilePic) . '" alt="Profile Picture" class="profile-pic" />';
                  }
                  echo '<h3 class="reviewer-name">' . htmlspecialchars($row['name']) . '</h3>';
                  echo '</div>';
                  echo '<div class="review-content">';
                  echo '<div class="images">';
                  echo '<div class="image-container">';
                  echo '<span class="image-label">Before</span>';
                  echo '<img src="' . htmlspecialchars($row['before_image']) . '" alt="Before">';
                  echo '</div>';
                  echo '<div class="image-container">';
                  echo '<span class="image-label">After</span>';
                  echo '<img src="' . htmlspecialchars($row['after_image']) . '" alt="After">';
                  echo '</div>';
                  echo '</div>';
                  echo '<p class="review-text">' . htmlspecialchars($row['review_text']) . '</p>';
                  echo '</div>';
                  echo '</div>';
              }
          } else {
              echo '<p>No reviews found.</p>';
          }
          ?>
        </div>
        </br>
        </br>  
        <div class="swiper-pagination"></div>
      </div>
      </br> 
      <a href="Includes/citizen/review.php" class="btn">Review</a>
    </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="footer-container">
        <div class="footer-about">
            <img src="uploads/logos/FIXLANKA_LOGO.png" alt="FixLanka Logo" class="footer-logo">
            <p>FixLanka is a platform dedicated to helping citizens report public infrastructure issues to the relevant authorities quickly and easily.</p>
        </div>
        <div class="footer-links">
            <h4>Quick Links</h4>
            <ul>
                <li><a href="#">Home</a></li>
                <li><a href="#how">How it Works</a></li>
                <li><a href="#departments">Departments</a></li>
                <li><a href="#reviews">Reviews</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                  <li><a href="Includes/citizen/my_complaints.php">My Complaints</a></li>
                <?php else: ?>
                  <li><a href="Includes/login.php">Login</a></li>
                  <li><a href="Includes/citizen/register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="footer-contact">
            <h4>Contact Us</h4>
            <p><i class="fas fa-map-marker-alt"></i> 123, Galle Road, Colombo 03, Sri Lanka</p>
            <p><i class="fas fa-envelope"></i> Email: support@fixlanka.lk</p>
            <p><i class="fas fa-phone"></i> Phone: +94 11 123 4567</p>
        </div>
        <div class="footer-social">
            <h4>Follow Us</h4>
            <div class="social-icons">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; 2025 FixLanka. All Rights Reserved.</p>
    </div>
  </footer>

  <!-- Profile Picture Modal -->
  <div id="profileModal" class="profile-modal">
    <div class="profile-modal-content">
      <span class="profile-modal-close" onclick="closeProfileModal()">&times;</span>
      <img id="profileModalImage" class="profile-modal-image" src="" alt="Profile Picture">
    </div>
  </div>

  <!-- Swiper JS Init -->
  <script>
    // Profile Picture Modal Functions
    function openProfileModal(imageSrc) {
      const modal = document.getElementById('profileModal');
      const modalImage = document.getElementById('profileModalImage');
      
      modalImage.src = imageSrc;
      modal.classList.add('show');
      
      // Prevent body scrolling when modal is open
      document.body.style.overflow = 'hidden';
    }

    function closeProfileModal() {
      const modal = document.getElementById('profileModal');
      modal.classList.remove('show');
      
      // Restore body scrolling
      document.body.style.overflow = 'auto';
    }

    // Close modal when clicking outside the image
    document.getElementById('profileModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeProfileModal();
      }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeProfileModal();
      }
    });

    const deptSwiper = new Swiper('.department-swiper', {
      loop: true,
      autoplay: { delay: 2500 },
      slidesPerView: 4,
      spaceBetween: 20,
      pagination: { el: '.swiper-pagination' },
      breakpoints: {
        640: { slidesPerView: 2 },
        768: { slidesPerView: 3 },
        1024: { slidesPerView: 4 },
        1440: { slidesPerView: 5 }
      }
    });

    const reviewSwiper = new Swiper('.review-swiper', {
      loop: true,
      autoplay: { delay: 3000 },
      slidesPerView: 3,
      spaceBetween: 30,
      pagination: { 
        el: '.swiper-pagination',
        clickable: true
      },
      breakpoints: {
        320: { slidesPerView: 1 },
        768: { slidesPerView: 2 },
        1024: { slidesPerView: 3 }
      }
    });
  </script>
</body>
</html>
