<?php
/**
 * CashClaim - Petty Cash & Reimbursement Simple Management System
 * 
 * @author    Bagus Setya
 * @github    https://github.com/bgustyp
 * @license   MIT License
 */
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require 'db.php';

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . url('index'));
    exit;
}

// Handle Login
$loginError = '';
if (isset($_POST['login_user'])) {
    // Validate CSRF token
    if (!validateCSRFToken()) {
        $loginError = '<div class="alert alert-danger">Invalid security token. Please try again.</div>';
    } else {
        $username = sanitizeString($_POST['login_user']);
        $accessCode = $_POST['access_code'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE name = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Use password verification with hashing
        if ($user && verifyPassword($accessCode, $user['access_code'])) {
            $_SESSION['user'] = $user['name'];
            header("Location: " . url('dashboard'));
            exit;
        } else {
            $loginError = '<div class="alert alert-danger">Kode Akses Salah!</div>';
        }
    }
}

// Handle Add User
$message = '';
if (isset($_POST['add_user'])) {
    // Validate CSRF token
    if (!validateCSRFToken()) {
        $message = '<div class="alert alert-danger">Invalid security token. Please try again.</div>';
    } else {
        $name = sanitizeString($_POST['new_user_name']);
        $code = $_POST['new_user_code'];
        
        if (!empty($name) && !empty($code)) {
            try {
                $pdo->beginTransaction();
                
                // Hash the password before storing
                $hashedCode = hashPassword($code);
                $stmt = $pdo->prepare("INSERT INTO users (name, access_code) VALUES (?, ?)");
                $stmt->execute([$name, $hashedCode]);
                
                // Auto-create Main project for new user
                $stmtProject = $pdo->prepare("INSERT INTO projects (user_id, name, description) VALUES (?, 'Main', 'Default Project')");
                $stmtProject->execute([$name]);
                
                $pdo->commit();
                $message = '<div class="alert alert-success">User berhasil ditambahkan! Silakan login.</div>';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = '<div class="alert alert-danger">Gagal: Nama user mungkin sudah ada.</div>';
            }
        } else {
            $message = '<div class="alert alert-warning">Nama dan Kode Akses wajib diisi.</div>';
        }
    }
}

// Fetch Users
$users = $pdo->query("SELECT * FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// If already logged in, redirect
if (isset($_SESSION['user'])) {
    header("Location: " . url('dashboard'));
    exit;
}

require 'header.php';
?>

<div class="container pb-5 mb-5">
    <div class="row justify-content-center mt-5">
        <div class="col-md-6 col-lg-5">
            <div class="text-center mb-5">
                <h1 class="fw-bold mb-2">Halo Guys</h1>
                <p class="text-muted">Pilih user-nya, terus isi kode akses</p>
            </div>

            <?= $message ?>
            <?= $loginError ?>

            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-3">
                        <?php foreach ($users as $u): ?>
                            <button type="button" 
                                    class="btn btn-outline-primary text-start p-3 d-flex justify-content-between align-items-center"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#loginModal"
                                    data-username="<?= htmlspecialchars($u['name']) ?>">
                                <span class="fw-medium"><?= htmlspecialchars($u['name']) ?></span>
                                <i class="bi bi-lock"></i>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <?php if (empty($users)): ?>
                        <div class="text-center text-muted py-3">Belum ada user. Silakan buat baru.</div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-light border-0 p-3">
                    <button class="btn btn-link text-decoration-none w-100 text-muted mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#addUserForm">
                        + Tambah User Baru
                    </button>
                    <div class="collapse mb-3" id="addUserForm">
                        <form method="POST" class="d-grid gap-2">
                            <?php csrfField(); ?>
                            <input type="text" name="new_user_name" class="form-control" placeholder="Nama User" required>
                            <input type="password" name="new_user_code" class="form-control" placeholder="Kode Akses (PIN)" required>
                            <button type="submit" name="add_user" value="1" class="btn btn-primary">Simpan User</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Masukkan Kode Akses</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php csrfField(); ?>
                        <input type="hidden" name="login_user" id="modalUsername">
                        <div class="mb-3">
                            <label class="form-label">User: <span id="displayUsername" class="fw-bold"></span></label>
                        </div>
                        <div class="mb-3">
                            <input type="password" name="access_code" class="form-control form-control-lg text-center" placeholder="****" required autofocus>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary w-100">Masuk</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div> <!-- End Container -->
<?php require 'footer.php'; ?>
