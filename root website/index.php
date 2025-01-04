<?php
session_start();
require_once 'config.php';

// Debug untuk melihat proses login
error_log("Starting login process");

$error_message = '';
$success_message = '';

if (isset($_SESSION['registration_success'])) {
    $success_message = $_SESSION['success_message'] ?? "Registration successful! Please login.";
    unset($_SESSION['registration_success']);
    unset($_SESSION['success_message']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var(trim($_POST["email"] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST["password"] ?? '';
    
    error_log("Login attempt - Email: " . $email);

    if (empty($email) || empty($password)) {
        $error_message = "All fields are required.";
    } else {
        try {
            $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
                error_log("User found - Role: " . $user["role"]); // Debug log
                
                if (password_verify($password, $user["password"])) {
                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["username"] = $user["name"];
                    $_SESSION["email"] = $user["email"];
                    $_SESSION["role"] = $user["role"];
                    
                    error_log("Session set - Role: " . $_SESSION["role"]); // Debug log
                    
                    // Pengecekan role yang menerima 'admin,GOD'
                    $role = $user["role"];
                    if ($role === 'admin,GOD' || $role === 'admin' || $role === 'GOD') {
                        $redirect_url = 'admin/dashboard.php';
                    } else {
                        $redirect_url = 'menu.php';
                    }

                    error_log("Redirecting to: " . $redirect_url);
                    echo json_encode(['success' => true, 'redirect' => $redirect_url]);
                    exit();
                }
            }
            $error_message = "Invalid email or password.";
            $stmt->close();
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error_message = "An error occurred. Please try again later.";
        }
    }
    if (!empty($error_message)) {
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GenZMart</title>
    <link rel="stylesheet" href="loginstyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div id="particles-js"></div>

    <div class="logo-container">
        <div class="form">
            <img src="img/logo-genzmart.png" alt="GenZMart Logo" class="Logo">
            <h2 id="welcome-back" class="form-title">Welcome Back</h2>

            <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <form id="loginForm" method="POST">
                <div class="input-group">
                    <input type="email" name="email" id="login-email" required autocomplete="email">
                    <label for="login-email">Email</label>
                </div>

                <div class="password-container">
                    <input type="password" name="password" id="login-password" required autocomplete="current-password">
                    <label for="login-password">Password</label>
                    <i class="fas fa-eye-slash toggle-password" data-target="login-password"></i>
                </div>

                <button type="submit" class="submit-btn" id="loginButton">Login</button>
            </form>

            <p class="message">Not registered? <a href="register.php">Create an account</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const button = document.getElementById('loginButton');
        button.disabled = true;
        button.classList.add('loading');

        const formData = new FormData(this);

        fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    showNotification(data.message, 'error');
                    button.disabled = false;
                    button.classList.remove('loading');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
                button.disabled = false;
                button.classList.remove('loading');
            });
    });
    </script>
    <script src="loginsc.js"></script>
</body>

</html>