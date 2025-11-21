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

// Handle Submit Reimbursement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_reimbursement') {
    $date = $_POST['date'];
    $description = trim($_POST['description']);
    $category = $_POST['category'];
    $amount = str_replace('.', '', $_POST['amount']); // Remove dots
    
    if (!empty($date) && !empty($description) && !empty($amount)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO reimbursements (user, date, category, description, amount, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$currentUser, $date, $category, $description, $amount]);
            $_SESSION['message'] = '<div class="alert alert-success alert-dismissible fade show" role="alert">Reimbursement berhasil diajukan!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (PDOException $e) {
            $_SESSION['message'] = '<div class="alert alert-danger">Gagal mengajukan: ' . $e->getMessage() . '</div>';
        }
    } else {
        $_SESSION['message'] = '<div class="alert alert-warning">Mohon lengkapi semua data.</div>';
    }
    header("Location: " . url('dashboard') . "#reimbursement");
    exit;
}

// Handle Approve Reimbursement (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_reimbursement' && $isAdmin) {
    $id = $_POST['reimbursement_id'];
    $notes = trim($_POST['notes'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE reimbursements SET status = 'approved', notes = ?, processed_at = CURRENT_TIMESTAMP, processed_by = ? WHERE id = ?");
    $stmt->execute([$notes, $currentUser, $id]);
    $_SESSION['message'] = '<div class="alert alert-success alert-dismissible fade show" role="alert">Reimbursement disetujui!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    header("Location: " . url('dashboard') . "#reimbursement");
    exit;
}

// Handle Reject Reimbursement (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_reimbursement' && $isAdmin) {
    $id = $_POST['reimbursement_id'];
    $notes = trim($_POST['notes'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE reimbursements SET status = 'rejected', notes = ?, processed_at = CURRENT_TIMESTAMP, processed_by = ? WHERE id = ?");
    $stmt->execute([$notes, $currentUser, $id]);
    $_SESSION['message'] = '<div class="alert alert-warning alert-dismissible fade show" role="alert">Reimbursement ditolak.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    header("Location: " . url('dashboard') . "#reimbursement");
    exit;
}

// Handle Mark as Paid (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_paid' && $isAdmin) {
    $id = $_POST['reimbursement_id'];
    
    $stmt = $pdo->prepare("UPDATE reimbursements SET status = 'paid' WHERE id = ? AND status = 'approved'");
    $stmt->execute([$id]);
    $_SESSION['message'] = '<div class="alert alert-success alert-dismissible fade show" role="alert">Reimbursement ditandai sebagai dibayar!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    header("Location: " . url('dashboard') . "#reimbursement");
    exit;
}

// Fallback
header("Location: " . url('dashboard'));
exit;
?>
