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
require 'db.php';
// Admin-only access
if (!isset($_SESSION['user']) || $_SESSION['user'] !== 'Admin') {
    header("Location: " . url('dashboard'));
    exit;
}

// Handle Update Access Code
if (isset($_POST['update_code'])) {
    $userId = $_POST['user_id'];
    $newCode = trim($_POST['new_code']);
    
    if (!empty($newCode)) {
        $stmt = $pdo->prepare("UPDATE users SET access_code = ? WHERE id = ?");
        $stmt->execute([$newCode, $userId]);
        $message = '<div class="alert alert-success">Kode akses berhasil diubah.</div>';
    } else {
        $message = '<div class="alert alert-warning">Kode akses tidak boleh kosong.</div>';
    }
}

// Handle Delete User
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    // Prevent deleting Admin
    $check = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $check->execute([$id]);
    $u = $check->fetch(PDO::FETCH_ASSOC);
    
    if ($u && $u['name'] !== 'Admin') {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $message = '<div class="alert alert-success">User berhasil dihapus.</div>';
    } else {
        $message = '<div class="alert alert-danger">Tidak bisa menghapus Admin atau user tidak ditemukan.</div>';
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

require 'header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-8 col-lg-6">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold mb-0">Kelola User</h3>
            <a href="<?= url('index') ?>" class="btn btn-outline-secondary">Kembali</a>
        </div>

        <?= $message ?? '' ?>

        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Nama User</th>
                            <th>Kode Akses</th>
                            <th class="text-end pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="ps-4 fw-medium"><?= htmlspecialchars($u['name']) ?></td>
                                <td>
                                    <button type="button" 
                                            class="btn btn-sm btn-light border" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editCodeModal"
                                            data-user-id="<?= $u['id'] ?>"
                                            data-user-name="<?= htmlspecialchars($u['name']) ?>"
                                            data-current-code="<?= htmlspecialchars($u['access_code'] ?? '') ?>">
                                        <i class="bi bi-key"></i> <?= htmlspecialchars($u['access_code'] ?? '-') ?>
                                    </button>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($u['name'] !== 'Admin'): ?>
                                        <a href="<?= url('users') ?>?delete=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus user ini? History transaksi akan tetap ada tapi tanpa pemilik.')">
                                            Hapus
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Default</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Access Code Modal -->
<div class="modal fade" id="editCodeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ubah Kode Akses</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="modalUserId">
                    <div class="mb-3">
                        <label class="form-label">User: <span id="modalUserName" class="fw-bold"></span></label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kode Akses Saat Ini</label>
                        <input type="text" id="modalCurrentCode" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kode Akses Baru</label>
                        <input type="text" name="new_code" class="form-control form-control-lg text-center" placeholder="Masukkan kode baru" required autofocus>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_code" class="btn btn-primary w-100">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const editCodeModal = document.getElementById('editCodeModal');
    editCodeModal.addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;
        const userId = button.getAttribute('data-user-id');
        const userName = button.getAttribute('data-user-name');
        const currentCode = button.getAttribute('data-current-code');
        
        editCodeModal.querySelector('#modalUserId').value = userId;
        editCodeModal.querySelector('#modalUserName').textContent = userName;
        editCodeModal.querySelector('#modalCurrentCode').value = currentCode;
    });
</script>

<?php require 'footer.php'; ?>
