<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
    $sql = "SELECT u.email, up.full_name, up.phone, up.address, up.profile_image
            FROM users u 
            LEFT JOIN user_profiles up ON u.id = up.user_id 
            WHERE u.id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'user' => [
                'full_name' => $row['full_name'] ?? $_SESSION['username'],
                'email' => $row['email'] ?? '',
                'phone' => $row['phone'] ?? '',
                'address' => $row['address'] ?? '',
                'profile_image' => $row['profile_image'] ?? 'default.jpg'
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>