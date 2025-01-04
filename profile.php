<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect jika belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Inisialisasi variabel
$userData = [];
$success_message = '';
$error_message = '';

// Cek koneksi database
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Fungsi untuk sanitasi input
function sanitizeInput($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = $conn->real_escape_string($data);
    return $data;
}

// Fungsi untuk mendapatkan data user
function getUserData($userId) {
    global $conn;
    
    try {
        $sql = "SELECT 
                u.id,
                u.username,
                u.email,
                u.role,
                up.full_name,
                up.phone,
                up.address,
                up.profile_image,
                up.points
                FROM users u
                LEFT JOIN user_profiles up ON u.id = up.user_id
                WHERE u.id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        
        if (!$stmt->execute()) {
            throw new Exception("Error executing query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            // Update session dengan data terbaru
            $_SESSION['email'] = $row['email'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['full_name'] = $row['full_name'] ?? $row['username'];
            
            // Hanya update session profile_image jika ada perubahan
            if ($row['profile_image'] && (!isset($_SESSION['profile_image']) || $_SESSION['profile_image'] !== $row['profile_image'])) {
                $_SESSION['profile_image'] = $row['profile_image'];
            }
            
            return [
                'id' => $row['id'],
                'username' => $row['username'],
                'email' => $row['email'],
                'role' => $row['role'],
                'full_name' => $row['full_name'] ?? $row['username'],
                'phone' => $row['phone'] ?? '',
                'address' => $row['address'] ?? '',
                'profile_image' => $row['profile_image'] ?? 'default.jpg',
                'points' => (int)$row['points']
            ];
        }
        return null;
    } catch (Exception $e) {
        error_log("Error getting user data: " . $e->getMessage());
        return null;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    
    // Mulai transaksi
    $conn->begin_transaction();
    
    try {
        // Handle profile update
        if (isset($_POST['full_name'])) {
            $fullName = sanitizeInput($_POST['full_name']);
            $email = sanitizeInput($_POST['email']);
            $phone = sanitizeInput($_POST['phone']);
            $address = sanitizeInput($_POST['address']);
            
            // Validasi email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Format email tidak valid");
            }
            
            // Validasi nomor telepon
            if ($phone && !preg_match('/^[0-9]{10,13}$/', $phone)) {
                throw new Exception("Nomor telepon harus 10-13 digit");
            }
            
            // Update email di tabel users
            $stmtUsers = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmtUsers->bind_param("si", $email, $userId);
            if (!$stmtUsers->execute()) {
                throw new Exception("Gagal mengupdate email");
            }

            // Check if user_profile exists and get current profile image
            $checkStmt = $conn->prepare("SELECT user_id, profile_image FROM user_profiles WHERE user_id = ?");
            $checkStmt->bind_param("i", $userId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $existingProfile = $result->fetch_assoc();

            if ($existingProfile) {
                // Update existing profile while preserving profile_image
                $updateStmt = $conn->prepare("
                    UPDATE user_profiles 
                    SET full_name = ?, 
                        phone = ?, 
                        address = ?
                    WHERE user_id = ?
                ");
                $updateStmt->bind_param("sssi", $fullName, $phone, $address, $userId);
                if (!$updateStmt->execute()) {
                    throw new Exception("Gagal mengupdate profil");
                }
            } else {
                // Insert new profile
                $insertStmt = $conn->prepare("
                    INSERT INTO user_profiles (user_id, full_name, phone, address) 
                    VALUES (?, ?, ?, ?)
                ");
                $insertStmt->bind_param("isss", $userId, $fullName, $phone, $address);
                if (!$insertStmt->execute()) {
                    throw new Exception("Gagal membuat profil baru");
                }
            }

            $success_message = "Profil berhasil diperbarui";
        }

        // Handle password update
        if (!empty($_POST['current_password']) && !empty($_POST['new_password'])) {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            if ($newPassword !== $confirmPassword) {
                throw new Exception("Password baru dan konfirmasi tidak cocok");
            }
            
            if (strlen(string: $newPassword) < 8) {  
                throw new Exception("Password harus terdiri dari minimal 8 karakter."); 
            } elseif (!preg_match('/[A-Z]/', $newPassword)) {  
                throw new Exception("Password harus mengandung setidaknya satu huruf besar.");   
            } elseif (!preg_match('/[a-z]/', $newPassword)) {  
                throw new Exception("Password harus mengandung setidaknya satu huruf kecil."); 
            } elseif (!preg_match('/[0-9]/', $newPassword)) {  
                throw new Exception("Password harus mengandung setidaknya satu angka.");  
            } elseif (!preg_match('/[\W_]/', $newPassword)) {  
                throw new Exception("Password harus mengandung setidaknya satu simbol."); 
            }
          
            
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if (!password_verify($currentPassword, $user['password'])) {
                throw new Exception("Password saat ini tidak valid");
            }
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashedPassword, $userId);
            
            if ($stmt->execute()) {
                $success_message = "Password berhasil diperbarui";
            } else {
                throw new Exception("Gagal memperbarui password");
            }
        }
        
        // Handle profile image upload via AJAX
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/profiles/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileInfo = pathinfo($_FILES['profile_image']['name']);
            $extension = strtolower($fileInfo['extension']);
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($extension, $allowedTypes)) {
                throw new Exception("Format file tidak didukung. Gunakan JPG, PNG, atau GIF.");
            }
            
            if ($_FILES['profile_image']['size'] > 5 * 1024 * 1024) {
                throw new Exception("Ukuran file tidak boleh lebih dari 5MB");
            }
            
            $newFilename = uniqid() . '.' . $extension;
            $uploadPath = $uploadDir . $newFilename;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath)) {
                // Get existing profile image
                $stmt = $conn->prepare("SELECT profile_image FROM user_profiles WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $oldImage = $result->fetch_assoc();
                
                // Update atau insert foto profil
                if ($result->num_rows > 0) {
                    $stmt = $conn->prepare("UPDATE user_profiles SET profile_image = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $newFilename, $userId);
                } else {
                    $stmt = $conn->prepare("INSERT INTO user_profiles (user_id, profile_image) VALUES (?, ?)");
                    $stmt->bind_param("is", $userId, $newFilename);
                }
                
                if ($stmt->execute()) {
                    // Hapus foto lama jika ada dan berbeda dari default
                    if (!empty($oldImage['profile_image']) && $oldImage['profile_image'] !== 'default.jpg') {
                        $oldFile = $uploadDir . $oldImage['profile_image'];
                        if (file_exists($oldFile)) {
                            unlink($oldFile);
                        }
                    }
                    
                    $_SESSION['profile_image'] = $newFilename;
                    
                    // Return success response untuk AJAX
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Foto profil berhasil diperbarui',
                            'image_url' => $uploadDir . $newFilename
                        ]);
                        exit;
                    }
                    
                    $success_message = "Foto profil berhasil diperbarui";
                } else {
                    // Delete uploaded file if database update fails
                    unlink($uploadPath);
                    throw new Exception("Gagal memperbarui foto profil dalam database");
                }
            } else {
                throw new Exception("Gagal mengupload file");
            }
        }

        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
        
        // Return error response untuk AJAX
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $error_message]);
            exit;
        }
    }
}

