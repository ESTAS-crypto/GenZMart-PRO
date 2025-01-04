<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize messages
$error_message = '';
$success_message = '';

// Debug function
function debug_log($message) {
    error_log("[Register Debug] " . $message);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    debug_log("Form submitted");
    
    // Sanitize and get input
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    debug_log("Received data - Username: $username, Email: $email");

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "Semua bidang wajib diisi.";
        debug_log("Error: Bidang kosong terdeteksi");
    } elseif ($password !== $confirm_password) {
        $error_message = "Kata sandi tidak cocok.";
        debug_log("Error: Ketidakcocokan kata sandi");
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
        debug_log("Error: Invalid email format");
    } elseif (strlen($username) < 5) {
        $error_message = "Username must be at least 5 characters long.";
        debug_log("Error: Username too short");
    } else {
        try {
            debug_log("Starting database operations");
            
            // Check database connection
            if (!$conn || $conn->connect_error) {
                throw new Exception("Database connection failed: " . ($conn ? $conn->connect_error : "Connection is null"));
            }
            debug_log("Database connection successful");

            // Begin transaction
            $conn->begin_transaction();
            
            // Check for existing email
            $check_sql = "SELECT id FROM users WHERE email = ?";
            $check_stmt = $conn->prepare($check_sql);
            if (!$check_stmt) {
                throw new Exception("Failed to prepare email check statement: " . $conn->error);
            }

            $check_stmt->bind_param("s", $email);
            if (!$check_stmt->execute()) {
                throw new Exception("Failed to execute email check: " . $check_stmt->error);
            }

            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "Email already registered.";
                debug_log("Error: Email already exists");
            } else {
                debug_log("Email check passed, proceeding with registration");
                
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = "customer";
                
                // Prepare insert statement
                $sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Failed to prepare insert statement: " . $conn->error);
                }

                $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
                
                // Execute insert
                if ($stmt->execute()) {
                    debug_log("User inserted successfully. Last insert ID: " . $conn->insert_id);
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $_SESSION['registration_success'] = true;
                    $_SESSION['success_message'] = "Pendaftaran berhasil! Silakan login dengan akun baru yang anda telah daftarkan Anda.";
                    
                    debug_log("Pendaftaran berhasil, dialihkan ke halaman awal/login");
                    
                    // Close statements
                    $stmt->close();
                    $check_stmt->close();
                    
                    // Redirect to login page
                    header("Location: index.php");
                    exit();
                } else {
                    throw new Exception("Insert failed: " . $stmt->error);
                }
            }
            
            // Close statement
            $check_stmt->close();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($conn && $conn->connect_error === null) {
                $conn->rollback();
            }
            
            debug_log("Error occurred: " . $e->getMessage());
            $error_message = "An error occurred during registration. Please try again later. Error: " . $e->getMessage();
        }
    }
}

// Debug GET request
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    debug_log("Page loaded via GET request");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - GenZMart</title>
    <link rel="stylesheet" href="../GenZMart.com/css/loginstyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div id="particles-js"></div>

    <div class="logo-container">
        <div class="form">
            <img src="img/logo-genzmart.png" alt="GenZMart Logo" class="Logo">

            <h2 id="welcome-back" class="form-title">Create Account</h2>

            <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <!-- Form with explicit action and method -->
            <form id="registerForm" action="register.php" method="POST" novalidate>
                <div class="input-group">
                    <input type="text" id="reg-username" name="username" required>
                    <label for="reg-username">Username</label>
                </div>

                <div class="input-group">
                    <input type="email" name="email" id="reg-email" required>
                    <label for="reg-email">Email</label>
                </div>

                <div class="password-container">
                    <input type="password" name="password" id="reg-password" required>
                    <label for="reg-password">Password</label>
                    <i class="fas fa-eye-slash toggle-password" data-target="reg-password"></i>
                </div>

                <div class="password-container">
                    <input type="password" name="confirm_password" id="reg-confirm-password" required>
                    <label for="reg-confirm-password">Confirm Password</label>
                    <i class="fas fa-eye-slash toggle-password" data-target="reg-confirm-password"></i>
                </div>

                <button type="submit" class="submit-btn">Create Account</button>
            </form>

            <p class="message">Already registered? <a href="index.php">Sign In</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script src="loginsc.js"></script>
</body>

</html>