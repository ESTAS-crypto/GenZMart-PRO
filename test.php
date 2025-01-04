<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'genzmart';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Debug Information</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    :root {
        --primary-color: #2c3e50;
        --secondary-color: #34495e;
        --accent-color: #3498db;
        --success-color: #2ecc71;
        --warning-color: #f1c40f;
        --danger-color: #e74c3c;
        --light-bg: #f8f9fa;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        line-height: 1.6;
        margin: 0;
        padding: 20px;
        background-color: var(--light-bg);
        color: var(--primary-color);
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
    }

    h1,
    h2,
    h3 {
        color: var(--primary-color);
        border-bottom: 2px solid var(--accent-color);
        padding-bottom: 10px;
        margin-top: 30px;
    }

    h1 {
        text-align: center;
        font-size: 2.5em;
        margin-bottom: 40px;
    }

    .debug-section {
        background: var(--light-bg);
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .status-icon {
        margin-right: 10px;
    }

    .success {
        color: var(--success-color);
    }

    .warning {
        color: var(--warning-color);
    }

    .error {
        color: var(--danger-color);
    }

    pre {
        background: #2c3e50;
        color: #ecf0f1;
        padding: 15px;
        border-radius: 5px;
        overflow-x: auto;
        font-family: 'Consolas', monospace;
    }

    .btn {
        display: inline-block;
        padding: 12px 24px;
        background: var(--danger-color);
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .file-list {
        list-style: none;
        padding: 0;
    }

    .file-list li {
        padding: 10px;
        margin: 5px 0;
        background: white;
        border-radius: 5px;
        border-left: 4px solid var(--accent-color);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
    }

    th,
    td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    th {
        background-color: var(--secondary-color);
        color: white;
    }

    tr:nth-child(even) {
        background-color: #f5f5f5;
    }

    .refresh-btn {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: var(--accent-color);
        color: white;
        padding: 15px;
        border-radius: 50%;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .refresh-btn:hover {
        transform: rotate(180deg);
        background: var(--secondary-color);
    }
    </style>
</head>

<body>
    <div class="container">
        <h1><i class="fas fa-bug"></i> System Debug Information</h1>

        <!-- Session Data -->
        <div class="debug-section">
            <h2><i class="fas fa-key"></i> Session Data</h2>
            <pre><?php print_r($_SESSION); ?></pre>
        </div>

        <!-- Database Connection -->
        <div class="debug-section">
            <h2><i class="fas fa-database"></i> Database Connection Test</h2>
            <?php
            try {
                if (!$conn->connect_error) {
                    echo "<p class='success'><i class='fas fa-check-circle status-icon'></i> Database connected successfully</p>";
                    
                    // User Data Check
                    if (isset($_SESSION['user_id'])) {
                        $user_id = $_SESSION['user_id'];
                        $stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result && $result->num_rows > 0) {
                            echo "<h3>User Data:</h3>";
                            echo "<pre>";
                            print_r($result->fetch_assoc());
                            echo "</pre>";
                        } else {
                            echo "<p class='warning'><i class='fas fa-exclamation-triangle status-icon'></i> User not found in database</p>";
                        }
                        $stmt->close();
                    } else {
                        echo "<p class='warning'><i class='fas fa-exclamation-triangle status-icon'></i> No user_id in session</p>";
                    }

                    // Available Roles
                    echo "<h3>Available Roles:</h3>";
                    $roles = $conn->query("SELECT DISTINCT role FROM users");
                    if ($roles) {
                        echo "<ul class='file-list'>";
                        while ($role = $roles->fetch_assoc()) {
                            echo "<li><i class='fas fa-user-tag'></i> " . htmlspecialchars($role['role']) . "</li>";
                        }
                        echo "</ul>";
                    }
                } else {
                    echo "<p class='error'><i class='fas fa-times-circle status-icon'></i> Database connection failed</p>";
                }
            } catch (Exception $e) {
                echo "<p class='error'><i class='fas fa-times-circle status-icon'></i> Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            ?>
        </div>

        <!-- Server Information -->
        <div class="debug-section">
            <h2><i class="fas fa-server"></i> Server Information</h2>
            <table>
                <tr>
                    <th>Information</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>PHP Version</td>
                    <td><?php echo phpversion(); ?></td>
                </tr>
                <tr>
                    <td>Server Software</td>
                    <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                </tr>
                <tr>
                    <td>Document Root</td>
                    <td><?php echo $_SERVER['DOCUMENT_ROOT']; ?></td>
                </tr>
                <tr>
                    <td>Script Path</td>
                    <td><?php echo $_SERVER['SCRIPT_FILENAME']; ?></td>
                </tr>
                <tr>
                    <td>Server Time</td>
                    <td><?php echo date('Y-m-d H:i:s'); ?></td>
                </tr>
                <tr>
                    <td>Memory Usage</td>
                    <td><?php echo round(memory_get_usage() / 1024 / 1024, 2) . ' MB'; ?></td>
                </tr>
            </table>
        </div>

        <!-- File Permissions -->
        <div class="debug-section">
            <h2><i class="fas fa-file-alt"></i> File Permissions Check</h2>
            <?php
            $files_to_check = [
                'config.php',
                'index.php',
                'admin/dashboard.php',
                'admin/items.php',
                'admin/categories.php'
            ];

            echo "<ul class='file-list'>";
            foreach ($files_to_check as $file) {
                if (file_exists($file)) {
                    $perms = fileperms($file);
                    $perms_str = substr(sprintf('%o', $perms), -4);
                    echo "<li class='success'><i class='fas fa-check-circle status-icon'></i>" . 
                         htmlspecialchars($file) . " (Permissions: " . $perms_str . ")</li>";
                } else {
                    echo "<li class='error'><i class='fas fa-times-circle status-icon'></i>" . 
                         htmlspecialchars($file) . " not found or not readable</li>";
                }
            }
            echo "</ul>";
            ?>
        </div>

        <!-- Clear Session Button -->
        <form method="post" style="text-align: center; margin-top: 30px;">
            <button type="submit" name="clear_session" class="btn">
                <i class="fas fa-trash-alt"></i> Clear Session
            </button>
        </form>

        <?php
        if (isset($_POST['clear_session'])) {
            session_destroy();
            echo '<p class="success" style="text-align: center; margin-top: 20px;">
                    <i class="fas fa-check-circle"></i> Session cleared! 
                    <a href="index.php" style="color: var(--success-color);">Go to login</a>
                  </p>';
        }
        ?>
    </div>

    <a href="?refresh=<?php echo time(); ?>" class="refresh-btn" title="Refresh">
        <i class="fas fa-sync-alt"></i>
    </a>
</body>

</html>