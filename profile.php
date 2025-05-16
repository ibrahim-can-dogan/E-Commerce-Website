<?php
// Include configuration file
require_once 'config/config.php';
require_once 'includes/database.php';
require_once 'includes/security.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login if not logged in
    header('Location: login.php');
    exit;
}

// Set page title
$pageTitle = 'Profile';

// Initialize variables
$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];
$email = '';
$fullname = '';
$marketName = '';
$city = '';
$district = '';
$error = '';
$success = '';


// Connect to database

$conn = getConnection();

// Get user information
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result && isset($result['email'])) {
    $user = $result;
    $email = $user['email'];
    
    // Get profile information based on user type
    if ($userType === 'market') {
        $stmt = $conn->prepare("SELECT market_name, city, district FROM market_profiles WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (count($result) === 1) {
            $profile = $result;
            $marketName = $profile['market_name'];
            $city = $profile['city'];
            $district = $profile['district'];
        }
    } else { // Consumer
        $stmt = $conn->prepare("SELECT fullname, city, district FROM consumer_profiles WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (count($result) === 1) {
            $profile = $result->fetch_assoc();
            $fullname = $profile['fullname'];
            $city = $profile['city'];
            $district = $profile['district'];
        }
    }
}

// Process profile update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    $district = isset($_POST['district']) ? trim($_POST['district']) : '';
    
    // User type specific fields
    if ($userType === 'market') {
        $marketName = isset($_POST['market_name']) ? trim($_POST['market_name']) : '';
    } else {
        $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
    }
    
    // Sanitize inputs
    $city = sanitizeInput($city);
    $district = sanitizeInput($district);
    
    if ($userType === 'market') {
        $marketName = sanitizeInput($marketName);
    } else {
        $fullname = sanitizeInput($fullname);
    }
    
    // Validate CSRF token
    if ( !verifyCSRFToken($csrfToken)) {
        $error = 'Invalid request. Please try again.';
    }
    // Validate required fields
    elseif (empty($city)) {
        $error = 'Please enter your city.';
    }
    elseif (empty($district)) {
        $error = 'Please enter your district.';
    }
    elseif ($userType === 'market' && empty($marketName)) {
        $error = 'Please enter your market name.';
    }
    elseif ($userType === 'consumer' && empty($fullname)) {
        $error = 'Please enter your full name.';
    }
    // Validate password change if requested
    elseif (!empty($newPassword) || !empty($currentPassword)) {
        if (empty($currentPassword)) {
            $error = 'Please enter your current password.';
        }
        elseif (empty($newPassword)) {
            $error = 'Please enter a new password.';
        }
        elseif (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters long.';
        }
        elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $userData = $result;
            
            if (!verifyPassword($currentPassword, $userData['password'])) {
                $error = 'Current password is incorrect.';
            }
        }
    }
    
    // If no errors, update profile
    if (empty($error)) {        
        try {
            // Update profile based on user type
            $conn->beginTransaction();
            if ($userType === 'market') {
                $stmt = $conn->prepare("UPDATE market_profiles SET market_name = ?, city = ?, district = ? WHERE user_id = ?");
                $stmt->execute([$marketName, $city, $district, $userId]);
            } else {
                $stmt = $conn->prepare("UPDATE consumer_profiles SET fullname = ?, city = ?, district = ? WHERE user_id = ?");
                $stmt->execute([$fullname, $city, $district, $userId]);
            }
            
            // Update password if requested
            if (!empty($newPassword)) {
                $hashedPassword = hashPassword($newPassword);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $userId]);
            }
            
            // Commit transaction
            $conn->commit();
            
            // Set success message
            $success = 'Profile updated successfully.';
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = 'Profile update failed. Please try again later.';
        }
    }
}

// Close database connection
$conn = null;
// Include header
include 'templates/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="form-container my-5">
                <h2 class="text-center mb-4">My Profile</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form action="profile.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($email); ?>" disabled>
                        <div class="form-text">Email cannot be changed</div>
                    </div>
                    
                    <?php if ($userType === 'market'): ?>
                        <div class="mb-3">
                            <label for="market_name" class="form-label">Market Name</label>
                            <input type="text" class="form-control" id="market_name" name="market_name" value="<?php echo htmlspecialchars($marketName); ?>" required>
                            <div class="invalid-feedback">Please enter your market name.</div>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label for="fullname" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="fullname" name="fullname" value="<?php echo htmlspecialchars($fullname); ?>" required>
                            <div class="invalid-feedback">Please enter your full name.</div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label">City</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($city); ?>" required>
                            <div class="invalid-feedback">Please enter your city.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="district" class="form-label">District</label>
                            <input type="text" class="form-control" id="district" name="district" value="<?php echo htmlspecialchars($district); ?>" required>
                            <div class="invalid-feedback">Please enter your district.</div>
                        </div>
                    </div>
                    
                    <h4 class="mt-4 mb-3">Change Password</h4>
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password">
                        <div class="form-text">Leave blank if you don't want to change your password</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="8">
                            <div class="invalid-feedback">Password must be at least 8 characters.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            <div class="invalid-feedback">Passwords must match.</div>
                        </div>
                    </div>
                    
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-success">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'templates/footer.php';
?> 