// Ambil data user setelah semua operasi
$userId = $_SESSION['user_id'];
$userData = getUserData($userId);

if (!$userData) {
    $userData = [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? 'user',
        'full_name' => $_SESSION['username'],
        'phone' => '',
        'address' => '',
        'profile_image' => isset($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'default.jpg',
        'points' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - GenZMart</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/pro.css">
</head>

<body>
    <div class="profile-container">
        <h1><i class="fas fa-user-edit"></i> Edit Profile</h1>

        <div class="profile-content">
            <!-- Bagian foto profil -->
            <div class="profile-left">
                <div class="profile-photo" id="profilePhotoContainer">
                    <?php if (!empty($userData['profile_image'])): ?>
                    <img src="uploads/profiles/<?php echo htmlspecialchars($userData['profile_image']); ?>?t=<?php echo time(); ?>"
                        alt="Profile Photo" id="currentProfile" data-profile-image="true">
                    <?php else: ?>
                    <i class="fas fa-user-circle" id="defaultProfileIcon"></i>
                    <?php endif; ?>

                    <div class="profile-photo-actions">
                        <label for="profileInput" class="upload-btn">
                            <i class="fas fa-camera"></i> Ganti Foto
                        </label>
                        <input type="file" name="profile_image" id="profileInput"
                            accept="image/jpeg,image/png,image/gif" style="display: none;">
                    </div>
                </div>

                <?php if (isset($userData['points'])): ?>
                <div class="points-info">
                    <i class="fas fa-star"></i>
                    <span>Points: <?php echo number_format($userData['points']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Form profil -->
            <div class="profile-right">
                <form method="POST" id="profileForm" class="profile-form">
                    <div class="form-group">
                        <input type="text" id="full_name" name="full_name"
                            value="<?php echo htmlspecialchars($userData['full_name'] ?? ''); ?>" placeholder=" "
                            required>
                        <label for="full_name">
                            <i class="fas fa-user"></i> Nama Lengkap
                        </label>
                    </div>

                    <div class="form-group">
                        <input type="email" id="email" name="email"
                            value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" placeholder=" " required>
                        <label for="email">
                            <i class="fas fa-envelope"></i> Email
                        </label>
                    </div>

                    <div class="form-group">
                        <input type="tel" id="phone" name="phone"
                            value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>" placeholder=" "
                            pattern="[0-9]{10,13}">
                        <label for="phone">
                            <i class="fas fa-phone"></i> Nomor Telepon
                        </label>
                    </div>

                    <div class="form-group">
                        <textarea id="address" name="address"
                            placeholder=" "><?php echo htmlspecialchars($userData['address'] ?? ''); ?></textarea>
                        <label for="address">
                            <i class="fas fa-map-marker-alt"></i> Alamat
                        </label>
                    </div>

                    <!-- Bagian password -->
                    <div class="password-section">
                        <h3><i class="fas fa-lock"></i> Ubah Password</h3>

                        <div class="form-group">
                            <input type="password" id="current_password" name="current_password" placeholder=" ">
                            <label for="current_password">Password Saat Ini</label>
                            <i class="fas fa-eye-slash password-toggle"></i>
                        </div>

                        <div class="form-group">
                            <input type="password" id="new_password" name="new_password" placeholder=" ">
                            <label for="new_password">Password Baru</label>
                            <i class="fas fa-eye-slash password-toggle"></i>
                            <div class="password-strength">
                                <div class="strength-bar"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <input type="password" id="confirm_password" name="confirm_password" placeholder=" ">
                            <label for="confirm_password">Konfirmasi Password</label>
                            <i class="fas fa-eye-slash password-toggle"></i>
                        </div>
                    </div>

                    <!-- Tombol aksi -->
                    <div class="button-group">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                        <a href="menu.php" class="btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview Container -->
    <div id="previewContainer" class="preview-container">
        <div class="preview-content">
            <div class="preview-header">
                <h3>Preview Foto Profil</h3>
                <button type="button" class="close-preview" id="cancelPreview">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="preview-body">
                <img id="previewImage" alt="Preview" class="preview-image">
            </div>
            <div class="preview-footer">
                <button type="button" id="confirmImage" class="btn-confirm">
                    <i class="fas fa-check"></i> Konfirmasi
                </button>
                <button type="button" id="cancelImage" class="btn-cancel">
                    <i class="fas fa-times"></i> Batal
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const elements = {
            profileInput: document.getElementById('profileInput'),
            previewContainer: document.getElementById('previewContainer'),
            previewImage: document.getElementById('previewImage'),
            profilePhotoContainer: document.getElementById('profilePhotoContainer'),
            profileForm: document.getElementById('profileForm'),
            currentProfile: document.getElementById('currentProfile'),
            defaultProfileIcon: document.getElementById('defaultProfileIcon')
        };

        let selectedFile = null;

        // Handle file selection
        if (elements.profileInput) {
            elements.profileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;

                if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
                    showNotification('Format file harus JPG, PNG, atau GIF', 'error');
                    this.value = '';
                    return;
                }

                if (file.size > 5 * 1024 * 1024) {
                    showNotification('Ukuran file tidak boleh lebih dari 5MB', 'error');
                    this.value = '';
                    return;
                }

                selectedFile = file;
                const reader = new FileReader();
                reader.onload = function(e) {
                    elements.previewImage.src = e.target.result;
                    elements.previewContainer.style.display = 'flex';
                };
                reader.onerror = function() {
                    showNotification('Gagal membaca file', 'error');
                    selectedFile = null;
                    elements.profileInput.value = '';
                };
                reader.readAsDataURL(file);
            });
        }

        // Handle preview confirmation
        document.getElementById('confirmImage')?.addEventListener('click', async function() {
            if (!selectedFile) return;

            const formData = new FormData();
            formData.append('profile_image', selectedFile);

            try {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengupload...';

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Terjadi kesalahan saat upload');
                }

                // Update UI
                if (elements.defaultProfileIcon) {
                    elements.defaultProfileIcon.style.display = 'none';
                }

                const imageUrl =
                    `${result.image_url}?t=${new Date().getTime()}`; // Add cache buster

                if (elements.currentProfile) {
                    elements.currentProfile.src = imageUrl;
                    elements.currentProfile.style.display = 'block';
                } else {
                    const newImg = document.createElement('img');
                    newImg.id = 'currentProfile';
                    newImg.alt = 'Profile Photo';
                    newImg.src = imageUrl;
                    newImg.setAttribute('data-profile-image', 'true');
                    elements.profilePhotoContainer.insertBefore(
                        newImg,
                        elements.defaultProfileIcon || elements.profilePhotoContainer.firstChild
                    );
                }

                // Reset state
                elements.previewContainer.style.display = 'none';
                elements.profileInput.value = '';
                selectedFile = null;

                showNotification(result.message || 'Foto profil berhasil diperbarui', 'success');

            } catch (error) {
                console.error('Error:', error);
                showNotification(error.message || 'Gagal mengupload foto profil', 'error');
            } finally {
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-check"></i> Konfirmasi';
            }
        });

        // Handle form submission
        if (elements.profileForm) {
            elements.profileForm.addEventListener('submit', function(e) {
                if (selectedFile) {
                    e.preventDefault();
                    showNotification('Harap konfirmasi upload foto profil terlebih dahulu', 'error');
                    return false;
                }
            });
        }

        // Handle preview cancellation
        document.querySelectorAll('#cancelImage, #cancelPreview, .close-preview').forEach(button => {
            button.addEventListener('click', function() {
                elements.previewContainer.style.display = 'none';
                elements.profileInput.value = '';
                selectedFile = null;
            });
        });

        // Password toggle functionality
        document.querySelectorAll('.password-toggle').forEach(icon => {
            icon.addEventListener('click', function() {
                const input = this.previousElementSibling.previousElementSibling;
                const type = input.type === 'password' ? 'text' : 'password';
                input.type = type;
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        });

        // Password strength indicator
        const newPasswordInput = document.getElementById('new_password');
        const strengthBar = document.querySelector('.strength-bar');

        if (newPasswordInput && strengthBar) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;

                if (password.length >= 8) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/[a-z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^A-Za-z0-9]/.test(password)) strength++;

                const percentage = (strength / 5) * 100;
                strengthBar.style.width = `${percentage}%`;
                strengthBar.className = 'strength-bar';

                if (strength <= 2) {
                    strengthBar.classList.add('weak');
                } else if (strength <= 3) {
                    strengthBar.classList.add('medium');
                } else {
                    strengthBar.classList.add('strong');
                }
            });
        }

        // Function to show notifications
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;

            notification.style.position = 'fixed';
            notification.style.top = '1rem';
            notification.style.right = '1rem';
            notification.style.zIndex = '9999';

            document.body.appendChild(notification);

            notification.style.animation = 'slideInRight 0.4s cubic-bezier(0.2, 1, 0.3, 1)';

            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.4s cubic-bezier(0.2, 1, 0.3, 1)';
                setTimeout(() => notification.remove(), 400);
            }, 5000);
        }

        // Show initial notifications if any
        <?php if ($success_message): ?>
        showNotification(<?php echo json_encode($success_message); ?>, 'success');
        <?php endif; ?>

        <?php if ($error_message): ?>
        showNotification(<?php echo json_encode($error_message); ?>, 'error');
        <?php endif; ?>
    });
    </script>
</body>

</html>