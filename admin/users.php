<?php
session_start();
require_once '../config.php';

// Access Control
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../index.php');
    exit();
}

// Role Check
$userRole = $_SESSION['role'];
$isGod = (strtoupper($userRole) === 'GOD' || stripos($userRole, 'GOD') !== false);
$isAdmin = (strtoupper($userRole) === 'ADMIN' || $isGod);

if (!$isAdmin) {
    header('Location: ../menu.php');
    exit();
}

$adminName = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
$error_message = '';
$success_message = '';

// Form handling untuk add/edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $name = trim($_POST['name']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $role = $_POST['role'];
    $status = $_POST['status'];
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Validasi dasar
    if (empty($name) || empty($email)) {
        $error_message = "Nama dan email harus diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format email tidak valid!";
    } else {
        try {
            $conn->begin_transaction();

            if ($_POST['action'] === 'add') {
                // Cek email unik
                $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $check->bind_param("s", $email);
                $check->execute();
                
                if ($check->get_result()->num_rows > 0) {
                    throw new Exception("Email sudah digunakan!");
                }

                // Handle password
                $default_password = "GenZmart#1";
                $actual_password = empty($password) ? $default_password : $password;
                $hashed_password = password_hash($actual_password, PASSWORD_DEFAULT);

                // Tambah user baru
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $name, $email, $hashed_password, $role, $status);
                
                if ($stmt->execute()) {
                    $userId = $conn->insert_id;
                    
                    // Buat profil user
                    $profile_stmt = $conn->prepare("INSERT INTO user_profiles (user_id, full_name, phone, address) VALUES (?, ?, ?, ?)");
                    $profile_stmt->bind_param("isss", $userId, $full_name, $phone, $address);
                    
                    if (!$profile_stmt->execute()) {
                        throw new Exception("Gagal membuat profil user!");
                    }
                    
                    $success_message = empty($password) ? 
                        "User berhasil ditambahkan! Password default: GenZmart#1" : 
                        "User berhasil ditambahkan dengan password yang ditentukan!";
                } else {
                    throw new Exception("Gagal menambahkan user!");
                }
            } 
            elseif ($_POST['action'] === 'edit') {
                $user_id = (int)$_POST['user_id'];
                
                // Cek jika mengedit user GOD
                $check = $conn->prepare("SELECT role FROM users WHERE id = ?");
                $check->bind_param("i", $user_id);
                $check->execute();
                $result = $check->get_result();
                $current_user = $result->fetch_assoc();

                if ($current_user['role'] === 'GOD' && !$isGod) {
                    throw new Exception("Hanya user GOD yang dapat mengedit akun GOD!");
                }

                // Update user info
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=?, status=? WHERE id=?");
                $stmt->bind_param("ssssi", $name, $email, $role, $status, $user_id);
                
                if ($stmt->execute()) {
                    // Update atau buat profil
                    $check_profile = $conn->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
                    $check_profile->bind_param("i", $user_id);
                    $check_profile->execute();
                    
                    if ($check_profile->get_result()->num_rows > 0) {
                        // Update profil yang ada
                        $update_profile = $conn->prepare("UPDATE user_profiles SET full_name=?, phone=?, address=? WHERE user_id=?");
                        $update_profile->bind_param("sssi", $full_name, $phone, $address, $user_id);
                        if (!$update_profile->execute()) {
                            throw new Exception("Gagal mengupdate profil user!");
                        }
                    } else {
                        // Buat profil baru
                        $insert_profile = $conn->prepare("INSERT INTO user_profiles (user_id, full_name, phone, address) VALUES (?, ?, ?, ?)");
                        $insert_profile->bind_param("isss", $user_id, $full_name, $phone, $address);
                        if (!$insert_profile->execute()) {
                            throw new Exception("Gagal membuat profil user!");
                        }
                    }
                    
                    $success_message = "User berhasil diupdate!";
                } else {
                    throw new Exception("Gagal mengupdate user!");
                }
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

// Handle delete user
if (isset($_GET['delete'])) {
    if (!$isGod) {
        $error_message = "Hanya user GOD yang dapat menghapus akun!";
    } else {
        $delete_id = (int)$_GET['delete'];
        
        try {
            $conn->begin_transaction();
            
            // Cek jika menghapus user GOD
            $check = $conn->prepare("SELECT role FROM users WHERE id = ?");
            $check->bind_param("i", $delete_id);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                if ($user['role'] === 'GOD') {
                    throw new Exception("Tidak dapat menghapus user GOD!");
                }
                
                // Hapus user (profil akan terhapus otomatis karena ON DELETE CASCADE)
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $delete_id);
                
                if ($stmt->execute()) {
                    $success_message = "User berhasil dihapus!";
                } else {
                    throw new Exception("Gagal menghapus user!");
                }
            }
            
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Handle reset password
if (isset($_GET['reset'])) {
    if (!$isGod) {
        $error_message = "Hanya user GOD yang dapat mereset password!";
    } else {
        $reset_id = (int)$_GET['reset'];
        $default_password = "GenZmart#1";
        $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
        
        try {
            $conn->begin_transaction();
            
            // Cek user yang akan direset
            $check = $conn->prepare("SELECT role FROM users WHERE id = ?");
            $check->bind_param("i", $reset_id);
            $check->execute();
            $result = $check->get_result();
            $user = $result->fetch_assoc();
            
            if ($user['role'] === 'GOD' && !$isGod) {
                throw new Exception("Hanya user GOD yang dapat mereset password akun GOD!");
            }
            
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $reset_id);
            
            if ($stmt->execute()) {
                $success_message = "Password berhasil direset ke: GenZmart#1";
            } else {
                throw new Exception("Gagal mereset password!");
            }
            
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get user for editing
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("
        SELECT u.*, p.full_name, p.phone, p.address
        FROM users u 
        LEFT JOIN user_profiles p ON u.id = p.user_id 
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_user = $stmt->get_result()->fetch_assoc();
    
    // Check permission to edit GOD users
    if ($edit_user && $edit_user['role'] === 'GOD' && !$isGod) {
        $error_message = "Hanya user GOD yang dapat mengedit akun GOD!";
        $edit_user = null;
    }
}

// Get all users with profile info
$users = $conn->query("
    SELECT u.*, p.full_name, p.phone, p.address
    FROM users u 
    LEFT JOIN user_profiles p ON u.id = p.user_id 
    ORDER BY u.role = 'GOD' DESC, u.role, u.name
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - GenZMart Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
    .password-input-group {
        position: relative;
        display: flex;
        align-items: center;
    }

    .password-input-group input {
        flex: 1;
        padding-right: 35px;
    }

    .toggle-password {
        position: absolute;
        right: 10px;
        cursor: pointer;
        color: #666;
        padding: 5px;
    }

    .toggle-password:hover {
        color: #333;
    }

    .form-text {
        color: #666;
        font-size: 0.85em;
        margin-top: 5px;
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.9em;
        font-weight: 500;
    }

    .status-badge.active {
        background-color: #48A277;
        color: white;
    }

    .status-badge.banned {
        background-color: #ff4444;
        color: white;
    }

    .actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-small {
        padding: 4px 8px;
        font-size: 0.9em;
        border-radius: 4px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .btn-danger {
        background-color: #ff4444;
        color: white;
    }

    .btn-warning {
        background-color: #ffc107;
        color: #000;
    }
    </style>
</head>

<body>
    <div id="particles-js"></div>

    <div class="admin-container">
        <!-- Admin Info -->
        <div class="admin-info">
            <div class="dropdown">
                <button class="info-button">
                    <i class="fas fa-user"></i>
                    <?php echo htmlspecialchars($adminName); ?>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="dropdown-content">
                    <div>
                        <i class="fas fa-id-badge"></i>
                        Role: <?php 
                            $roleDisplay = str_replace('admin', 'Admin', $userRole);
                            $roleDisplay = str_replace('GOD', 'CODER👑', $roleDisplay);
                            echo htmlspecialchars($roleDisplay); 
                        ?>
                    </div>
                    <div onclick="window.location.href='../menu.php'">
                        <i class="fas fa-store"></i> Back to Menu
                    </div>
                    <div onclick="window.location.href='../index.php'">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <img src="../img/logo-genzmart.png" alt="GenZMart Logo">
            </div>
            <nav>
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="categories.php">Categories</a></li>
                    <li><a href="items.php">Items</a></li>
                    <li><a href="users.php" class="active">Users</a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <h1>Manage Users</h1>

            <?php if ($error_message): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if ($success_message): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <div class="stat-content">
                        <h3>Total Users</h3>
                        <p class="stat-number"><?php 
                            $total = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc();
                            echo $total['total'];?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-shield"></i>
                    <div class="stat-content">
                        <h3>Admin Users</h3>
                        <p class="stat-number"><?php 
                                    $admins = $conn->query("SELECT COUNT(*) as total FROM users WHERE role IN ('admin', 'GOD')")->fetch_assoc();
                                    echo $admins['total'];
                                ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-check"></i>
                    <div class="stat-content">
                        <h3>Active Users</h3>
                        <p class="stat-number"><?php 
                                    $active = $conn->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'")->fetch_assoc();
                                    echo $active['total'];
                                ?></p>
                    </div>
                </div>
                <div class="stat-card warning">
                    <i class="fas fa-user-slash"></i>
                    <div class="stat-content">
                        <h3>Banned Users</h3>
                        <p class="stat-number"><?php 
                                    $banned = $conn->query("SELECT COUNT(*) as total FROM users WHERE status = 'banned'")->fetch_assoc();
                                    echo $banned['total'];
                                ?></p>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <div class="form-section">
                <h2><?php echo $edit_user ? 'Edit User' : 'Add New User'; ?></h2>
                <form method="POST" class="admin-form">
                    <input type="hidden" name="action" value="<?php echo $edit_user ? 'edit' : 'add'; ?>">
                    <?php if ($edit_user): ?>
                    <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Username:</label>
                        <input type="text" name="name" required
                            value="<?php echo $edit_user ? htmlspecialchars($edit_user['name']) : ''; ?>"
                            class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" required
                            value="<?php echo $edit_user ? htmlspecialchars($edit_user['email']) : ''; ?>"
                            class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Full Name:</label>
                        <input type="text" name="full_name"
                            value="<?php echo $edit_user ? htmlspecialchars($edit_user['full_name'] ?? '') : ''; ?>"
                            class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Phone:</label>
                        <input type="tel" name="phone"
                            value="<?php echo $edit_user ? htmlspecialchars($edit_user['phone'] ?? '') : ''; ?>"
                            class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Address:</label>
                        <textarea name="address"
                            class="form-control"><?php echo $edit_user ? htmlspecialchars($edit_user['address'] ?? '') : ''; ?></textarea>
                    </div>

                    <?php if (!$edit_user): ?>
                    <div class="form-group">
                        <label>Password:</label>
                        <div class="password-input-group">
                            <input type="password" name="password" id="password" class="form-control"
                                placeholder="Leave empty for default password" autocomplete="new-password">
                            <i class="fas fa-eye-slash toggle-password" onclick="togglePasswordVisibility()"></i>
                        </div>
                        <small class="form-text text-muted">Default password if left empty: GenZmart#1</small>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Role:</label>
                        <select name="role" required class="form-control">
                            <option value="customer"
                                <?php echo ($edit_user && $edit_user['role'] === 'customer') ? 'selected' : ''; ?>>
                                Customer</option>
                            <option value="admin"
                                <?php echo ($edit_user && $edit_user['role'] === 'admin') ? 'selected' : ''; ?>>Admin
                            </option>
                            <?php if ($isGod): ?>
                            <option value="GOD"
                                <?php echo ($edit_user && $edit_user['role'] === 'GOD') ? 'selected' : ''; ?>>GOD
                            </option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status:</label>
                        <select name="status" required class="form-control">
                            <option value="active"
                                <?php echo ($edit_user && $edit_user['status'] === 'active') ? 'selected' : ''; ?>>
                                Active</option>
                            <option value="banned"
                                <?php echo ($edit_user && $edit_user['status'] === 'banned') ? 'selected' : ''; ?>>
                                Banned</option>
                        </select>
                    </div>

                    <div class="form-buttons">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_user ? 'Update User' : 'Add User'; ?>
                        </button>
                        <?php if ($edit_user): ?>
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="table-section">
                <h2>Users List</h2>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Full Name</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($user['address'] ?? '-'); ?></td>
                                <td>
                                    <span class="role-badge <?php echo strtolower($user['role']); ?>">
                                        <?php 
                                            $roleDisplay = $user['role'];
                                            echo ($roleDisplay === 'GOD') ? 'Coder👑' : htmlspecialchars($roleDisplay);
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $user['status']; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <?php if ($isGod || ($isAdmin && $user['role'] !== 'GOD')): ?>
                                    <a href="?edit=<?php echo $user['id']; ?>" class="btn-small">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <?php endif; ?>

                                    <?php if ($isGod && $user['role'] !== 'GOD'): ?>
                                    <a href="?reset=<?php echo $user['id']; ?>" class="btn-small btn-warning"
                                        onclick="return confirm('mengatur ulang kata sandi ke (GenZmart#1)?')">
                                        <i class="fas fa-key"></i> Reset
                                    </a>

                                    <a href="?delete=<?php echo $user['id']; ?>" class="btn-small btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted">Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
    // Toggle Password Visibility
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.querySelector('.toggle-password');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        }
    }

    // Initialize Particles.js
    particlesJS('particles-js', {
        particles: {
            number: {
                value: 80
            },
            color: {
                value: "#ffffff"
            },
            shape: {
                type: "circle"
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
                color: "#ffffff",
                opacity: 0.4,
                width: 1
            },
            move: {
                enable: true,
                speed: 6,
                direction: "none",
                random: false,
                straight: false,
                out_mode: "out",
                bounce: false
            }
        },
        interactivity: {
            detect_on: "canvas",
            events: {
                onhover: {
                    enable: true,
                    mode: "repulse"
                },
                onclick: {
                    enable: true,
                    mode: "push"
                },
                resize: true
            }
        },
        retina_detect: true
    });
    </script>

    <style>
    .role-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.9em;
        font-weight: 500;
    }

    .role-badge.god {
        background-color: #FFD700;
        color: #000;
    }

    .role-badge.admin {
        background-color: #4169E1;
        color: white;
    }

    .role-badge.customer {
        background-color: #2E8B57;
        color: white;
    }

    .table-responsive {
        overflow-x: auto;
        margin-top: 20px;
    }

    .form-buttons {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }

    .btn {
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        text-decoration: none;
        border: none;
    }

    .btn-primary {
        background-color: #48A277;
        color: white;
    }

    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }

    .form-control {
        width: 100%;
        padding: 8px 12px;
        border-radius: 4px;
        border: 1px solid #ddd;
        margin-top: 5px;
    }

    .text-muted {
        color: #6c757d;
        font-style: italic;
    }
    </style>
</body>

</html>