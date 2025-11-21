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
    
    // Determine who this transaction belongs to
    $targetUser = $currentUser;
    if ($isAdmin && isset($_POST['target_user']) && !empty($_POST['target_user'])) {
        $targetUser = $_POST['target_user'];
    }

    if (!empty($date) && !empty($description) && !empty($amount)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO expenses (date, description, category, amount, type, user) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$date, $description, $category, $amount, $type, $targetUser]);
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
