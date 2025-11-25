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
if (!isset($_SESSION['user'])) {
    header("Location: " . url('index'));
    exit;
}

$currentUser = $_SESSION['user'];
$isAdmin = ($currentUser === 'Admin');

// Handle Add Transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_transaction') {
    $date = $_POST['date'];
    $description = trim($_POST['description']);
    $category = $_POST['category'];
    $amount = str_replace('.', '', $_POST['amount']); // Remove dots
    $type = $_POST['type'];
    $projectId = $_POST['project_id'] ?? 1; // Default to 1 (Main) if not specified
    
    // Determine who this transaction belongs to
    $targetUser = $currentUser;
    if ($isAdmin && isset($_POST['target_user']) && !empty($_POST['target_user'])) {
        $targetUser = $_POST['target_user'];
    }

    if (!empty($date) && !empty($description) && !empty($amount)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO expenses (date, description, category, amount, type, user, project_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$date, $description, $category, $amount, $type, $targetUser, $projectId]);
            $_SESSION['message'] = '<div class="alert alert-success alert-dismissible fade show" role="alert">Transaksi berhasil disimpan!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (PDOException $e) {
            $_SESSION['message'] = '<div class="alert alert-danger">Gagal menyimpan: ' . $e->getMessage() . '</div>';
        }
    } else {
        $_SESSION['message'] = '<div class="alert alert-warning">Mohon lengkapi semua data.</div>';
    }
    header("Location: " . url('dashboard'));
    exit;
}

// Handle Create Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_project') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO projects (user_id, name, description) VALUES (?, ?, ?)");
            $stmt->execute([$currentUser, $name, $description]);
            $_SESSION['message'] = '<div class="alert alert-success alert-dismissible fade show" role="alert">Projek berhasil dibuat!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (PDOException $e) {
            $_SESSION['message'] = '<div class="alert alert-danger">Gagal membuat projek: ' . $e->getMessage() . '</div>';
        }
    } else {
        $_SESSION['message'] = '<div class="alert alert-warning">Nama projek tidak boleh kosong.</div>';
    }
    header("Location: " . url('dashboard'));
    exit;
}

