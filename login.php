<?php
include_once 'includes/config.php';

// Redirect if already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("location: dashboard.php");
    exit;
}

$login_err = "";
$username = ""; // Initialize username for sticky form

// Handle login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Input Sanitization and Basic Validation
    $username = trim($_POST["login_username"] ?? '');
    $password = $_POST["login_password"] ?? '';
    
    if (empty($username) || empty($password)) {
        $login_err = "Please fill in both username and password.";
    }

    // Only proceed to database check if basic fields are filled
    if (empty($login_err)) {
        
        // 2. Prepare SQL statement (Select user details based on username)
        // Note: Using 'password_hash' column name from the register page implementation
        $sql = "SELECT user_id, username, password_hash, role FROM users WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $username);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($user_id, $db_username, $stored_hash, $role);
                    
                    if ($stmt->fetch()) {
                        
                        // 3. CRITICAL SECURITY FIX: Use password_verify()
                        if (password_verify($password, $stored_hash)) {
                            // Password is correct, start session
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"]  = $user_id;
                            $_SESSION["username"] = $db_username;
                            $_SESSION["role"]     = $role;

                            // Assuming log_action is defined
                            // log_action($user_id, "User logged in."); 
                            header("location: dashboard.php");
                            exit;
                        } else {
                            // Passwords do not match
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else {
                    // Username not found
                    $login_err = "Invalid username or password.";
                }
            } else {
                $login_err = "Oops! Something went wrong with the database query.";
            }
            $stmt->close();
        } else {
             $login_err = "Database connection error.";
        }
    }
}
$conn->close();

/* ---- Page-specific CSS hook (header.php injects this) ---- */
$page_css   = '<link rel="stylesheet" href="assets/auth.css?v=3">';
$body_class = 'auth';

include 'includes/header.php';
?>

<div class="auth-wrap">
    <section class="auth-card card">
        <div class="auth-accent"></div>

        <h1 class="auth-title">Login to Your Account</h1>
        <p class="auth-sub">Enter your credentials to continue.</p>

        <?php if (!empty($login_err)): ?>
            <div class="auth-alert error"><?= htmlspecialchars($login_err) ?></div>
        <?php endif; ?>

        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="auth-form">
            <label for="login_username"><b>Username</b></label>
            <input id="login_username" name="login_username" type="text" 
                   value="<?= htmlspecialchars($username) ?>"
                   required autocomplete="username">

            <label for="login_password"><b>Password</b></label>
            <input id="login_password" name="login_password" type="password" required autocomplete="current-password">

            <button class="btn primary auth-submit" type="submit">Login</button>
        </form>


        <p class="auth-switch">
            Don’t have an account?
            <a href="register.php"><b>Register here</b></a>
        </p>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
