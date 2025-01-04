<?php
session_start();

// Simpan role sebelum session destroy
$userRole = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '';
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$email = isset($_SESSION['email']) ? $_SESSION['email'] : '';

// Hapus semua session
session_destroy();

// Start session baru
session_start();

// Tentukan redirect berdasarkan parameter
$redirect = $_GET['redirect'] ?? 'login';

if ($redirect === 'menu') {
    // Jika redirect ke menu, restore session untuk akses menu
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = $userRole;
    
    header('Location: ../menu.php');
} else {
    // Jika redirect ke login, biarkan session kosong
    header('Location: ../index.php');
}
exit();
?>