// Handle Move Funds (Pindah Dana Antar Projek Sendiri)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'move_funds') {
    $sourceProjectId = $_POST['source_project_id'];
    $targetProjectId = $_POST['target_project_id'];
    $amount = str_replace('.', '', $_POST['amount']);
    $date = $_POST['date'];
    $description = trim($_POST['description']);
    
    if (empty($description)) {
        $description = "Pindah Dana";
    }

    if (!empty($sourceProjectId) && !empty($targetProjectId) && !empty($amount) && $amount > 0 && !empty($date)) {
        if ($sourceProjectId == $targetProjectId) {
            $_SESSION['message'] = '<div class="alert alert-warning">Projek asal dan tujuan tidak boleh sama.</div>';
        } else {
            try {
                // Check Source Balance
                $stmtBalance = $pdo->prepare("
                    SELECT 
                        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
                        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense
                    FROM expenses 
                    WHERE user = ? AND project_id = ?
                ");
                $stmtBalance->execute([$currentUser, $sourceProjectId]);
                $balanceStats = $stmtBalance->fetch(PDO::FETCH_ASSOC);
                $currentBalance = ($balanceStats['total_income'] ?? 0) - ($balanceStats['total_expense'] ?? 0);

                if ($currentBalance >= $amount) {
                    $pdo->beginTransaction();

                    // 1. Deduct from Source Project
                    // Get Target Project Name for description
                    $stmtTargetName = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
                    $stmtTargetName->execute([$targetProjectId]);
                    $targetProjectName = $stmtTargetName->fetchColumn();

                    $stmtSender = $pdo->prepare("INSERT INTO expenses (date, description, category, amount, type, user, project_id) VALUES (?, ?, ?, ?, 'expense', ?, ?)");
                    $stmtSender->execute([$date, "Pindah ke $targetProjectName: $description", 'Pindah Dana Keluar', $amount, $currentUser, $sourceProjectId]);

                    // 2. Add to Target Project
                    // Get Source Project Name for description
                    $stmtSourceName = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
                    $stmtSourceName->execute([$sourceProjectId]);
                    $sourceProjectName = $stmtSourceName->fetchColumn();

                    $stmtReceiver = $pdo->prepare("INSERT INTO expenses (date, description, category, amount, type, user, project_id) VALUES (?, ?, ?, ?, 'income', ?, ?)");
                    $stmtReceiver->execute([$date, "Pindah dari $sourceProjectName: $description", 'Pindah Dana Masuk', $amount, $currentUser, $targetProjectId]);

                    $pdo->commit();
                    $_SESSION['message'] = '<div class="alert alert-success alert-dismissible fade show" role="alert">Dana berhasil dipindahkan!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                } else {
                    $_SESSION['message'] = '<div class="alert alert-danger">Saldo projek asal tidak mencukupi.</div>';
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['message'] = '<div class="alert alert-danger">Gagal pindah dana: ' . $e->getMessage() . '</div>';
            }
        }
    } else {
        $_SESSION['message'] = '<div class="alert alert-warning">Mohon lengkapi data pindah dana.</div>';
    }
    header("Location: " . url('dashboard') . "?project_id=" . $sourceProjectId);
    exit;
}

// Handle Transfer Balance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfer_balance') {
    $targetUser = $_POST['target_user'];
    $amount = str_replace('.', '', $_POST['amount']);
    $date = $_POST['date'];
    $description = trim($_POST['description']);
    
    if (empty($description)) {
        $description = "Transfer";
    }

    if (!empty($targetUser) && !empty($amount) && $amount > 0 && !empty($date)) {
        try {
            // Check Sender Balance
            $stmtBalance = $pdo->prepare("
                SELECT 
                    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
                    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense
                FROM expenses 
                WHERE user = ?
            ");
            $stmtBalance->execute([$currentUser]);
            $balanceStats = $stmtBalance->fetch(PDO::FETCH_ASSOC);
            $currentBalance = ($balanceStats['total_income'] ?? 0) - ($balanceStats['total_expense'] ?? 0);

            if ($currentBalance >= $amount) {
                $pdo->beginTransaction();

                // 1. Deduct from Sender (Current Project context)
                // Transfer to friend always comes from the CURRENT project the user is viewing.
                $stmtSender = $pdo->prepare("INSERT INTO expenses (date, description, category, amount, type, user, project_id) VALUES (?, ?, ?, ?, 'expense', ?, ?)");
                $stmtSender->execute([$date, "Transfer ke $targetUser: $description", 'Transfer Keluar', $amount, $currentUser, $projectId]);

                // 2. Add to Receiver (ALWAYS to Main Wallet / Project ID 1)
                // This ensures the receiver knows where the money is.
                $receiverProjectId = 1; 
                $stmtReceiver = $pdo->prepare("INSERT INTO expenses (date, description, category, amount, type, user, project_id) VALUES (?, ?, ?, ?, 'income', ?, ?)");
                $stmtReceiver->execute([$date, "Transfer dari $currentUser: $description", 'Transfer Masuk', $amount, $targetUser, $receiverProjectId]);

                $pdo->commit();
                $_SESSION['message'] = '<div class="alert alert-success alert-dismissible fade show" role="alert">Transfer berhasil!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            } else {
                $_SESSION['message'] = '<div class="alert alert-danger">Saldo tidak mencukupi.</div>';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['message'] = '<div class="alert alert-danger">Gagal transfer: ' . $e->getMessage() . '</div>';
        }
    } else {
        $_SESSION['message'] = '<div class="alert alert-warning">Mohon lengkapi data transfer.</div>';
    }
    header("Location: " . url('dashboard'));
    exit;
}

// Handle Delete Transaction
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Verify ownership or Admin privilege
    $check = $pdo->prepare("SELECT user FROM expenses WHERE id = ?");
    $check->execute([$id]);
    $transaction = $check->fetch(PDO::FETCH_ASSOC);

    if ($transaction && ($isAdmin || $transaction['user'] === $currentUser)) {
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['message'] = '<div class="alert alert-success alert-dismissible fade show" role="alert">Transaksi dihapus.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    } else {
        $_SESSION['message'] = '<div class="alert alert-danger">Anda tidak berhak menghapus data ini.</div>';
    }
    header("Location: " . url('dashboard'));
    exit;
}

// Fallback
header("Location: dashboard");
exit;
?>
