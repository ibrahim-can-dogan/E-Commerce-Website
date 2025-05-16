<?php
// Include configuration file
require_once 'config/config.php';
require_once 'includes/database.php';
require_once 'includes/security.php';
require_once 'includes/mail.php';

// Set page title
$pageTitle = 'Verify Email';

// Check if user is set for verification
if (!isset($_SESSION['unverified_user_id'])) {
    // Redirect to login if no user to verify
    header('Location: login.php');
    exit;
}

// Initialize variables
$userId = $_SESSION['unverified_user_id'];
$verificationCode = '';
$error = '';
$success = '';
$resent = false;

// Connect to database
$conn = getConnection();

// Get user information
$stmt = $conn->prepare("SELECT u.email, u.user_type, COALESCE(m.market_name, c.fullname) AS name 
                        FROM users u 
                        LEFT JOIN market_profiles m ON u.id = m.user_id AND u.user_type = 'market'
                        LEFT JOIN consumer_profiles c ON u.id = c.user_id AND u.user_type = 'consumer'
                        WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    // User not found
    $error = 'User not found.';
} else {
    $email = $user['email'];
    $name = $user['name'];
    $userType = $user['user_type'];
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if resend button was clicked
        if (isset($_POST['resend'])) {
            // Generate new verification code
            $verificationCode = generateVerificationCode();
            $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 hours
            
            // Update verification code in database
            $stmt = $conn->prepare("UPDATE verification_codes SET code = ?, expires_at = ? WHERE user_id = ?");
            $stmt->execute([$verificationCode, $expiresAt, $userId]);
            
            // Send verification email
            if (sendVerificationEmail($email, $name, $verificationCode)) {
                $success = 'Verification code has been resent to your email.';
                $resent = true;
            } else {
                $error = 'Failed to send verification email. Please try again later.';
            }
        } else {
            // Verify code
            $verificationCode = isset($_POST['verification_code']) ? trim($_POST['verification_code']) : '';
            $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
            
            // Validate CSRF token
            if (!verifyCSRFToken($csrfToken)) {
                $error = 'Invalid request. Please try again.';
            }
            // Validate verification code
            elseif (empty($verificationCode)) {
                $error = 'Please enter the verification code.';
            } else {
                // Check verification code in database
                $stmt = $conn->prepare("SELECT id FROM verification_codes WHERE user_id = ? AND code = ? AND expires_at >= NOW()");
                $stmt->execute([$userId, $verificationCode]);
                $verifyResult = $stmt->fetch();
                
                if ($verifyResult) {
                    // Mark user as verified
                    $stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                    $stmt->execute([$userId]);
                    
                    // Delete verification code
                    $stmt = $conn->prepare("DELETE FROM verification_codes WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Set success message
                    $_SESSION['flash_message'] = 'Email verified successfully! You can now login.';
                    $_SESSION['flash_type'] = 'success';
                    
                    // Clear unverified user ID
                    unset($_SESSION['unverified_user_id']);
                    
                    // Redirect to login
                    header('Location: login.php');
                    exit;
                } else {
                    $error = 'Invalid or expired verification code. Please try again or request a new code.';
                }
            }
        }
    }
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Include header
include 'templates/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="form-container auth-form mx-auto my-5">
                <h2 class="text-center mb-4">Verify Your Email</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <p class="text-center mb-4">
                    We've sent a verification code to <strong><?php echo htmlspecialchars($email); ?></strong>.<br>
                    Please check your email and enter the code below.
                </p>
                
                <form action="verify.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="mb-4">
                        <label for="verification_code" class="form-label">Verification Code</label>
                        <input type="text" class="form-control verification-code-input" id="verification_code" name="verification_code" maxlength="6" placeholder="Enter 6-digit code" required>
                        <div class="invalid-feedback">Please enter the verification code.</div>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-success">Verify Email</button>
                    </div>
                </form>
                
                <hr>
                
                <form action="verify.php" method="POST" class="text-center">
                    <p>Didn't receive the code?</p>
                    <button type="submit" name="resend" class="btn btn-link" <?php echo $resent ? 'disabled' : ''; ?>>
                        Resend Verification Code
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'templates/footer.php';
?> 