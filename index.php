<?php
session_start();
require_once 'config.php';

// Debug untuk melihat proses login
error_log("Starting login process");

$error_message = '';
$success_message = '';

// Cek pesan sukses dari registrasi
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
        $error_message = "Semua field harus diisi!";
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

                // Cek status banned terlebih dahulu
                if ($user["status"] === "banned") {
                    error_log("User is banned - Email: " . $email);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Akun Anda telah dibanned. Silakan hubungi admin di WhatsApp: 0895385890629 untuk informasi lebih lanjut.'
                    ]);
                    exit();
                }
                
                if (password_verify($password, $user["password"])) {
                    // Set session variables
                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["username"] = $user["name"];
                    $_SESSION["email"] = $user["email"];
                    $_SESSION["role"] = $user["role"];
                    $_SESSION["status"] = $user["status"];
                    
                    error_log("Session set - Role: " . $_SESSION["role"]); // Debug log
                    
                    // Pengecekan role untuk redirect
                    $role = $user["role"];
                    if ($role === 'admin,GOD' || $role === 'admin' || $role === 'GOD') {
                        $redirect_url = 'admin/dashboard.php';
                    } else {
                        $redirect_url = 'menu.php';
                    }

                    // Update last login time
                    $update_stmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                    $update_stmt->bind_param("i", $user["id"]);
                    $update_stmt->execute();
                    $update_stmt->close();

                    error_log("Redirecting to: " . $redirect_url);
                    echo json_encode(['success' => true, 'redirect' => $redirect_url]);
                    exit();
                } else {
                    $error_message = "Email atau password salah!";
                }
            } else {
                $error_message = "Email atau password salah!";
            }
            $stmt->close();
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error_message = "Terjadi kesalahan. Silakan coba lagi nanti.";
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/loginstyle.css">
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

            <p class="message">Tidak terdaftar? <a href="register.php">Buat akun</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const button = document.getElementById('loginButton');
        button.disabled = true;
        button.classList.add('loading');

        // Show loading animation
        showLoading();

        const formData = new FormData(this);

        fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showNotification('Login successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 2222);
                } else {
                    showNotification(data.message, 'error');
                    button.disabled = false;
                    button.classList.remove('loading');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
                button.disabled = false;
                button.classList.remove('loading');
            });
    });

    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            const target = document.getElementById(this.dataset.target);
            if (target.type === 'password') {
                target.type = 'text';
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            } else {
                target.type = 'password';
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            }
        });
    });

    // Loading animation functions
    function showLoading() {
        const loading = document.createElement('div');
        loading.id = 'loading-overlay';
        loading.innerHTML = '<div class="spinner"></div>';
        document.body.appendChild(loading);
    }

    function hideLoading() {
        const loading = document.getElementById('loading-overlay');
        if (loading) {
            loading.remove();
        }
    }

    // Notification functions
    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `notification ${type === 'success' ? 'notification-success' : 'notification-error'}`;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('show');
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 500);
            }, 5000);
        }, 500);
    }

    // Initialize particles.js
    particlesJS('particles-js', {
        particles: {
            number: {
                value: 80,
                density: {
                    enable: true,
                    value_area: 800
                }
            },
            color: {
                value: '#ffffff'
            },
            shape: {
                type: 'circle'
            },
            opacity: {
                value: 0.5,
                random: false,
                anim: {
                    enable: false
                }
            },
            size: {
                value: 3,
                random: true
            },
            line_linked: {
                enable: true,
                distance: 150,
                color: '#ffffff',
                opacity: 0.4,
                width: 1
            },
            move: {
                enable: true,
                speed: 6,
                direction: 'none',
                random: false,
                straight: false,
                out_mode: 'out',
                bounce: false
            }
        },
        interactivity: {
            detect_on: 'canvas',
            events: {
                onhover: {
                    enable: true,
                    mode: 'repulse'
                },
                onclick: {
                    enable: true,
                    mode: 'push'
                },
                resize: true
            }
        },
        retina_detect: true
    });
    </script>
</body>

</html>