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

requireLogin();

$currentUser = getCurrentUser();
$isAdmin = isAdmin();

// Handle Submit Reimbursement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_reimbursement') {
    // Validate CSRF token
    if (!validateCSRFToken()) {
        setErrorMessage('Invalid security token. Please try again.');
        header("Location: " . url('dashboard') . "#reimbursement");
        exit;
    }
    
    $date = sanitizeString($_POST['date']);
    $description = sanitizeString($_POST['description']);
    $category = sanitizeString($_POST['category']);
    $amount = sanitizeAmount($_POST['amount']);
    
    if (!empty($date) && !empty($description) && !empty($amount)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO reimbursements (user, date, category, description, amount, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$currentUser, $date, $category, $description, $amount]);
            setSuccessMessage('Reimbursement berhasil diajukan!');
        } catch (PDOException $e) {
            setErrorMessage('Gagal mengajukan: ' . $e->getMessage());
        }
    } else {
        setWarningMessage('Mohon lengkapi semua data.');
    }
    header("Location: " . url('dashboard') . "#reimbursement");
    exit;
}

// Handle Approve Reimbursement (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_reimbursement' && $isAdmin) {
    // Validate CSRF token
    if (!validateCSRFToken()) {
        setErrorMessage('Invalid security token. Please try again.');
        header("Location: " . url('dashboard') . "#reimbursement");
        exit;
    }
    
    $id = sanitizeInt($_POST['reimbursement_id']);
    $notes = sanitizeString($_POST['notes'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE reimbursements SET status = 'approved', notes = ?, processed_at = CURRENT_TIMESTAMP, processed_by = ? WHERE id = ?");
    $stmt->execute([$notes, $currentUser, $id]);
    setSuccessMessage('Reimbursement disetujui!');
    header("Location: " . url('dashboard') . "#reimbursement");
    exit;
}

// Handle Reject Reimbursement (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_reimbursement' && $isAdmin) {
    // Validate CSRF token
    if (!validateCSRFToken()) {
        setErrorMessage('Invalid security token. Please try again.');
        header("Location: " . url('dashboard') . "#reimbursement");
        exit;
    }
    
    $id = sanitizeInt($_POST['reimbursement_id']);
    $notes = sanitizeString($_POST['notes'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE reimbursements SET status = 'rejected', notes = ?, processed_at = CURRENT_TIMESTAMP, processed_by = ? WHERE id = ?");
    $stmt->execute([$notes, $currentUser, $id]);
    setWarningMessage('Reimbursement ditolak.');
    header("Location: " . url('dashboard') . "#reimbursement");
    exit;
}

// Handle Mark as Paid (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_paid' && $isAdmin) {
    // Validate CSRF token
    if (!validateCSRFToken()) {
        setErrorMessage('Invalid security token. Please try again.');
        header("Location: " . url('dashboard') . "#reimbursement");
        exit;
    }
    
    $id = sanitizeInt($_POST['reimbursement_id']);
    
    $stmt = $pdo->prepare("UPDATE reimbursements SET status = 'paid' WHERE id = ? AND status = 'approved'");
    $stmt->execute([$id]);
    setSuccessMessage('Reimbursement ditandai sebagai dibayar!');
    header("Location: " . url('dashboard') . "#reimbursement");
    exit;
}

// Fallback
header("Location: " . url('dashboard'));
exit;
?>
