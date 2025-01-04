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
    return $string ?: 'item-' . time();
}

// Handle upload gambar
function handleImageUpload($file) {
    $target_dir = "../uploads/items/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'Hanya file JPG, JPEG, PNG & GIF yang diperbolehkan.'];
    }
    
    if ($file['size'] > 5000000) {
        return ['success' => false, 'message' => 'File terlalu besar. Maksimal 5MB.'];
    }
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return ['success' => true, 'filename' => 'uploads/items/' . $new_filename];
    }
    
    return ['success' => false, 'message' => 'Gagal upload file.'];
}
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Query untuk menghapus item
    $delete_query = $conn->prepare("DELETE FROM items WHERE id = ?");
    $delete_query->bind_param("i", $delete_id);
    
    if ($delete_query->execute()) {
        $success_message = "Menu berhasil dihapus!";
    } else {
        $error_message = "Gagal menghapus menu: " . $delete_query->error;
    }
    
    $delete_query->close();
}
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $name = trim($_POST['name']);
        $category_id = (int)$_POST['category_id'];
        $description = trim($_POST['description']);
        $price = (float)$_POST['price'];
        $stock = (int)$_POST['stock'];
        $status = $_POST['status']; // Menggunakan status dari form
        
        // Create slug from name
        $slug = createSlug($name);

        if (empty($name)) {
            $error_message = "Nama item tidak boleh kosong!";
        } else {
            if ($_POST['action'] === 'add') {
                // Handle upload gambar untuk item baru
                $image_url = '';
                if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                    $upload_result = handleImageUpload($_FILES['image']);
                    if ($upload_result['success']) {
                        $image_url = $upload_result['filename'];
                    } else {
                        $error_message = $upload_result['message'];
                    }
                }

                if (empty($error_message)) {
                    // Cek duplikasi nama/slug
                    $check = $conn->prepare("SELECT id FROM items WHERE name = ? OR slug = ?");
                    $check->bind_param("ss", $name, $slug);
                    $check->execute();
                    $result = $check->get_result();
                    
                    if ($result->num_rows > 0) {
                        $error_message = "Item dengan nama tersebut sudah ada!";
                    } else {
                        $stmt = $conn->prepare("INSERT INTO items (category_id, name, slug, description, price, stock, image_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("issssdss", $category_id, $name, $slug, $description, $price, $stock, $image_url, $status);
                        
                        if ($stmt->execute()) {
                            $success_message = "Item berhasil ditambahkan!";
                        } else {
                            $error_message = "Error: " . $stmt->error;
                        }
                    }
                    $check->close();
                }
            } 
            elseif ($_POST['action'] === 'edit') {
                $id = (int)$_POST['item_id'];
                $current_image = $_POST['current_image'];
                
                // Handle upload gambar jika ada gambar baru
                $image_url = $current_image;
                if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                    $upload_result = handleImageUpload($_FILES['image']);
                    if ($upload_result['success']) {
                        $image_url = $upload_result['filename'];
                        // Hapus gambar lama
                        if (!empty($current_image) && file_exists("../$current_image")) {
                            unlink("../$current_image");
                        }
                    } else {
                        $error_message = $upload_result['message'];
                    }
                }

                if (empty($error_message)) {
                    $stmt = $conn->prepare("UPDATE items SET category_id=?, name=?, slug=?, description=?, price=?, stock=?, image_url=?, status=? WHERE id=?");
                    $stmt->bind_param("issssdssi", $category_id, $name, $slug, $description, $price, $stock, $image_url, $status, $id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Item berhasil diupdate!";
                    } else {
                        $error_message = "Error: " . $stmt->error;
                    }
                }
            }
        }
    }
}

// Get categories untuk dropdown
$categories = $conn->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC");

