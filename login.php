<?php
// Include configuration file
require_once 'config/config.php';
require_once 'includes/database.php';
require_once 'includes/security.php';

// Set page title
$pageTitle = 'Login';

// Initialize variables
$email = '';
$error = '';
$success = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get input values
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    // Validate CSRF token
    if (!verifyCSRFToken($csrfToken)) {
        $error = 'Invalid request. Please try again.';
    }
    // Validate email
    elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    }
    // Validate password
    elseif (empty($password)) {
        $error = 'Please enter your password.';
    }
    else {
        // Connect to database
        $conn = getConnection();
        
        // Prepare SQL statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, email, password, user_type, is_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Verify password
            if (verifyPassword($password, $user['password'])) {
                // Check if user is verified
                if ($user['is_verified']) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_type'] = $user['user_type'];
                    
                    // Get user profile data
                    if ($user['user_type'] === 'market') {
                        $profileStmt = $conn->prepare("SELECT id FROM market_profiles WHERE user_id = ?");
                    } else {
                        $profileStmt = $conn->prepare("SELECT id FROM consumer_profiles WHERE user_id = ?");
                    }
                    
                    $profileStmt->execute([$user['id']]);
                    $profile = $profileStmt->fetch();
                    
                    if ($profile) {
                        $_SESSION['profile_id'] = $profile['id'];
                    }
                    
                    // Set success flash message
                    $_SESSION['flash_message'] = 'Login successful. Welcome back!';
                    $_SESSION['flash_type'] = 'success';
                    
                    // Redirect based on user type
                    if ($user['user_type'] === 'market') {
                        header('Location: market/products.php');
                    } else {
                        header('Location: index.php');
                    }
                    exit;
                } else {
                    // User not verified
                    $_SESSION['unverified_user_id'] = $user['id'];
                    header('Location: verify.php');
                    exit;
                }
            } else {
                $error = 'Invalid email or password. Please try again.';
            }
        } else {
            $error = 'Invalid email or password. Please try again.';
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
                <h2 class="text-center mb-4">Login</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form action="login.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="invalid-feedback">Please enter your password.</div>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-success">Login</button>
                    </div>
                    
                    <p class="text-center">Don't have an account? <a href="register.php">Register</a></p>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'templates/footer.php';
?> 