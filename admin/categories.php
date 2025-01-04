<?php
session_start();
require_once '../config.php';

// Cek akses admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../index.php');
    exit();
}

$userRole = $_SESSION['role'];
if ($userRole !== 'admin,GOD' && $userRole !== 'admin' && $userRole !== 'GOD') {
    header('Location: ../index.php');
    exit();
}

$adminName = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
$error_message = '';
$success_message = '';

// Fungsi untuk membuat slug
function createSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9\-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    $string = trim($string, '-');
    return $string;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $status = $_POST['status'];
        
        // Buat slug dari nama kategori
        $slug = createSlug($name);
        
        if (empty($name)) {
            $error_message = "Nama kategori tidak boleh kosong!";
        } else {
            if ($_POST['action'] === 'add') {
                // Cek apakah kategori sudah ada
                $check = $conn->prepare("SELECT id FROM categories WHERE name = ? OR slug = ?");
                $check->bind_param("ss", $name, $slug);
                $check->execute();
                $result = $check->get_result();
                
                if ($result->num_rows > 0) {
                    $error_message = "Kategori dengan nama tersebut sudah ada!";
                } else {
                    $stmt = $conn->prepare("INSERT INTO categories (name, slug, description, status) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $name, $slug, $description, $status);
                    
                    if ($stmt->execute()) {
                        $success_message = "Kategori berhasil ditambahkan!";
                    } else {
                        $error_message = "Error: " . $stmt->error;
                    }
                }
                $check->close();
            } 
            elseif ($_POST['action'] === 'edit') {
                $id = (int)$_POST['category_id'];
                
                // Update kategori
                $stmt = $conn->prepare("UPDATE categories SET description=?, status=? WHERE id=?");
                $stmt->bind_param("ssi", $description, $status, $id);
                
                if ($stmt->execute()) {
                    // Jika status diubah menjadi inactive, update semua items dalam kategori
                    if ($status === 'inactive') {
                        $update_items = $conn->prepare("UPDATE items SET status='inactive' WHERE category_id=?");
                        $update_items->bind_param("i", $id);
                        $update_items->execute();
                        $update_items->close();
                    }
                    
                    $success_message = "Kategori berhasil diperbarui!";
                } else {
                    $error_message = "Error: " . $stmt->error;
                }
            }
            
        }
    }
}

// Get categories dengan informasi items
$categories = $conn->query("
    SELECT c.*, 
           COUNT(i.id) as total_items,
           SUM(CASE WHEN i.stock = 0 THEN 1 ELSE 0 END) as out_of_stock_items
    FROM categories c
    LEFT JOIN items i ON c.id = i.category_id
    GROUP BY c.id
    ORDER BY c.name ASC
");

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - GenZMart Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
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
                            $roleDisplay = str_replace(',admin', ' (Admin)', $userRole);
                            $roleDisplay = str_replace('admin', 'Admin', $roleDisplay);
                            $roleDisplay = str_replace(',GOD', ' (CODER)', $userRole);
                            $roleDisplay = str_replace('GOD', 'CODER👑', $roleDisplay);
                            echo htmlspecialchars($roleDisplay); 
                        ?>
                    </div>
                    <div onclick="window.location.href='logout.php?redirect=menu'">
                        <i class="fas fa-store"></i> back ke Menu
                    </div>
                    <div onclick="window.location.href='logout.php?redirect=login'">
                        <i class="fas fa-sign-out-alt"></i> Logout ke Login
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
                    <li><a href="dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="categories.php">Categories</a></li>
                    <li><a href="items.php">Items</a></li>
                    <li><a href="users.php">Users</a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <h1>Kelola Kategori</h1>

            <?php if ($error_message): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if ($success_message): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <!-- Form Tambah/Edit Kategori -->
            <div class="form-section">
                <h2><?php echo isset($_GET['edit']) ? 'Edit Kategori' : 'Tambah Kategori Baru'; ?></h2>
                <form method="POST" class="admin-form">
                    <input type="hidden" name="action" value="<?php echo isset($_GET['edit']) ? 'edit' : 'add'; ?>">
                    <?php
                    if (isset($_GET['edit'])) {
                        $edit_id = (int)$_GET['edit'];
                        $category = $conn->query("SELECT * FROM categories WHERE id = $edit_id")->fetch_assoc();
                        if ($category) {
                            echo '<input type="hidden" name="category_id" value="' . $edit_id . '">';
                        }
                    }
                    ?>

                    <div class="form-group">
                        <label>Nama Kategori:</label>
                        <input type="text" name="name" required
                            value="<?php echo isset($category) ? htmlspecialchars($category['name']) : ''; ?>"
                            <?php echo isset($_GET['edit']) ? 'readonly' : ''; ?>>
                    </div>

                    <div class="form-group">
                        <label>Deskripsi:</label>
                        <textarea name="description"
                            required><?php echo isset($category) ? htmlspecialchars($category['description']) : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Status:</label>
                        <select name="status">
                            <option value="active"
                                <?php echo (isset($category) && $category['status'] == 'active') ? 'selected' : ''; ?>>
                                Aktif
                            </option>
                            <option value="inactive"
                                <?php echo (isset($category) && $category['status'] == 'inactive') ? 'selected' : ''; ?>>
                                Non-aktif
                            </option>
                        </select>
                        <small>Menonaktifkan kategori akan menonaktifkan semua item di dalamnya</small>
                    </div>

                    <button type="submit" class="btn">
                        <?php echo isset($_GET['edit']) ? 'Update Kategori' : 'Tambah Kategori'; ?>
                    </button>
                </form>
            </div>

            <!-- Daftar Kategori -->
            <div class="table-section">
                <h2>Daftar Kategori</h2>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Nama Kategori</th>
                            <th>Deskripsi</th>
                            <th>Status</th>
                            <th>Total Items</th>
                            <th>Items Habis</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($category = $categories->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                            <td><?php echo htmlspecialchars($category['description']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $category['status']; ?>">
                                    <?php echo $category['status'] === 'active' ? 'Aktif' : 'Non-aktif'; ?>
                                </span>
                            </td>
                            <td><?php echo $category['total_items']; ?></td>
                            <td>
                                <?php if ($category['out_of_stock_items'] > 0): ?>
                                <span class="warning-text"><?php echo $category['out_of_stock_items']; ?></span>
                                <?php else: ?>
                                0
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?edit=<?php echo $category['id']; ?>" class="btn-small">Edit</a>
                                <a href="items.php?delete=<?php echo $item['id']; ?>" class="btn-small btn-danger"
                                    onclick="return confirm('Yakin ingin menghapus menu ini?')">
                                    Hapus
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
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
                    bounce: false,
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
    });
    </script>
</body>

</html>