// Get items dengan nama kategori
$items = $conn->query("
    SELECT i.*, c.name as category_name 
    FROM items i 
    LEFT JOIN categories c ON i.category_id = c.id 
    ORDER BY c.name ASC, i.name ASC
");
?>

<!-- HTML template tetap sama sampai form -->



<!-- Sisanya HTML template tetap sama -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Menu - GenZMart Admin</title>
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
                        <i class="fas fa-store"></i> Logout ke Menu
                    </div>
                    <div onclick="window.location.href='logout.php?redirect=login'">
                        <i class="fas fa-sign-out-alt"></i> back ke Login
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
            <h1>Kelola Menu</h1>

            <?php if ($error_message): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if ($success_message): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <!-- Form Tambah/Edit Item -->
            <div class="form-section">
                <h2><?php echo isset($_GET['edit']) ? 'Edit Menu' : 'Tambah Menu Baru'; ?></h2>
                <form method="POST" enctype="multipart/form-data" class="admin-form">
                    <input type="hidden" name="action" value="<?php echo isset($_GET['edit']) ? 'edit' : 'add'; ?>">
                    <?php
                    if (isset($_GET['edit'])) {
                        $edit_id = (int)$_GET['edit'];
                        $item = $conn->query("SELECT * FROM items WHERE id = $edit_id")->fetch_assoc();
                        if ($item) {
                            echo '<input type="hidden" name="item_id" value="' . $edit_id . '">';
                            echo '<input type="hidden" name="current_image" value="' . htmlspecialchars($item['image_url']) . '">';
                        }
                    }
                    ?>

                    <div class="form-group">
                        <label>Kategori:</label>
                        <select name="category_id" required>
                            <?php while ($category = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $category['id']; ?>"
                                <?php echo (isset($item) && $item['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Nama Menu:</label>
                        <input type="text" name="name" required
                            value="<?php echo isset($item) ? htmlspecialchars($item['name']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Deskripsi:</label>
                        <textarea name="description"
                            required><?php echo isset($item) ? htmlspecialchars($item['description']) : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Harga (Rp):</label>
                        <input type="number" name="price" step="100" required
                            value="<?php echo isset($item) ? $item['price'] : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Stok:</label>
                        <input type="number" name="stock" required
                            value="<?php echo isset($item) ? $item['stock'] : '0'; ?>">
                        <small>Set 0 untuk menandai item habis</small>
                    </div>

                    <div class="form-group">
                        <label>Gambar Menu:</label>
                        <input type="file" name="image" accept="image/*" <?php echo isset($item) ? '' : 'required'; ?>>
                        <?php if (isset($item) && !empty($item['image_url'])): ?>
                        <div class="current-image">
                            <img src="../<?php echo $item['image_url']; ?>" alt="Current Image"
                                style="max-width: 200px;">
                            <small>Biarkan kosong jika tidak ingin mengubah gambar</small>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Status:</label>
                        <select name="status" required>
                            <option value="active"
                                <?php echo (isset($item) && $item['status'] == 'active') ? 'selected' : ''; ?>>Aktif
                            </option>
                            <option value="inactive"
                                <?php echo (isset($item) && $item['status'] == 'inactive') ? 'selected' : ''; ?>>
                                Non-aktif</option>
                            <option value="out_of_stock"
                                <?php echo (isset($item) && $item['status'] == 'out_of_stock') ? 'selected' : ''; ?>>
                                Habis</option>
                            <option value="to_be_replaced"
                                <?php echo (isset($item) && $item['status'] == 'to_be_replaced') ? 'selected' : ''; ?>>
                                Akan Diganti</option>
                        </select>
                    </div>
                    <button type="submit" class="btn">
                        <?php echo isset($_GET['edit']) ? 'Update Menu' : 'Tambah Menu'; ?>
                    </button>
                </form>
            </div>

            <!-- Daftar Items -->
            <div class="table-section">
                <h2>Daftar Menu</h2>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Gambar</th>
                            <th>Nama</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $items->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php if (!empty($item['image_url'])): ?>
                                <img src="../<?php echo $item['image_url']; ?>"
                                    alt="<?php echo htmlspecialchars($item['name']); ?>"
                                    style="max-width: 50px; max-height: 50px;">
                                <?php else: ?>
                                No Image
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                            <td>Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></td>
                            <td>
                                <?php if ($item['stock'] <= 0): ?>
                                <span class="stock-warning">Habis</span>
                                <?php else: ?>
                                <?php echo $item['stock']; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $item['status']; ?>">
                                    <?php 
                                        switch ($item['status']) {
                                            case 'active':
                                                echo 'Aktif';
                                                break;
                                            case 'out_of_stock':
                                                echo 'Habis';
                                                break;
                                            case 'inactive':
                                                echo 'Non-aktif';
                                                break;
                                            case 'to_be_replaced':
                                                echo 'Akan Diganti';
                                                break;
                                        }
                                        ?>
                                </span>
                            </td>
                            <td>
                                <a href="?edit=<?php echo $item['id']; ?>" class="btn-small">Edit</a>
                                <a href="?delete=<?php echo $item['id']; ?>" class="btn-small btn-danger"
                                    onclick="return confirm('Yakin ingin menghapus menu ini?')">Hapus</a>
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