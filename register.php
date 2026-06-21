<?php
include_once 'includes/config.php';

// Redirect if already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("location: dashboard.php");
    exit;
}

// Initialize variables to hold errors and input values
$username = $email = $password = "";
$username_err = $email_err = $password_err = $reg_success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Get and trim input values
    $username = trim($_POST["reg_username"]);
    $email    = trim($_POST["reg_email"]);
    $password = $_POST["reg_password"];

    // --- 2. Input Validation ---

    // Validate Username
    if (empty($username)) {
    $username_err = "Please enter a username.";
} elseif (strlen($username) < 5) {
    $username_err = "Username must have at least 5 letters.";
} elseif (!preg_match('/^[A-Za-z]{5,}[A-Za-z0-9_]*$/', $username)) {
    $username_err = "Username must start with at least 5 letters and may include numbers or underscores after.";
}
    // Validate Email 📧 (9 digits @my.richfield.ac.za)
    if (empty($email)) {
    $email_err = "Please enter an email.";
} else {
    // Accept only student (40xxxxxxx@my.richfield.ac.za) or lecturer (name@richfield.ac.za)
    $richfield_pattern = '/^(?:402\d{6}@my\.richfield\.ac\.za|[a-zA-Z0-9._%+-]+@richfield\.ac\.za)$/i';

    if (!preg_match($richfield_pattern, $email)) {
        $email_err = "Invalid email format. Use only:
        Student: 402xxxxxx@my.richfield.ac.za |
        Lecturer: name@richfield.ac.za";
    }
}

    // Validate Password 🔒
    if (empty($password)) {
        $password_err = "Please enter a password.";
    } elseif (strlen($password) < 8) {
        $password_err = "Password must be at least 8 characters long.";
    } else {
        $password_pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d\s]).{8,}$/';
        
        if (!preg_match($password_pattern, $password)) {
            $password_err = "Password must contain: minimum 8 characters, at least one uppercase letter, one lowercase letter, one number, and one special character.";
        }
    }
    
    // --- Check for existing username/email ---
    if (empty($username_err) && empty($email_err) && empty($password_err)) {
        $sql_check = "SELECT username FROM users WHERE username = ? OR email = ?";
        if ($stmt_check = $conn->prepare($sql_check)) {
            $stmt_check->bind_param("ss", $username, $email);
            $stmt_check->execute();
            $stmt_check->store_result();
            
            if ($stmt_check->num_rows > 0) {
                $username_err = "Username or Email already exists. Please choose a different one.";
            }
            $stmt_check->close();
        } else {
            $username_err = "Database error during check.";
        }
    }

    // --- 3. Insert into Database and Redirect ---
    if (empty($username_err) && empty($email_err) && empty($password_err)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'Student')";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sss", $username, $email, $hashed_password);
            
            if ($stmt->execute()) {
                // ✅ Redirect to login page after successful registration
                header("Location: login.php?registered=success");
                exit;
            } else {
                if ($conn->errno == 1062) {
                    $username_err = "Username or Email already exists. Please choose a different one.";
                } else {
                    $username_err = "An unknown error occurred during registration. (" . $conn->error . ")";
                }
            }
            $stmt->close();
        } else {
            $username_err = "Database error during registration setup.";
        }
    }
}
$conn->close();

/* Page-specific CSS hook */
$page_css   = '<link rel="stylesheet" href="assets/auth.css?v=3">';
$body_class = 'auth';

include 'includes/header.php';
?>
<html>
<head>
<title>REGISTER</title>
</head>

<body>
<div class="auth-wrap">
    <section class="auth-card card">
        <div class="auth-accent"></div>

        <h1 class="auth-title">Register a New Account</h1>
        <p class="auth-sub">Create your student profile to start reporting or claiming items.</p>
		
		<?php if (!empty($reg_success)): ?>
            <div class="auth-alert success"><?= htmlspecialchars($reg_success) ?></div>
        <?php endif; ?>
		
		<?php if (!empty($username_err) || !empty($email_err)): ?>
            <div class="auth-alert error">
                <?= htmlspecialchars($username_err ?: $email_err) ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="post">
            <label for="reg_username">Username</label>
            <input id="reg_username" name="reg_username" type="text" required>
            <?php if (!empty($username_err)): ?><p class="error-inline"><?= htmlspecialchars($username_err) ?></p><?php endif; ?>

            <label for="reg_email">Email (9 digits @my.richfield.ac.za | name@richfield.ac.za)</label>
            <input id="reg_email" name="reg_email" type="email" required>
            <?php if (!empty($email_err)): ?><p class="error-inline"><?= htmlspecialchars($email_err) ?></p><?php endif; ?>

            <label for="reg_password">Password</label>
            <input id="reg_password" name="reg_password" type="password" required>
            <?php if (!empty($password_err)): ?><p class="error-inline"><?= htmlspecialchars($password_err) ?></p><?php endif; ?>

            <button class="btn primary auth-submit" type="submit">Register</button>
        </form>

        <p class="auth-switch">
            Already have an account?
            <a href="login.php"><b>Login here</b></a>.
        </p>
    </section>
</div>
</body>
</html>

<style>
.error-inline {
    color: #dc3545; 
    font-size: 0.85em;
    margin-top: -10px;
    margin-bottom: 10px;
}
</style>

<?php include 'includes/footer.php'; ?>