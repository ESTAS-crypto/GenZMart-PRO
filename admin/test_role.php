<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "<h2>Debugging Information</h2>";

// 1. Session Data
echo "<h3>Session Data:</h3>";
print_r($_SESSION);

// 2. Database Connection Test
echo "\n<h3>Database Connection Test:</h3>";
require_once 'config.php';
if (isset($conn) && !$conn->connect_error) {
    echo "✅ Database connected successfully\n";
    
    // 3. User Data Check
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            echo "\nUser Data from Database:\n";
            print_r($result->fetch_assoc());
        } else {
            echo "\n❌ User not found in database\n";
        }
        $stmt->close();
    } else {
        echo "\n⚠️ No user_id in session\n";
    }
    
    // 4. Role Check
    echo "\nAvailable Roles in Database:\n";
    $roles = $conn->query("SELECT DISTINCT role FROM users");
    if ($roles) {
        while ($role = $roles->fetch_assoc()) {
            echo "- " . $role['role'] . "\n";
        }
    }
} else {
    echo "❌ Database connection failed\n";
}

// 5. Server Information
echo "\n<h3>Server Information:</h3>";
echo "PHP Version: " . phpversion() . "\n";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Path: " . $_SERVER['SCRIPT_FILENAME'] . "\n";

// 6. File Permissions
echo "\n<h3>File Permissions Check:</h3>";
$files_to_check = [
    'config.php',
    'index.php',
    'admin/dashboard.php',
    'admin/items.php',
    'admin/categories.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "✅ " . $file . " exists and is readable\n";
    } else {
        echo "❌ " . $file . " not found or not readable\n";
    }
}

echo "</pre>";

// Tambahkan tombol untuk clear session
echo '<form method="post">';
echo '<input type="submit" name="clear_session" value="Clear Session">';
echo '</form>';

if (isset($_POST['clear_session'])) {
    session_destroy();
    echo '<p>Session cleared! <a href="index.php">Go to login</a></p>';
}
?>

<style>
pre {
    background: #f4f4f4;
    padding: 15px;
    border-radius: 5px;
}

h2,
h3 {
    color: #333;
}

form {
    margin-top: 20px;
}

input[type="submit"] {
    padding: 10px 20px;
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}
</style>